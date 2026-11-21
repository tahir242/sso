<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('general.site_name', 'SSO');
        $this->migrator->add('general.organization_name', 'Your Organization');
        $this->migrator->add('general.tagline', 'One account for every application');
        $this->migrator->add('general.support_email', null);
        $this->migrator->add('general.support_phone', null);
        $this->migrator->add('general.timezone', 'UTC');
        $this->migrator->add('general.login_greetings_enabled', true);
        $this->migrator->add('general.hijri_offset_days', 0);

        $this->migrator->add('security.password_min_length', 12);
        $this->migrator->add('security.lockout_max_attempts', 5);
        $this->migrator->add('security.lockout_decay_minutes', 15);
        $this->migrator->add('security.ip_max_attempts', 30);

        $this->migrator->add('mfa.required_for_admins', true);
        $this->migrator->add('mfa.required_off_network', true);
        $this->migrator->add('mfa.trusted_networks', ['10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16', '127.0.0.1/32', '::1/128']);
        $this->migrator->add('mfa.trusted_device_days', 30);
        $this->migrator->add('mfa.issuer', 'SSO');

        $this->migrator->add('ldap.enabled', false);
        $this->migrator->add('ldap.hosts', []);
        $this->migrator->add('ldap.port', 636);
        $this->migrator->add('ldap.encryption', 'ssl');
        $this->migrator->add('ldap.base_dn', '');
        $this->migrator->add('ldap.bind_username', null);
        $this->migrator->addEncrypted('ldap.bind_password', null);
        $this->migrator->add('ldap.timeout', 5);
        $this->migrator->add('ldap.verify_certificate', true);
        $this->migrator->add('ldap.auto_create_users', true);
        $this->migrator->add('ldap.user_filter', null);

        $this->migrator->add('session.idle_timeout_minutes', 30);
        $this->migrator->add('session.absolute_timeout_hours', 10);
        $this->migrator->add('session.access_token_minutes', 15);
        $this->migrator->add('session.refresh_token_hours', 10);

        $this->migrator->add('mail.mailer', 'log');
        $this->migrator->add('mail.host', null);
        $this->migrator->add('mail.port', 587);
        $this->migrator->add('mail.scheme', null);
        $this->migrator->add('mail.username', null);
        $this->migrator->addEncrypted('mail.password', null);
        $this->migrator->add('mail.from_address', 'no-reply@example.com');
        $this->migrator->add('mail.from_name', 'SSO');
    }
};
