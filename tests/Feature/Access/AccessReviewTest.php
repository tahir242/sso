<?php

use App\Domain\Access\Actions\CloseAccessReview;
use App\Domain\Access\Actions\DecideReviewItem;
use App\Domain\Access\Actions\RunAccessReviewSchedule;
use App\Domain\Access\Actions\StartAccessReview;
use App\Domain\Access\Enums\ReviewDecision;
use App\Domain\Access\Models\AccessReviewCampaign;
use App\Domain\Access\Models\AccessReviewItem;
use App\Domain\Access\Notifications\AccessRemovedByReview;
use App\Domain\Access\Notifications\AccessReviewAssigned;
use App\Domain\Access\Notifications\AccessReviewReminder;
use App\Domain\Federation\Actions\GrantApplicationAccess;
use App\Domain\Federation\Actions\RegisterApplication;
use App\Domain\Federation\Models\Application;
use App\Domain\Identity\Models\User;
use App\Domain\Settings\AccessSettings;
use App\Filament\Resources\AccessReviews\Pages\CreateAccessReview;
use App\Filament\Resources\AccessReviews\Pages\ViewAccessReview;
use App\Livewire\Access\Reviews;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Notification::fake();
    Role::findOrCreate('super-admin', 'web');

    $this->owner = User::factory()->create();
    $this->admin = tap(User::factory()->create(), fn (User $u) => $u->assignRole('super-admin'));
    [$this->hr] = app(RegisterApplication::class)->handle([
        'slug' => 'hr', 'name' => 'HR System', 'base_url' => 'https://hr.example.test', 'owner_user_id' => $this->owner->id, 'active' => true,
    ], ['https://hr.example.test/cb']);
    [$this->lab] = app(RegisterApplication::class)->handle([
        'slug' => 'lab', 'name' => 'Lab Reports', 'base_url' => 'https://lab.example.test', 'active' => true,
    ], ['https://lab.example.test/cb']);

    $this->alice = User::factory()->create(['name' => 'Alice']);
    $this->bob = User::factory()->create(['name' => 'Bob']);
    app(GrantApplicationAccess::class)->handle($this->hr, $this->alice);
    app(GrantApplicationAccess::class)->handle($this->hr, $this->bob);
    app(GrantApplicationAccess::class)->handle($this->hr, $this->owner);   // owner's own access
    app(GrantApplicationAccess::class)->handle($this->lab, $this->alice);  // app without owner
});

function startReview(?Application $app = null, bool $autoRevoke = false): AccessReviewCampaign
{
    return app(StartAccessReview::class)->handle('Q4 review', $app, now()->addDays(14), $autoRevoke);
}

it('snapshots current access and routes items to the right reviewers', function () {
    $campaign = startReview();

    expect($campaign->items()->count())->toBe(4)
        ->and($campaign->items()->where('reviewer_id', $this->owner->id)->count())->toBe(2)   // alice + bob on HR
        ->and($campaign->items()->whereNull('reviewer_id')->count())->toBe(2)                // owner's own HR access + Lab (no owner)
        ->and($campaign->items()->first()->roles_snapshot)->toBe(['user']);

    Notification::assertSentTo($this->owner, AccessReviewAssigned::class, fn ($n) => $n->count === 2);
    Notification::assertSentTo($this->admin, AccessReviewAssigned::class);
});

it('removes access (and tokens) when the reviewer says so', function () {
    $campaign = startReview($this->hr);
    $item = $campaign->items()->where('user_id', $this->bob->id)->first();

    app(DecideReviewItem::class)->handle($item, $this->owner, keep: false, comment: 'Moved to Pharmacy');

    expect($this->hr->assignmentFor($this->bob))->toBeNull()
        ->and($item->fresh()->decision)->toBe(ReviewDecision::Revoke);
    Notification::assertSentTo($this->bob, AccessRemovedByReview::class);
});

it('only lets the assigned reviewer or a super-admin decide, never about themselves', function () {
    $campaign = startReview($this->hr);
    $bobItem = $campaign->items()->where('user_id', $this->bob->id)->first();
    $ownerItem = $campaign->items()->where('user_id', $this->owner->id)->first();

    expect(fn () => app(DecideReviewItem::class)->handle($bobItem, $this->alice, keep: true))->toThrow(AuthorizationException::class)
        ->and($ownerItem->canBeDecidedBy($this->owner))->toBeFalse()
        ->and($ownerItem->canBeDecidedBy($this->admin))->toBeTrue();
});

it('keeps access for everyone remaining from the portal inbox', function () {
    startReview($this->hr);
    $this->actingAs($this->owner);

    Livewire::test(Reviews::class)
        ->assertSee('Alice')->assertSee('Bob')->assertDontSee('Lab Reports')
        ->call('keepAll', AccessReviewCampaign::first()->id, $this->hr->id)
        ->assertSee('No access to review');

    expect(AccessReviewItem::where('decision', ReviewDecision::Keep)->count())->toBe(2)
        ->and($this->hr->assignmentFor($this->alice))->not->toBeNull();
});

it('removes one person from the portal inbox with a reason', function () {
    startReview($this->hr);
    $this->actingAs($this->owner);
    $item = AccessReviewItem::where('user_id', $this->bob->id)->first();

    Livewire::test(Reviews::class)
        ->call('startRemove', $item->id)
        ->set("reasons.{$item->id}", 'Left the organization')
        ->call('confirmRemove', $item->id);

    expect($item->fresh()->comment)->toBe('Left the organization')
        ->and($this->hr->assignmentFor($this->bob))->toBeNull();
});

it('closes reviews: keeps or removes unconfirmed access as configured', function () {
    $keepCampaign = startReview($this->hr);
    app(CloseAccessReview::class)->handle($keepCampaign);

    expect($keepCampaign->fresh()->status)->toBe(AccessReviewCampaign::CLOSED)
        ->and($keepCampaign->items()->where('decision', ReviewDecision::Expired)->count())->toBe(3)
        ->and($this->hr->assignmentFor($this->alice))->not->toBeNull();

    $strict = startReview($this->hr, autoRevoke: true);
    app(CloseAccessReview::class)->handle($strict);

    expect($this->hr->assignmentFor($this->alice))->toBeNull()
        ->and($strict->items()->where('decision', ReviewDecision::Revoke)->count())->toBe(3);
});

it('reminds reviewers near the deadline and closes overdue reviews', function () {
    startReview($this->hr);

    $this->travel(12)->days();
    expect(app(RunAccessReviewSchedule::class)->handle()['reminded'])->toBe(1)
        ->and(app(RunAccessReviewSchedule::class)->handle()['reminded'])->toBe(0); // once a day
    Notification::assertSentTo($this->owner, AccessReviewReminder::class);

    $this->travel(3)->days();
    expect(app(RunAccessReviewSchedule::class)->handle()['closed'])->toBe(1);
});

it('starts a scheduled review once the interval has passed', function () {
    $settings = app(AccessSettings::class);
    $settings->review_interval_months = 3;
    $settings->save();

    expect(app(RunAccessReviewSchedule::class)->handle()['started'])->toBeFalse();

    $this->travelTo(now()->addMonths(3)->addDay());
    expect(app(RunAccessReviewSchedule::class)->handle()['started'])->toBeTrue()
        ->and(AccessReviewCampaign::where('scheduled', true)->count())->toBe(1)
        ->and(app(RunAccessReviewSchedule::class)->handle()['started'])->toBeFalse();
});

it('refuses a second open review of the same application', function () {
    startReview($this->hr);

    expect(fn () => startReview($this->hr))->toThrow(ValidationException::class);
});

it('lets super-admins start, close and export reviews in the console', function () {
    $this->actingAs($this->admin);

    Livewire::test(CreateAccessReview::class)
        ->fillForm(['name' => 'HR review', 'application_id' => $this->hr->id, 'due_at' => now()->addWeek(), 'auto_revoke' => false])
        ->call('create')
        ->assertHasNoFormErrors();

    $campaign = AccessReviewCampaign::firstOrFail();
    expect($campaign->items()->count())->toBe(3);

    Livewire::test(ViewAccessReview::class, ['record' => $campaign->ulid])
        ->callAction('export')
        ->assertFileDownloaded()
        ->callAction('close');

    expect($campaign->fresh()->isOpen())->toBeFalse();
});
