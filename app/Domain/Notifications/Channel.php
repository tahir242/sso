<?php

namespace App\Domain\Notifications;

use App\Domain\Notifications\Channels\WhatsAppChannel;
use NotificationChannels\WebPush\WebPushChannel;

/**
 * Optional delivery channels a user can choose per category. In-app
 * notifications (the bell) are always on.
 */
enum Channel: string
{
    case Mail = 'mail';
    case WhatsApp = 'whatsapp';
    case WebPush = 'webpush';

    public function label(): string
    {
        return match ($this) {
            self::Mail => 'Email',
            self::WhatsApp => 'WhatsApp',
            self::WebPush => 'Browser',
        };
    }

    /**
     * Laravel notification channel name / class.
     */
    public function driver(): string
    {
        return match ($this) {
            self::Mail => 'mail',
            self::WhatsApp => WhatsAppChannel::class,
            self::WebPush => WebPushChannel::class,
        };
    }
}
