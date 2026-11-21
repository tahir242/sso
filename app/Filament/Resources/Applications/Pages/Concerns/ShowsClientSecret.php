<?php

namespace App\Filament\Resources\Applications\Pages\Concerns;

use App\Domain\Federation\Models\Application;
use Filament\Notifications\Notification;

trait ShowsClientSecret
{
    /**
     * The secret is stored hashed, so this is the only time it can be seen.
     */
    protected function notifyClientSecret(Application $application, string $secret, string $title): void
    {
        Notification::make()
            ->title($title)
            ->body("Client ID: {$application->oauth_client_id}\n\nClient secret: {$secret}\n\nCopy the secret now and give it to the application’s developers securely. It will not be shown again.")
            ->warning()
            ->persistent()
            ->send();
    }
}
