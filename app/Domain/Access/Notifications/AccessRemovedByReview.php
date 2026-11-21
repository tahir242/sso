<?php

namespace App\Domain\Access\Notifications;

use App\Domain\Access\Models\AccessReviewItem;
use App\Domain\Identity\Models\User;
use App\Domain\Notifications\BaseNotification;
use App\Domain\Notifications\NotificationCategory;

/**
 * Tells a person their access was removed in an access review, and how to
 * ask for it again if it is still needed.
 */
class AccessRemovedByReview extends BaseNotification
{
    public function __construct(public readonly AccessReviewItem $item) {}

    public function category(): NotificationCategory
    {
        return NotificationCategory::Access;
    }

    public function title(User $notifiable): string
    {
        return __('Access removed: :app', ['app' => $this->item->application->name]);
    }

    public function body(User $notifiable): string
    {
        return __('Your access to :app was removed in a periodic access review:reason If you still need it, request access again.', [
            'app' => $this->item->application->name,
            'reason' => $this->item->comment ? ' ('.$this->item->comment.').' : '.',
        ]);
    }

    public function url(User $notifiable): string
    {
        return route('access.request', ['app' => $this->item->application->slug]);
    }
}
