<?php

namespace App\Domain\Access\Actions;

use App\Domain\Access\Enums\AccessRequestStatus;
use App\Domain\Access\Enums\ApprovalStepStatus;
use App\Domain\Access\Models\AccessRequest;
use App\Domain\Identity\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * The requester (or the person it was for) withdraws a pending request.
 */
final class CancelAccessRequest
{
    public function handle(AccessRequest $request, User $user): void
    {
        if (! $request->status->isOpen() || ! in_array($user->getKey(), [$request->user_id, $request->requested_by], true)) {
            throw new AuthorizationException(__('This request cannot be cancelled.'));
        }

        $request->steps()->whereIn('status', [ApprovalStepStatus::Waiting, ApprovalStepStatus::Pending])
            ->update(['status' => ApprovalStepStatus::Skipped]);

        $request->forceFill(['status' => AccessRequestStatus::Cancelled, 'decided_at' => now()])->save();
    }
}
