<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('notifications.whatsapp_enabled', false);
        $this->migrator->add('notifications.green_api_url', 'https://api.green-api.com');
        $this->migrator->add('notifications.green_api_instance_id', null);
        $this->migrator->addEncrypted('notifications.green_api_token', null);
        $this->migrator->add('notifications.default_country_code', '92');
        $this->migrator->add('notifications.webpush_enabled', false);
        $this->migrator->add('notifications.vapid_public_key', null);
        $this->migrator->addEncrypted('notifications.vapid_private_key', null);
    }
};
