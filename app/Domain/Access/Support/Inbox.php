<?php

namespace App\Domain\Access\Support;

use App\Domain\Access\Enums\AccessRequestStatus;
use App\Domain\Access\Models\AccessRequest;
use App\Domain\Access\Models\AccessReviewItem;
use App\Domain\Access\Models\ApprovalStep;
use App\Domain\Federation\Models\Application;
use App\Domain\Helpdesk\Enums\TicketStatus;
use App\Domain\Helpdesk\Models\Ticket;
use App\Domain\Identity\Models\Department;
use App\Domain\Identity\Models\User;
use App\Domain\Requests\Enums\ChangeRequestStatus;
use App\Domain\Requests\Models\ChangeRequest;
use App\Livewire\Access\NewStaffApprovals;

/**
 * What a portal user has asked for and what is waiting on them: the
 * navigation badges, the home page and the "My requests" tabs.
 */
final class Inbox
{
    /**
     * Whether the user approves anything at all (head of department,
     * application owner, named approver or reviewer, approver role). Only
     * approvers see "Approvals".
     */
    public static function isApprover(User $user): bool
    {
        return $user->hasAnyRole(['super-admin', 'it-security'])
            || Department::where('hod_user_id', $user->getKey())->exists()
            || Application::where('owner_user_id', $user->getKey())->exists()
            || ApprovalStep::where('approver_id', $user->getKey())->exists()
            || AccessReviewItem::where('reviewer_id', $user->getKey())->exists();
    }

    /** Access requests, access reviews and new staff waiting for this user's decision. */
    public static function approvalsWaiting(User $user): int
    {
        return ApprovalStep::query()->awaiting($user)->count()
            + AccessReviewItem::query()->awaiting($user)->count()
            + NewStaffApprovals::awaiting($user)->count();
    }

    /** Help tickets where the helpdesk is waiting for the user's answer. */
    public static function ticketsWaiting(User $user): int
    {
        return Ticket::where('requester_id', $user->getKey())->where('status', TicketStatus::Waiting)->count();
    }

    /**
     * Open items per "My requests" tab.
     *
     * @return array{access: int, help: int, suggestions: int}
     */
    public static function openCounts(User $user): array
    {
        return [
            'access' => AccessRequest::query()
                ->where(fn ($q) => $q->where('user_id', $user->getKey())->orWhere('requested_by', $user->getKey()))
                ->where('status', AccessRequestStatus::Pending)->count(),
            'help' => Ticket::where('requester_id', $user->getKey())->whereIn('status', TicketStatus::active())->count(),
            'suggestions' => ChangeRequest::where('requested_by', $user->getKey())
                ->whereNotIn('status', [ChangeRequestStatus::Done, ChangeRequestStatus::Declined])->count(),
        ];
    }
}
