<?php

namespace App\Domain\Access\Actions;

use App\Domain\Access\Enums\ApprovalStepStatus;
use App\Domain\Access\Models\ApprovalStep;
use App\Domain\Access\Notifications\ApprovalOverdue;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

/**
 * Steps past their SLA: remind the approver and alert the super-admins
 * (once per step).
 */
final class EscalateOverdueApprovals
{
    public function handle(): int
    {
        $steps = ApprovalStep::query()
            ->with('request.application', 'request.user', 'approver')
            ->where('status', ApprovalStepStatus::Pending)
            ->whereNull('escalated_at')
            ->where('due_at', '<', now())
            ->get();

        $admins = Role::where('name', 'super-admin')->exists() ? User::role('super-admin')->get() : collect();

        foreach ($steps as $step) {
            $recipients = $step->notifiables()->merge($admins)->unique('id')
                ->reject(fn (User $user) => $user->getKey() === $step->request->user_id);

            Notification::send($recipients, new ApprovalOverdue($step));

            $step->forceFill(['escalated_at' => now()])->save();
        }

        return $steps->count();
    }
}
