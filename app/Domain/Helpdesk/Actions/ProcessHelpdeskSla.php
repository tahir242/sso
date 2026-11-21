<?php

namespace App\Domain\Helpdesk\Actions;

use App\Domain\Helpdesk\Enums\CommentKind;
use App\Domain\Helpdesk\Enums\TicketStatus;
use App\Domain\Helpdesk\Models\Ticket;
use App\Domain\Helpdesk\Notifications\TicketNotification;
use App\Domain\Helpdesk\Support\Helpdesk;
use App\Domain\Settings\HelpdeskSettings;
use Illuminate\Support\Facades\Notification;

/**
 * Hourly: alert the assignee and the helpdesk once about tickets past their
 * resolution due date, and close resolved tickets nobody replied to.
 */
final readonly class ProcessHelpdeskSla
{
    public function __construct(private HelpdeskSettings $settings) {}

    /**
     * @return array{overdue: int, closed: int}
     */
    public function handle(): array
    {
        $overdue = 0;
        $agents = Helpdesk::agents();

        Ticket::query()->overdue()->whereNull('breach_notified_at')->with(['assignee', 'application'])
            ->each(function (Ticket $ticket) use (&$overdue, $agents) {
                $recipients = ($ticket->assignee ? $agents->merge([$ticket->assignee]) : $agents)->unique('id');

                Notification::send($recipients, new TicketNotification(
                    __('Overdue: :ref :subject', ['ref' => $ticket->reference, 'subject' => $ticket->subject]),
                    __(':priority priority, due :due. :who', [
                        'priority' => $ticket->priority->getLabel(),
                        'due' => $ticket->due_at->format('j M, H:i'),
                        'who' => $ticket->assignee ? __('Assigned to :name.', ['name' => $ticket->assignee->name]) : __('Not assigned.'),
                    ]),
                    TicketLinks::staff($ticket),
                ));

                $ticket->forceFill(['breach_notified_at' => now()])->save();
                $overdue++;
            });

        $closed = 0;

        if ($this->settings->auto_close_days > 0) {
            Ticket::query()->where('status', TicketStatus::Resolved)
                ->where('resolved_at', '<', now()->subDays($this->settings->auto_close_days))
                ->each(function (Ticket $ticket) use (&$closed) {
                    $ticket->update(['status' => TicketStatus::Closed, 'closed_at' => now()]);
                    $ticket->comments()->create([
                        'user_id' => $ticket->assignee_id ?? $ticket->requester_id,
                        'kind' => CommentKind::Event,
                        'body' => __('Closed automatically after :days days with no reply', ['days' => $this->settings->auto_close_days]),
                    ]);
                    $closed++;
                });
        }

        return ['overdue' => $overdue, 'closed' => $closed];
    }
}
