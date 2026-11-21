<?php

namespace App\Domain\Access\Notifications;

use App\Domain\Access\Enums\AccessRequestStatus;
use App\Domain\Access\Enums\ApprovalStepStatus;
use App\Domain\Access\Models\AccessRequest;
use App\Domain\Identity\Models\User;
use App\Domain\Notifications\BaseNotification;
use App\Domain\Notifications\NotificationCategory;

class AccessRequestDecided extends BaseNotification
{
    public function __construct(public readonly AccessRequest $request) {}

    public function category(): NotificationCategory
    {
        return NotificationCategory::Access;
    }

    public function title(User $notifiable): string
    {
        return $this->approved()
            ? __('Access granted: :app', ['app' => $this->request->application->name])
            : __('Access request not approved: :app', ['app' => $this->request->application->name]);
    }

    public function body(User $notifiable): string
    {
        return $this->approved()
            ? __('You can now open :app from My applications.', ['app' => $this->request->application->name])
            : __('Reason: :reason', ['reason' => (string) $this->request->steps()->where('status', ApprovalStepStatus::Rejected)->value('comment')]);
    }

    public function url(User $notifiable): string
    {
        return $this->approved() ? route('home') : route('access.requests');
    }

    private function approved(): bool
    {
        return $this->request->status === AccessRequestStatus::Approved;
    }
}
