<?php

namespace App\Domain\Access\Notifications;

use App\Domain\Access\Models\AccessReviewCampaign;
use App\Domain\Identity\Models\User;
use App\Domain\Notifications\BaseNotification;
use App\Domain\Notifications\NotificationCategory;

class AccessReviewReminder extends BaseNotification
{
    public function __construct(
        public readonly AccessReviewCampaign $campaign,
        public readonly int $remaining,
    ) {}

    public function category(): NotificationCategory
    {
        return NotificationCategory::Access;
    }

    public function title(User $notifiable): string
    {
        return __('Reminder: access review closes :when', ['when' => $this->campaign->due_at->diffForHumans()]);
    }

    public function body(User $notifiable): string
    {
        $outcome = $this->campaign->auto_revoke
            ? __('Access you have not confirmed will be removed when it closes.')
            : __('Unconfirmed access will be reported as not reviewed.');

        return trans_choice(':count person still needs a decision.|:count people still need a decision.', $this->remaining).' '.$outcome;
    }

    public function url(User $notifiable): string
    {
        return route('approvals.reviews');
    }
}
