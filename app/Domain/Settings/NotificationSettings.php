<?php

namespace App\Domain\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Delivery channels beyond email and in-app: WhatsApp (Green-API) and
 * browser push (VAPID). Secrets are stored encrypted.
 */
class NotificationSettings extends Settings
{
    public bool $whatsapp_enabled;

    /** e.g. https://api.green-api.com (or the host Green-API assigns to the instance) */
    public string $green_api_url;

    public ?string $green_api_instance_id;

    public ?string $green_api_token;

    /** Used for local mobile numbers such as 0300 1234567. */
    public string $default_country_code;

    public bool $webpush_enabled;

    public ?string $vapid_public_key;

    public ?string $vapid_private_key;

    public static function group(): string
    {
        return 'notifications';
    }

    public static function encrypted(): array
    {
        return ['green_api_token', 'vapid_private_key'];
    }

    public function whatsappReady(): bool
    {
        return $this->whatsapp_enabled && $this->green_api_instance_id && $this->green_api_token;
    }

    public function webpushReady(): bool
    {
        return $this->webpush_enabled && $this->vapid_public_key && $this->vapid_private_key;
    }
}
