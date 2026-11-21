<?php

namespace App\Domain\Access\Actions;

use App\Domain\Access\Enums\AccessRequestStatus;
use App\Domain\Access\Enums\ApprovalStepStatus;
use App\Domain\Access\Models\AccessRequest;
use App\Domain\Access\Notifications\AccessRequestDecided;
use App\Domain\Access\Notifications\ApprovalNeeded;
use App\Domain\Federation\Actions\GrantApplicationAccess;
use App\Domain\Settings\AccessSettings;
use Illuminate\Support\Facades\Notification;

/**
 * Moves a request forward: activates the next waiting step (and notifies its
 * approvers), or — when every step is done — grants the access.
 */
final readonly class AdvanceAccessRequest
{
    public function __construct(
        private GrantApplicationAccess $grant,
        private AccessSettings $settings,
    ) {}

    public function handle(AccessRequest $request): void
    {
        if ($request->status !== AccessRequestStatus::Pending) {
            return;
        }

        // steps() is already ordered by sequence (SQL Server rejects ordering by it twice).
        $next = $request->steps()->where('status', ApprovalStepStatus::Waiting)->first();

        if ($next) {
            $next->forceFill([
                'status' => ApprovalStepStatus::Pending,
                'activated_at' => now(),
                'due_at' => now()->addHours($this->settings->approval_sla_hours),
            ])->save();

            Notification::send($next->notifiables(), new ApprovalNeeded($next));

            return;
        }

        $this->grant->handle(
            $request->application,
            $request->user,
            $request->role_ids ?? [],
            $request->access_until?->endOfDay()->startOfSecond(), // whole seconds: SQL Server datetime would round .999999 up to the next day
            // The final approver is recorded as the one who granted access.
            $request->steps()->reorder()->whereNotNull('decided_by')->orderByDesc('sequence')->first()?->decider,
        );

        $request->forceFill(['status' => AccessRequestStatus::Approved, 'decided_at' => now()])->save();

        $this->notifyOutcome($request);
    }

    public function notifyOutcome(AccessRequest $request): void
    {
        $people = collect([$request->user, $request->requester])->filter()->unique('id');

        Notification::send($people, new AccessRequestDecided($request));
    }
}
