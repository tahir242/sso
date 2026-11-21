<?php

namespace App\Livewire\Support;

use App\Domain\Helpdesk\Actions\ChangeTicketStatus;
use App\Domain\Helpdesk\Actions\ReplyToTicket;
use App\Domain\Helpdesk\Enums\TicketStatus;
use App\Domain\Helpdesk\Models\Ticket;
use App\Domain\Helpdesk\Support\Helpdesk;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * The requester's view of a ticket: the public conversation, a reply box,
 * and "solved" / "reopen" buttons. Internal notes are never loaded here.
 */
#[Layout('layouts::app')]
class TicketShow extends Component
{
    use WithFileUploads;

    #[Locked]
    public string $ulid;

    public string $body = '';

    /** @var array<int, TemporaryUploadedFile> */
    public array $attachments = [];

    public function mount(string $ulid): void
    {
        $this->ulid = $ulid;
        $this->ticket(); // 404s for anyone who may not see it
    }

    public function reply(ReplyToTicket $reply): void
    {
        $this->validate([
            'body' => ['required', 'string', 'min:2', 'max:5000'],
            'attachments' => ['array', 'max:3'],
            'attachments.*' => ['file', 'max:5120', 'mimes:png,jpg,jpeg,webp,pdf'],
        ], attributes: ['body' => 'message']);

        $reply->handle($this->ticket(), auth()->user(), $this->body, attachments: $this->attachments);

        $this->reset('body', 'attachments');
    }

    public function close(ChangeTicketStatus $change): void
    {
        $change->handle($this->ticket(), auth()->user(), TicketStatus::Closed);
        session()->flash('status', __('Thanks — the ticket is closed.'));
    }

    public function reopen(ChangeTicketStatus $change): void
    {
        $change->handle($this->ticket(), auth()->user(), TicketStatus::Open);
    }

    public function render(): View
    {
        $ticket = $this->ticket()->load(['application', 'category', 'assignee', 'requester', 'media', 'publicComments.author']);

        return view('livewire.support.ticket-show', [
            'ticket' => $ticket,
            'isStaff' => Helpdesk::canHandle($ticket, auth()->user()),
        ])->title($ticket->reference.' · '.$ticket->subject);
    }

    private function ticket(): Ticket
    {
        $ticket = Ticket::where('ulid', $this->ulid)->firstOrFail();
        abort_unless(Helpdesk::canView($ticket, auth()->user()), 404);

        return $ticket;
    }
}
