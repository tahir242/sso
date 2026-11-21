<?php

namespace App\Domain\Notifications;

use App\Domain\Identity\Models\User;

/**
 * "Did this reach me?" — sent on every channel the user can receive.
 */
class TestNotification extends BaseNotification
{
    public function category(): NotificationCategory
    {
        return NotificationCategory::Access;
    }

    public function via(User $notifiable): array
    {
        return ['database', ...array_map(fn (Channel $channel) => $channel->driver(), $notifiable->availableChannels())];
    }

    public function title(User $notifiable): string
    {
        return __('Test notification from single sign-on');
    }

    public function body(User $notifiable): string
    {
        return __('If you can read this, notifications reach you here.');
    }

    public function url(User $notifiable): string
    {
        return route('account.notifications');
    }
}
