<?php

use App\Domain\Helpdesk\Actions\OpenTicket;
use App\Domain\Helpdesk\Enums\TicketPriority;
use App\Domain\Helpdesk\Enums\TicketStatus;
use App\Domain\Helpdesk\Models\Ticket;
use App\Domain\Identity\Models\User;
use App\Filament\Pages\HelpdeskReports;
use App\Filament\Pages\Settings\ManageHelpdesk;
use App\Filament\Resources\TicketCategories\Pages\ManageTicketCategories;
use App\Filament\Resources\Tickets\Pages\ListTickets;
use App\Filament\Resources\Tickets\Pages\ViewTicket;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Storage::fake('media');
    $this->agent = User::factory()->create()->assignRole(Role::findOrCreate('helpdesk', 'web'));
    $this->owner = User::factory()->create()->assignRole(Role::findOrCreate('app-owner', 'web'));
    $this->requester = User::factory()->create();
});

function ticketFor(User $requester, $application = null): Ticket
{
    return app(OpenTicket::class)->handle($requester, $application, null, 'Cannot sign in', 'It says my account is locked.', TicketPriority::Normal);
}

it('shows app owners only their applications’ tickets', function () {
    $mine = ticketFor($this->requester, makeApp('Lab Reports', $this->owner));
    $other = ticketFor($this->requester, makeApp('Pharmacy'));

    $this->actingAs($this->owner);
    Livewire::test(ListTickets::class, ['activeTab' => 'all'])
        ->assertCanSeeTableRecords([$mine])->assertCanNotSeeTableRecords([$other]);

    $this->get(ticketAdminUrl($other))->assertNotFound();

    $this->actingAs($this->agent);
    Livewire::test(ListTickets::class, ['activeTab' => 'unassigned'])->assertCanSeeTableRecords([$mine, $other]);
});

function ticketAdminUrl(Ticket $ticket): string
{
    return url('/admin/tickets/'.$ticket->ulid);
}

it('works a ticket from the admin console', function () {
    $ticket = ticketFor($this->requester);
    $this->actingAs($this->agent);

    Livewire::test(ViewTicket::class, ['record' => $ticket->getRouteKey()])
        ->assertSee('It says my account is locked.')
        ->callAction('take')
        ->assertHasNoActionErrors()
        ->callAction('reply', [
            'body' => 'Unlocked — please try again.',
            'internal' => false,
            'status' => TicketStatus::Resolved->value,
            'attachments' => [UploadedFile::fake()->image('steps.png')],
        ])
        ->assertHasNoActionErrors()
        ->callAction('reply', ['body' => 'Lockout came from an old phone.', 'internal' => true, 'status' => TicketStatus::Resolved->value])
        ->assertHasNoActionErrors()
        ->assertSee('Internal note');

    $ticket->refresh();
    expect($ticket)
        ->assignee_id->toBe($this->agent->id)
        ->status->toBe(TicketStatus::Resolved)
        ->first_responded_at->not->toBeNull()
        ->and($ticket->getMedia('attachments'))->toHaveCount(1);

    Livewire::test(ViewTicket::class, ['record' => $ticket->getRouteKey()])
        ->callAction('details', ['assignee_id' => $this->agent->id, 'priority' => 'high', 'category_id' => null])
        ->assertHasNoActionErrors()
        ->callAction('status', ['status' => 'closed'])
        ->assertHasNoActionErrors();

    expect($ticket->fresh())->priority->toBe(TicketPriority::High)->status->toBe(TicketStatus::Closed);
});

it('renders categories, reports and helpdesk settings for staff', function () {
    $this->actingAs($this->agent);

    Livewire::test(ManageTicketCategories::class)
        ->callAction('create', ['name' => 'Printing', 'active' => true, 'sort_order' => 0])
        ->assertHasNoActionErrors();

    ticketFor($this->requester);
    Livewire::test(HelpdeskReports::class)->assertSee('First reply on time')->assertSee('General IT')
        ->set('days', 7)->assertOk();

    $this->actingAs(superAdmin());
    Livewire::test(ManageHelpdesk::class)
        ->fillForm(['resolve_hours_urgent' => 2])
        ->call('save')
        ->assertHasNoFormErrors();
});
