<?php

namespace App\Domain\Audit\Notifications;

use App\Domain\Identity\Models\User;
use App\Domain\Notifications\BaseNotification;
use App\Domain\Notifications\NotificationCategory;

/**
 * Something security-relevant happened on the recipient's own account.
 */
class AccountSecurityAlert extends BaseNotification
{
    public function __construct(
        public readonly string $heading,
        public readonly string $message,
    ) {}

    public function category(): NotificationCategory
    {
        return NotificationCategory::Security;
    }

    public function title(User $notifiable): string
    {
        return $this->heading;
    }

    public function body(User $notifiable): string
    {
        return $this->message.' '.__('If this wasn’t you, change your password and contact the IT helpdesk.');
    }

    public function url(User $notifiable): string
    {
        return route('account.security');
    }
}
