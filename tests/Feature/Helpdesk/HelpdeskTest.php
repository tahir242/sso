<?php

use App\Domain\Helpdesk\Actions\ChangeTicketStatus;
use App\Domain\Helpdesk\Actions\OpenTicket;
use App\Domain\Helpdesk\Actions\ProcessHelpdeskSla;
use App\Domain\Helpdesk\Actions\ReplyToTicket;
use App\Domain\Helpdesk\Actions\UpdateTicketDetails;
use App\Domain\Helpdesk\Enums\CommentKind;
use App\Domain\Helpdesk\Enums\TicketPriority;
use App\Domain\Helpdesk\Enums\TicketStatus;
use App\Domain\Helpdesk\Models\Ticket;
use App\Domain\Helpdesk\Models\TicketCategory;
use App\Domain\Helpdesk\Support\HelpdeskReport;
use App\Domain\Identity\Models\User;
use App\Domain\Settings\HelpdeskSettings;
use App\Livewire\Support\MyTickets;
use App\Livewire\Support\NewTicket;
use App\Livewire\Support\TicketShow;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Storage::fake('media');

    $this->agent = User::factory()->create()->assignRole(Role::findOrCreate('helpdesk', 'web'));
    $this->owner = User::factory()->create();
    $this->requester = User::factory()->create();
    $this->lab = makeApp('Lab Reports', $this->owner);
    $this->printing = TicketCategory::create(['name' => 'Printing', 'application_id' => $this->lab->id]);
    $this->general = TicketCategory::create(['name' => 'Password or login']);
});

function raiseTicket(User $requester, TicketPriority $priority = TicketPriority::Normal, ?TicketCategory $category = null): Ticket
{
    return app(OpenTicket::class)->handle($requester, null, $category, 'Report PDF is blank', 'Printing a report gives an empty page.', $priority);
}

it('raises a ticket from the portal with SLA dates, attachments and a helpdesk alert', function () {
    $this->freezeSecond();

    Livewire::actingAs($this->requester)->withQueryParams(['app' => 'lab-reports'])->test(NewTicket::class)
        ->assertSet('application_id', $this->lab->id)
        ->assertSee('Printing')->assertSee('Password or login')
        ->call('submit')
        ->assertHasErrors(['subject', 'description'])
        ->set('category_id', $this->printing->id)
        ->set('priority', 'high')
        ->set('subject', 'Report PDF is blank')
        ->set('description', 'Printing a creatinine report gives an empty page.')
        ->set('attachments', [UploadedFile::fake()->image('screen.png')])
        ->call('submit')
        ->assertHasNoErrors();

    $ticket = Ticket::sole();
    $settings = app(HelpdeskSettings::class);

    expect($ticket)
        ->application_id->toBe($this->lab->id)
        ->status->toBe(TicketStatus::Open)
        ->priority->toBe(TicketPriority::High)
        ->and($ticket->due_at->equalTo(now()->addHours($settings->resolve_hours_high)))->toBeTrue()
        ->and($ticket->response_due_at->equalTo(now()->addHours($settings->first_response_hours)))->toBeTrue()
        ->and($ticket->getMedia('attachments'))->toHaveCount(1)
        ->and($this->agent->notifications()->count())->toBe(1);
});

it('does not offer another application’s categories', function () {
    Livewire::actingAs($this->requester)->test(NewTicket::class)
        ->assertDontSee('Printing')
        ->set('category_id', $this->printing->id)
        ->set('subject', 'Something broke')
        ->set('description', 'It stopped working this morning.')
        ->call('submit')
        ->assertHasErrors('category_id');
});

it('sends new tickets to the category’s default assignee', function () {
    $this->printing->update(['default_assignee_id' => $this->owner->id]);

    $ticket = raiseTicket($this->requester, category: $this->printing);

    expect($ticket->assignee_id)->toBe($this->owner->id)
        ->and($this->owner->notifications()->count())->toBe(1)
        ->and($this->agent->notifications()->count())->toBe(0);
});

it('runs the conversation: staff reply, internal note, requester answer', function () {
    $ticket = raiseTicket($this->requester);
    $reply = app(ReplyToTicket::class);

    $reply->handle($ticket, $this->agent, 'Which printer is it?', status: TicketStatus::Waiting);
    expect($ticket->fresh())
        ->status->toBe(TicketStatus::Waiting)
        ->first_responded_at->not->toBeNull()
        ->and($this->requester->notifications()->count())->toBe(1);

    $reply->handle($ticket->fresh(), $this->agent, 'Probably the toner driver.', internal: true);
    expect($this->requester->notifications()->count())->toBe(1);

    Livewire::actingAs($this->requester)->test(TicketShow::class, ['ulid' => $ticket->ulid])
        ->assertSee('Which printer is it?')
        ->assertDontSee('Probably the toner driver.')
        ->set('body', 'The one in Ward 3.')
        ->call('reply')
        ->assertHasNoErrors();

    expect($ticket->fresh()->status)->toBe(TicketStatus::Open)
        ->and($ticket->comments()->where('kind', CommentKind::Note)->count())->toBe(1);
});

it('keeps tickets private to the requester and the people handling them', function () {
    $ticket = raiseTicket($this->requester);

    $this->actingAs(User::factory()->create())->get(route('support.show', $ticket->ulid))->assertNotFound();
    $this->actingAs($this->requester)->get(route('support.show', $ticket->ulid))->assertOk();
    $this->actingAs($this->agent)->get(route('support.show', $ticket->ulid))->assertOk();

    expect(fn () => app(ReplyToTicket::class)->handle($ticket, User::factory()->create(), 'Hi'))
        ->toThrow(AuthorizationException::class);
});

it('lets the requester close, reopen a resolved ticket, but not change other statuses', function () {
    $ticket = raiseTicket($this->requester);
    $change = app(ChangeTicketStatus::class);

    expect(fn () => $change->handle($ticket, $this->requester, TicketStatus::Resolved))->toThrow(AuthorizationException::class);

    $change->handle($ticket, $this->agent, TicketStatus::Resolved);
    expect($ticket->fresh()->resolved_at)->not->toBeNull();

    Livewire::actingAs($this->requester)->test(TicketShow::class, ['ulid' => $ticket->ulid])
        ->assertSee('Is it working for you?')
        ->call('reopen');
    expect($ticket->fresh())->status->toBe(TicketStatus::Open)->resolved_at->toBeNull();

    Livewire::actingAs($this->requester)->test(TicketShow::class, ['ulid' => $ticket->ulid])->call('close');
    expect($ticket->fresh()->status)->toBe(TicketStatus::Closed);

    expect(fn () => app(ReplyToTicket::class)->handle($ticket->fresh(), $this->requester, 'One more thing'))
        ->toThrow(ValidationException::class);
});

it('recalculates the due date when the priority changes and records assignment', function () {
    $this->freezeSecond();
    $ticket = raiseTicket($this->requester);

    app(UpdateTicketDetails::class)->handle($ticket, $this->agent, $this->owner, TicketPriority::Urgent, $this->general);

    $ticket->refresh();
    expect($ticket->assignee_id)->toBe($this->owner->id)
        ->and($ticket->category_id)->toBe($this->general->id)
        ->and($ticket->due_at->equalTo($ticket->created_at->copy()->addHours(app(HelpdeskSettings::class)->resolve_hours_urgent)))->toBeTrue()
        ->and($ticket->comments()->where('kind', CommentKind::Event)->count())->toBe(3)
        ->and($this->owner->notifications()->count())->toBe(1);

    // The application owner can now work the ticket; a random user cannot.
    expect(fn () => app(UpdateTicketDetails::class)->handle($ticket, User::factory()->create(), null, TicketPriority::Low, null))
        ->toThrow(AuthorizationException::class);
});

it('alerts once about overdue tickets and auto-closes old resolved ones', function () {
    $overdue = raiseTicket($this->requester, TicketPriority::Urgent);
    $old = raiseTicket($this->requester);
    app(ChangeTicketStatus::class)->handle($old, $this->agent, TicketStatus::Resolved);
    $before = $this->agent->notifications()->count();

    $this->travel(10)->days();
    $result = app(ProcessHelpdeskSla::class)->handle();

    expect($result)->toBe(['overdue' => 1, 'closed' => 1])
        ->and($old->fresh()->status)->toBe(TicketStatus::Closed)
        ->and($this->agent->notifications()->count())->toBe($before + 1)
        ->and(app(ProcessHelpdeskSla::class)->handle()['overdue'])->toBe(0);

    $this->artisan('helpdesk:sla')->assertSuccessful();
});

it('lists my tickets and nags about ones waiting for me', function () {
    $ticket = raiseTicket($this->requester);
    app(ReplyToTicket::class)->handle($ticket, $this->agent, 'Which printer?', status: TicketStatus::Waiting);

    Livewire::actingAs($this->requester)->test(MyTickets::class)
        ->assertSee($ticket->reference)
        ->assertSee('waiting for your answer');
});

it('reports volumes and on-time rates', function () {
    $onTime = raiseTicket($this->requester);
    app(ReplyToTicket::class)->handle($onTime, $this->agent, 'Fixed.', status: TicketStatus::Resolved);
    raiseTicket($this->requester, TicketPriority::Urgent);

    $this->travel(2)->days();
    $report = HelpdeskReport::since(now()->subDays(30));

    expect($report)
        ->raised->toBe(2)
        ->resolved->toBe(1)
        ->overdue_now->toBe(1)
        ->resolve_on_time->toBe(50)
        ->response_on_time->toBe(50)
        ->and($report['by_application']->first()['name'])->toBe('General IT');
});
