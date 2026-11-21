<?php

namespace App\Filament\Resources\Users\Pages\Concerns;

use App\Domain\Identity\Actions\ImportDirectoryUser;
use App\Domain\Identity\Directory\ActiveDirectory;
use App\Domain\Identity\Models\User;
use Filament\Notifications\Notification;
use LogicException;
use Throwable;

/**
 * Brings an Active Directory account's SSO details in line with AD (name,
 * email, employee ID, department, job title, mobile, link to the AD
 * account), and tells the administrator what happened.
 */
trait SyncsFromDirectory
{
    protected function syncFromDirectory(User $user, string $success = 'Details updated from Active Directory'): bool
    {
        try {
            $person = app(ActiveDirectory::class)->findUser($user->username);
        } catch (Throwable $e) {
            Notification::make()->title('Could not reach Active Directory')->body(ActiveDirectory::explain($e->getMessage()))->danger()->persistent()->send();

            return false;
        }

        if (! $person) {
            Notification::make()->title('Not found in Active Directory')
                ->body("No Active Directory account has the username “{$user->username}”.")->danger()->persistent()->send();

            return false;
        }

        try {
            ImportDirectoryUser::applyTo($user, $person);
        } catch (LogicException $e) {
            Notification::make()->title('Not updated')->body($e->getMessage())->danger()->persistent()->send();

            return false;
        }

        $user->refresh();
        Notification::make()->title($success)
            ->body(collect([$user->name, $user->email, $user->department?->name, $user->designation])->filter()->implode(' · ')
                .($user->email ? '' : ' (no email in AD — the person is asked for one at first sign-in)'))
            ->success()->send();

        return true;
    }
}
