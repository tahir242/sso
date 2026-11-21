<?php

namespace App\Domain\Access\Notifications;

use App\Domain\Access\Models\ApprovalStep;
use App\Domain\Identity\Models\User;
use App\Domain\Notifications\BaseNotification;
use App\Domain\Notifications\NotificationCategory;

class ApprovalOverdue extends BaseNotification
{
    public function __construct(public readonly ApprovalStep $step) {}

    public function category(): NotificationCategory
    {
        return NotificationCategory::Access;
    }

    public function title(User $notifiable): string
    {
        return __('Overdue approval: :name → :app', [
            'name' => $this->step->request->user->name,
            'app' => $this->step->request->application->name,
        ]);
    }

    public function body(User $notifiable): string
    {
        return __('The :role approval was due :due and is still waiting. :who', [
            'role' => mb_strtolower($this->step->type->getLabel()),
            'due' => $this->step->due_at?->diffForHumans(),
            'who' => $this->step->approver
                ? __('Assigned to :approver.', ['approver' => $this->step->approver->name])
                : __('Any administrator can decide it.'),
        ]);
    }

    public function url(User $notifiable): string
    {
        return route('approvals');
    }
}
