<?php

namespace App\Domain\Access\Notifications;

use App\Domain\Access\Models\AccessReviewCampaign;
use App\Domain\Identity\Models\User;
use App\Domain\Notifications\BaseNotification;
use App\Domain\Notifications\NotificationCategory;

class AccessReviewAssigned extends BaseNotification
{
    public function __construct(
        public readonly AccessReviewCampaign $campaign,
        public readonly int $count,
    ) {}

    public function category(): NotificationCategory
    {
        return NotificationCategory::Access;
    }

    public function title(User $notifiable): string
    {
        return __('Access review: :name', ['name' => $this->campaign->name]);
    }

    public function body(User $notifiable): string
    {
        return trans_choice(
            'Please confirm whether :count person should keep access to your application by :due.|Please confirm whether :count people should keep access to your applications by :due.',
            $this->count,
            ['due' => $this->campaign->due_at->format('D j M')],
        );
    }

    public function url(User $notifiable): string
    {
        return route('approvals.reviews');
    }
}
