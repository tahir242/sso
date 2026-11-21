<?php

namespace App\Domain\Notifications\Channels;

use App\Domain\Notifications\GreenApi;
use Illuminate\Notifications\Notification;

/**
 * Laravel notification channel for WhatsApp via Green-API. The notifiable
 * provides its number through routeNotificationForWhatsApp(); the
 * notification provides the text through toWhatsApp().
 */
class WhatsAppChannel
{
    public function __construct(private readonly GreenApi $greenApi) {}

    public function send(object $notifiable, Notification $notification): void
    {
        $mobile = $notifiable->routeNotificationFor('whatsapp', $notification);

        if (! $mobile || ! $this->greenApi->ready() || ! method_exists($notification, 'toWhatsApp')) {
            return;
        }

        $this->greenApi->send($mobile, $notification->toWhatsApp($notifiable));
    }
}
