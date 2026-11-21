<?php

namespace App\Domain\Helpdesk\Notifications;

use App\Domain\Identity\Models\User;
use App\Domain\Notifications\BaseNotification;
use App\Domain\Notifications\NotificationCategory;

/**
 * Ticket activity: one title, one message, one link.
 */
class TicketNotification extends BaseNotification
{
    public function __construct(
        public readonly string $heading,
        public readonly string $message,
        public readonly string $link,
    ) {}

    public function category(): NotificationCategory
    {
        return NotificationCategory::Helpdesk;
    }

    public function title(User $notifiable): string
    {
        return $this->heading;
    }

    public function body(User $notifiable): string
    {
        return $this->message;
    }

    public function url(User $notifiable): string
    {
        return $this->link;
    }
}
