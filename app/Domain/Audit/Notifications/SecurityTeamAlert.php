<?php

namespace App\Domain\Audit\Notifications;

use App\Domain\Identity\Models\User;
use App\Domain\Notifications\BaseNotification;
use App\Domain\Notifications\NotificationCategory;

/**
 * Alert for the IT security team (it-security and super-admin roles).
 */
class SecurityTeamAlert extends BaseNotification
{
    public function __construct(
        public readonly string $heading,
        public readonly string $message,
    ) {}

    public function category(): NotificationCategory
    {
        return NotificationCategory::SecurityTeam;
    }

    public function title(User $notifiable): string
    {
        return '[Security] '.$this->heading;
    }

    public function body(User $notifiable): string
    {
        return $this->message;
    }

    public function url(User $notifiable): string
    {
        return url('/admin/audit/sign-in-activity');
    }
}
