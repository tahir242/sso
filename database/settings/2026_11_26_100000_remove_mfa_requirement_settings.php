<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * Two-step verification is the user's own choice: drop the policy settings
 * that forced it on (administrators, sign-in from outside the network).
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->delete('mfa.required_for_admins');
        $this->migrator->delete('mfa.required_off_network');
        $this->migrator->delete('mfa.trusted_networks');
    }

    public function down(): void
    {
        $this->migrator->add('mfa.required_for_admins', true);
        $this->migrator->add('mfa.required_off_network', true);
        $this->migrator->add('mfa.trusted_networks', ['10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16', '127.0.0.1/32', '::1/128']);
    }
};
