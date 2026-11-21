<?php

namespace App\Domain\Helpdesk\Actions;

use App\Domain\Helpdesk\Enums\CommentKind;
use App\Domain\Helpdesk\Enums\TicketStatus;
use App\Domain\Helpdesk\Models\Ticket;
use App\Domain\Helpdesk\Models\TicketComment;
use App\Domain\Helpdesk\Notifications\TicketNotification;
use App\Domain\Helpdesk\Support\Helpdesk;
use App\Domain\Identity\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Adds a reply (seen by the requester) or an internal note (staff only).
 *
 * - The first staff reply stops the first-response clock.
 * - A requester reply on a ticket waiting for them, or a resolved one,
 *   puts it back in the queue.
 * - A staff reply can move the ticket to another status in one step.
 */
final class ReplyToTicket
{
    /**
     * @param  array<int, UploadedFile>  $attachments
     */
    public function handle(Ticket $ticket, User $author, string $body, bool $internal = false, ?TicketStatus $status = null, array $attachments = []): TicketComment
    {
        $isStaff = Helpdesk::canHandle($ticket, $author);
        $isRequester = $ticket->requester_id === $author->getKey();

        if (! $isStaff && ! $isRequester) {
            throw new AuthorizationException(__('You cannot reply to this ticket.'));
        }
        if ($ticket->status === TicketStatus::Closed) {
            throw ValidationException::withMessages(['body' => __('This ticket is closed. Please raise a new one.')]);
        }
        if (trim($body) === '') {
            throw ValidationException::withMessages(['body' => __('Please write a message.')]);
        }

        $internal = $internal && $isStaff;
        $staffReply = $isStaff && ! $isRequester;

        $comment = DB::transaction(function () use ($ticket, $author, $body, $internal, $staffReply, $status) {
            $comment = $ticket->comments()->create([
                'user_id' => $author->getKey(),
                'kind' => $internal ? CommentKind::Note : CommentKind::Reply,
                'body' => trim($body),
            ]);

            $changes = ['last_activity_at' => now()];

            if ($staffReply && ! $internal && ! $ticket->first_responded_at) {
                $changes['first_responded_at'] = now();
            }

            if (! $staffReply && in_array($ticket->status, [TicketStatus::Waiting, TicketStatus::Resolved], true)) {
                $changes['status'] = TicketStatus::Open;
                $changes['resolved_at'] = null;
            } elseif ($staffReply && $status && $status !== $ticket->status) {
                $changes += ChangeTicketStatus::timestampsFor($status);
                $changes['status'] = $status;
            } elseif ($staffReply && ! $internal && $ticket->status === TicketStatus::Open) {
                $changes['status'] = TicketStatus::InProgress;
            }

            $ticket->update($changes);

            return $comment;
        });

        foreach ($attachments as $file) {
            $ticket->addMedia($file)->usingName($file->getClientOriginalName())
                ->withCustomProperties(['comment_id' => $comment->getKey()])
                ->toMediaCollection('attachments');
        }

        $this->notify($ticket->fresh(['assignee', 'requester']), $author, $comment, $staffReply);

        return $comment;
    }

    private function notify(Ticket $ticket, User $author, TicketComment $comment, bool $staffReply): void
    {
        $excerpt = Str::limit($comment->body, 160);

        if ($comment->kind === CommentKind::Note) {
            Notification::send(Helpdesk::watchers($ticket)->reject(fn (User $u) => $u->is($author)), new TicketNotification(
                __('Note on :ref: :subject', ['ref' => $ticket->reference, 'subject' => $ticket->subject]),
                __(':name: :text', ['name' => $author->name, 'text' => $excerpt]),
                TicketLinks::staff($ticket),
            ));

            return;
        }

        if ($staffReply) {
            $ticket->requester->notify(new TicketNotification(
                $ticket->status === TicketStatus::Resolved
                    ? __(':ref resolved: :subject', ['ref' => $ticket->reference, 'subject' => $ticket->subject])
                    : __('Reply on :ref: :subject', ['ref' => $ticket->reference, 'subject' => $ticket->subject]),
                __(':name: :text', ['name' => $author->name, 'text' => $excerpt]),
                TicketLinks::requester($ticket),
            ));

            return;
        }

        Notification::send(Helpdesk::watchers($ticket)->reject(fn (User $u) => $u->is($author)), new TicketNotification(
            __(':name replied on :ref', ['name' => $author->name, 'ref' => $ticket->reference]),
            $excerpt,
            TicketLinks::staff($ticket),
        ));
    }
}
