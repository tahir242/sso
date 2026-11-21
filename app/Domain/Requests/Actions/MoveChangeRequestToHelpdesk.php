<?php

namespace App\Domain\Requests\Actions;

use App\Domain\Helpdesk\Actions\OpenTicket;
use App\Domain\Helpdesk\Enums\TicketPriority;
use App\Domain\Helpdesk\Models\Ticket;
use App\Domain\Identity\Models\User;
use App\Domain\Requests\Enums\ChangeRequestStatus;
use App\Domain\Requests\Models\ChangeRequest;
use App\Domain\Requests\Notifications\RequestNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * A "suggestion" that is really a problem (something not working) is passed
 * to the IT helpdesk: a ticket is raised for the requester with the same
 * details and files, and the suggestion is closed with a pointer to it.
 */
final class MoveChangeRequestToHelpdesk
{
    public function __construct(private OpenTicket $open) {}

    /** The application owner, super-admins and the helpdesk (who triage these). */
    public static function canMove(ChangeRequest $request, User $user): bool
    {
        return ! $request->status->isClosed() && $request->requester !== null
            && (UpdateChangeRequest::canManage($request, $user) || $user->hasRole('helpdesk'));
    }

    public function handle(ChangeRequest $request, User $by): Ticket
    {
        if (! self::canMove($request, $by)) {
            throw new AuthorizationException(__('This suggestion cannot be moved to the helpdesk.'));
        }

        return DB::transaction(function () use ($request, $by): Ticket {
            $ticket = $this->open->handle(
                $request->requester,
                $request->application,
                null,
                $request->title,
                $request->description,
                TicketPriority::tryFrom($request->priority->value) ?? TicketPriority::Normal,
            );

            foreach ($request->getMedia('attachments') as $file) {
                $file->copy($ticket, 'attachments');
            }

            $request->update([
                'status' => ChangeRequestStatus::Declined,
                'owner_comment' => __('This is a problem rather than an improvement, so it was passed to the IT helpdesk as ticket :ref. Follow it under My requests → IT help.', ['ref' => $ticket->reference]),
                'decided_by' => $by->getKey(),
                'decided_at' => now(),
            ]);

            $request->requester->notify(new RequestNotification(
                __('Passed to the IT helpdesk: :ref', ['ref' => $ticket->reference]),
                '“'.$request->title.'” — '.__('the helpdesk will reply on the ticket.'),
                route('support.show', $ticket->ulid),
            ));

            return $ticket;
        });
    }
}
