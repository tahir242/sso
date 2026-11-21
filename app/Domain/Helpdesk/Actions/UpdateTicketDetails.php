<?php

namespace App\Domain\Helpdesk\Actions;

use App\Domain\Helpdesk\Enums\CommentKind;
use App\Domain\Helpdesk\Enums\TicketPriority;
use App\Domain\Helpdesk\Models\Ticket;
use App\Domain\Helpdesk\Models\TicketCategory;
use App\Domain\Helpdesk\Notifications\TicketNotification;
use App\Domain\Helpdesk\Support\Helpdesk;
use App\Domain\Identity\Models\User;
use App\Domain\Settings\HelpdeskSettings;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Staff change who handles a ticket, its priority or its category. A new
 * priority recalculates the resolution due date from when it was raised.
 */
final readonly class UpdateTicketDetails
{
    public function __construct(private HelpdeskSettings $settings) {}

    public function handle(Ticket $ticket, User $actor, ?User $assignee, TicketPriority $priority, ?TicketCategory $category): void
    {
        if (! Helpdesk::canHandle($ticket, $actor)) {
            throw new AuthorizationException(__('You cannot change this ticket.'));
        }

        $events = [];
        $changes = [];

        if ($assignee?->getKey() !== $ticket->assignee_id) {
            $changes['assignee_id'] = $assignee?->getKey();
            $events[] = $assignee ? __('Assigned to :name', ['name' => $assignee->name]) : __('Unassigned');
        }

        if ($priority !== $ticket->priority) {
            $changes['priority'] = $priority;
            $changes['due_at'] = $ticket->created_at->copy()->addHours($this->settings->resolveHours($priority));
            $changes['breach_notified_at'] = null;
            $events[] = __('Priority changed to :priority', ['priority' => $priority->getLabel()]);
        }

        if ($category?->getKey() !== $ticket->category_id) {
            $changes['category_id'] = $category?->getKey();
            $events[] = __('Category changed to :name', ['name' => $category?->name ?? __('none')]);
        }

        if ($changes === []) {
            return;
        }

        DB::transaction(function () use ($ticket, $actor, $changes, $events) {
            $ticket->update([...$changes, 'last_activity_at' => now()]);

            foreach ($events as $event) {
                $ticket->comments()->create(['user_id' => $actor->getKey(), 'kind' => CommentKind::Event, 'body' => $event]);
            }
        });

        if (array_key_exists('assignee_id', $changes) && $assignee && ! $assignee->is($actor)) {
            $assignee->notify(new TicketNotification(
                __(':ref assigned to you: :subject', ['ref' => $ticket->reference, 'subject' => $ticket->subject]),
                __(':priority priority · due :due', ['priority' => $ticket->priority->getLabel(), 'due' => $ticket->due_at?->format('j M, H:i')]),
                TicketLinks::staff($ticket),
            ));
        }
    }
}
