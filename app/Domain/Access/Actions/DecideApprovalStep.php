<?php

namespace App\Domain\Access\Actions;

use App\Domain\Access\Enums\AccessRequestStatus;
use App\Domain\Access\Enums\ApprovalStepStatus;
use App\Domain\Access\Models\ApprovalStep;
use App\Domain\Identity\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * An approver approves or rejects the current step of a request.
 */
final readonly class DecideApprovalStep
{
    public function __construct(private AdvanceAccessRequest $advance) {}

    /**
     * @throws AuthorizationException|ValidationException
     */
    public function handle(ApprovalStep $step, User $approver, bool $approve, ?string $comment = null): void
    {
        $comment = trim((string) $comment) ?: null;

        if (! $step->canBeDecidedBy($approver)) {
            throw new AuthorizationException(__('You cannot decide this request.'));
        }

        if (! $approve && $comment === null) {
            throw ValidationException::withMessages(['comment' => __('Please give a reason so the requester knows what to do next.')]);
        }

        DB::transaction(function () use ($step, $approver, $approve, $comment) {
            $step->forceFill([
                'status' => $approve ? ApprovalStepStatus::Approved : ApprovalStepStatus::Rejected,
                'decided_by' => $approver->getKey(),
                'comment' => $comment,
                'decided_at' => now(),
            ])->save();

            $request = $step->request;

            if ($approve) {
                $this->advance->handle($request);

                return;
            }

            $request->steps()->where('status', ApprovalStepStatus::Waiting)->update(['status' => ApprovalStepStatus::Skipped]);
            $request->forceFill(['status' => AccessRequestStatus::Rejected, 'decided_at' => now()])->save();

            $this->advance->notifyOutcome($request);
        });
    }
}
