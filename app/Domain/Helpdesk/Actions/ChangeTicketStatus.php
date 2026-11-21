<?php

namespace App\Domain\Helpdesk\Actions;

use App\Domain\Helpdesk\Enums\CommentKind;
use App\Domain\Helpdesk\Enums\TicketStatus;
use App\Domain\Helpdesk\Models\Ticket;
use App\Domain\Helpdesk\Notifications\TicketNotification;
use App\Domain\Helpdesk\Support\Helpdesk;
use App\Domain\Identity\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Moves a ticket to another status and records it in the thread.
 *
 * Staff may set any status. The requester may only close their ticket
 * ("my problem is solved") or reopen a resolved one.
 */
final class ChangeTicketStatus
{
    public function handle(Ticket $ticket, User $actor, TicketStatus $status): void
    {
        if ($status === $ticket->status) {
            return;
        }

        if (! self::allowed($ticket, $actor, $status)) {
            throw new AuthorizationException(__('You cannot change this ticket to :status.', ['status' => $status->getLabel()]));
        }

        DB::transaction(function () use ($ticket, $actor, $status) {
            $ticket->update([...self::timestampsFor($status), 'status' => $status, 'last_activity_at' => now()]);

            $ticket->comments()->create([
                'user_id' => $actor->getKey(),
                'kind' => CommentKind::Event,
                'body' => __('Status changed to :status', ['status' => $status->staffLabel()]),
            ]);
        });

        $ticket->load(['requester', 'assignee']);

        if ($actor->is($ticket->requester)) {
            Notification::send(Helpdesk::watchers($ticket)->reject(fn (User $u) => $u->is($actor)), new TicketNotification(
                __(':ref :status by :name', ['ref' => $ticket->reference, 'status' => mb_strtolower($status->getLabel()), 'name' => $actor->name]),
                $ticket->subject,
                TicketLinks::staff($ticket),
            ));
        } elseif (in_array($status, [TicketStatus::Resolved, TicketStatus::Waiting, TicketStatus::Closed], true)) {
            $ticket->requester->notify(new TicketNotification(
                __(':ref: :status', ['ref' => $ticket->reference, 'status' => $status->getLabel()]),
                $status === TicketStatus::Resolved
                    ? __('“:subject” was marked resolved. If the problem is not fixed, reply and it will be reopened.', ['subject' => $ticket->subject])
                    : $ticket->subject,
                TicketLinks::requester($ticket),
            ));
        }
    }

    public static function allowed(Ticket $ticket, User $actor, TicketStatus $status): bool
    {
        if (Helpdesk::canHandle($ticket, $actor)) {
            return true;
        }

        if ($ticket->requester_id !== $actor->getKey()) {
            return false;
        }

        return match ($status) {
            TicketStatus::Closed => $ticket->status !== TicketStatus::Closed,
            TicketStatus::Open => $ticket->status === TicketStatus::Resolved,
            default => false,
        };
    }

    /**
     * Timestamps that go with entering a status.
     *
     * @return array<string, mixed>
     */
    public static function timestampsFor(TicketStatus $status): array
    {
        return match ($status) {
            TicketStatus::Resolved => ['resolved_at' => now(), 'closed_at' => null],
            TicketStatus::Closed => ['closed_at' => now()],
            default => ['resolved_at' => null, 'closed_at' => null],
        };
    }
}
