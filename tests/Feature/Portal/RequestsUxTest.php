<?php

use App\Domain\Federation\Enums\AccessMode;
use App\Domain\Helpdesk\Models\Ticket;
use App\Domain\Identity\Models\Department;
use App\Domain\Identity\Models\User;
use App\Domain\Requests\Actions\MoveChangeRequestToHelpdesk;
use App\Domain\Requests\Actions\SubmitChangeRequest;
use App\Domain\Requests\Enums\ChangeRequestStatus;
use App\Domain\Requests\Enums\ChangeRequestType;
use App\Domain\Requests\Enums\Priority;
use App\Filament\Resources\ChangeRequests\ChangeRequestResource;
use App\Filament\Resources\ChangeRequests\Pages\ViewChangeRequest;
use App\Livewire\Requests\ChangeRequestForm;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(fn () => Storage::fake('media'));

it('offers one place to start any request, in plain words', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('requests.new'))
        ->assertOk()
        ->assertSee('What do you need?')
        ->assertSee('Something isn’t working', false)
        ->assertSee('I need access to an application')
        ->assertSee('I have an idea to improve an application')
        ->assertSee(route('support.new'))
        ->assertSee(route('access.request'))
        ->assertSee(route('suggestions.new'));
});

it('shows one "My requests" menu and hides Approvals from people who approve nothing', function () {
    $staff = User::factory()->create();

    $this->actingAs($staff)->get(route('home'))->assertOk()
        ->assertSee('My requests')
        ->assertDontSee(route('requests.new')) // no header button; the home page has the quick actions
        ->assertDontSee(route('approvals'))
        ->assertDontSee('Something isn’t working', false); // home is just the launcher

    $hod = User::factory()->create();
    Department::create(['code' => 'NEPH', 'name' => 'Nephrology', 'hod_user_id' => $hod->id]);

    $this->actingAs($hod)->get(route('home'))->assertSee(route('approvals'));
});

it('lists access, IT help and suggestions as tabs of My requests', function () {
    $user = User::factory()->create();

    foreach ([route('access.requests'), route('support'), route('access.requests', ['tab' => 'changes'])] as $url) {
        $this->actingAs($user)->get($url)->assertOk()
            ->assertSee('My requests')->assertSee('Access')->assertSee('IT help')->assertSee('Suggestions');
    }
});

it('takes suggestions for a chosen application and no longer takes problem reports', function () {
    $owner = User::factory()->create();
    $app = makeApp('Lab Reports', $owner);
    $app->update(['access_mode' => AccessMode::Everyone]);
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(ChangeRequestForm::class)
        ->assertSee('Something not working?')
        ->set('type', 'bug')
        ->set('title', 'Add a landscape print option')
        ->set('description', 'Wide reports are cut off when printed on A4 paper.')
        ->call('submit')
        ->assertHasErrors(['app', 'type'])
        ->set('app', 'lab-reports')
        ->set('type', 'feature')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect(route('access.requests', ['tab' => 'changes']));

    expect($app->changeRequests()->count())->toBe(1);
});

it('moves a problem sent as a suggestion to the IT helpdesk', function () {
    $owner = User::factory()->create();
    $app = makeApp('Lab Reports', $owner);
    $user = User::factory()->create();
    $request = app(SubmitChangeRequest::class)->handle($user, $app, ChangeRequestType::Change, 'Report PDF is blank', 'Printing gives an empty page since Monday.', Priority::High);

    $owner->assignRole(Role::findOrCreate('app-owner', 'web'));
    $this->actingAs($owner);
    expect(MoveChangeRequestToHelpdesk::canMove($request, $owner))->toBeTrue()
        ->and(MoveChangeRequestToHelpdesk::canMove($request, $user))->toBeFalse();

    Livewire::test(ViewChangeRequest::class, ['record' => $request->getRouteKey()])
        ->callAction('moveToHelpdesk')
        ->assertNotified();

    $ticket = Ticket::sole();
    expect($ticket->requester_id)->toBe($user->id)
        ->and($ticket->application_id)->toBe($app->id)
        ->and($ticket->subject)->toBe('Report PDF is blank')
        ->and($request->fresh()->status)->toBe(ChangeRequestStatus::Declined)
        ->and($request->fresh()->owner_comment)->toContain($ticket->reference)
        ->and(MoveChangeRequestToHelpdesk::canMove($request->fresh(), $owner))->toBeFalse();
});

it('files change requests under Requests in the admin console', function () {
    expect(ChangeRequestResource::getNavigationGroup())->toBe('Requests');
});

it('marks the required fields on the request forms', function () {
    $user = User::factory()->create();

    foreach ([route('support.new'), route('access.request'), route('suggestions.new')] as $url) {
        $this->actingAs($user)->get($url)->assertOk()
            ->assertSee('<span class="sr-only"> (required)</span>', false)
            ->assertSee('Required');
    }
});
