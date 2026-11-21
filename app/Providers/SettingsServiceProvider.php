<?php

namespace App\Providers;

use App\Domain\Settings\GeneralSettings;
use App\Domain\Settings\MailSettings;
use App\Domain\Settings\NotificationSettings;
use App\Domain\Settings\SessionSettings;
use Error;
use Illuminate\Database\QueryException;
use Illuminate\Support\ServiceProvider;
use Spatie\LaravelSettings\Exceptions\MissingSettings;

/**
 * Applies database-stored settings on top of the static config files, so
 * runtime behaviour is controlled from the admin console instead of .env.
 *
 * .env only carries bootstrap values (APP_KEY, APP_URL, DB connection).
 */
class SettingsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        try {
            $this->applyGeneral($this->app->make(GeneralSettings::class));
            $this->applySession($this->app->make(SessionSettings::class));
            $this->applyMail($this->app->make(MailSettings::class));
            $this->applyWebPush($this->app->make(NotificationSettings::class));
        } catch (MissingSettings|QueryException|Error) {
            // Fresh install, migrations not yet run, or a stale settings cache
            // during a deploy (new property): keep config defaults.
        }
    }

    private function applyGeneral(GeneralSettings $settings): void
    {
        config([
            'app.name' => $settings->site_name,
            'app.timezone' => $settings->timezone,
        ]);

        date_default_timezone_set($settings->timezone);
    }

    private function applySession(SessionSettings $settings): void
    {
        // Laravel's session lifetime is the idle timeout; the absolute limit is
        // enforced separately by middleware (Phase 1).
        config(['session.lifetime' => $settings->idle_timeout_minutes]);
    }

    private function applyMail(MailSettings $settings): void
    {
        config([
            'mail.default' => $settings->mailer,
            'mail.mailers.smtp.host' => $settings->host,
            'mail.mailers.smtp.port' => $settings->port,
            'mail.mailers.smtp.scheme' => $settings->scheme,
            'mail.mailers.smtp.username' => $settings->username,
            'mail.mailers.smtp.password' => $settings->password,
            'mail.from.address' => $settings->from_address,
            'mail.from.name' => $settings->from_name,
        ]);
    }

    private function applyWebPush(NotificationSettings $settings): void
    {
        config([
            'webpush.vapid.public_key' => $settings->vapid_public_key,
            'webpush.vapid.private_key' => $settings->vapid_private_key,
            'webpush.vapid.subject' => config('app.url'),
        ]);
    }
}
