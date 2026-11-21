<?php

namespace App\Domain\Access;

use App\Domain\Access\Enums\ApprovalStepStatus;
use App\Domain\Access\Enums\ApprovalStepType;
use App\Domain\Access\Models\AccessRequest;

/**
 * Builds the approval chain for a request from the application's policy:
 *
 *   1. Head of department of the person getting access (unless skipped)
 *   2. Application owner
 *   3. IT security (only if the application requires it)
 *
 * A named approver is never the person getting access: such steps fall back
 * to the step's role (super-admins / IT security).
 */
final class ApprovalRouter
{
    public function createSteps(AccessRequest $request): void
    {
        $application = $request->application;
        $policy = $application->approval_policy ?? [];
        $subject = $request->user;
        $sequence = 0;

        if (! ($policy['skip_hod'] ?? false)) {
            $hodId = $subject->department?->hod_user_id;

            match (true) {
                $hodId === $subject->getKey() => $this->step($request, ++$sequence, ApprovalStepType::HeadOfDepartment, null,
                    ApprovalStepStatus::Skipped, 'The person is the head of their department.'),
                $hodId === null => $this->step($request, ++$sequence, ApprovalStepType::HeadOfDepartment, null,
                    comment: 'No head of department on record; any administrator can approve.'),
                default => $this->step($request, ++$sequence, ApprovalStepType::HeadOfDepartment, $hodId),
            };
        }

        $ownerId = $application->owner_user_id;
        $this->step($request, ++$sequence, ApprovalStepType::ApplicationOwner, $ownerId === $subject->getKey() ? null : $ownerId);

        if ($policy['require_it_security'] ?? false) {
            $this->step($request, ++$sequence, ApprovalStepType::ItSecurity, null);
        }
    }

    private function step(AccessRequest $request, int $sequence, ApprovalStepType $type, ?int $approverId, ApprovalStepStatus $status = ApprovalStepStatus::Waiting, ?string $comment = null): void
    {
        $request->steps()->create([
            'sequence' => $sequence,
            'type' => $type,
            'approver_id' => $approverId,
            'status' => $status,
            'comment' => $comment,
        ]);
    }
}
