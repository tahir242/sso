<?php

namespace App\Domain\Identity\Directory;

use App\Domain\Identity\Actions\ImportDirectoryUser;
use App\Domain\Identity\Enums\AuthSource;
use App\Domain\Identity\Models\User;
use App\Domain\Settings\LdapSettings;
use Illuminate\Support\Facades\Log;
use LdapRecord\LdapRecordException;
use LdapRecord\Models\ActiveDirectory\User as LdapUser;

/**
 * Verifies an AD password with an LDAP bind, keeps the SSO profile in sync
 * with AD and (optionally) creates the SSO account on first sign-in.
 */
final readonly class ActiveDirectoryAuthenticator
{
    public function __construct(
        private ActiveDirectory $directory,
        private LdapSettings $settings,
        private ImportDirectoryUser $import,
    ) {}

    /**
     * @return User|null the signed-in user, or null when the credentials are not valid in AD
     */
    public function authenticate(?User $user, string $identifier, string $password): ?User
    {
        if (! $this->directory->isEnabled() || $password === '') {
            return null;
        }

        try {
            $ldapUser = $this->locate($user, $identifier);

            if (! $ldapUser || $ldapUser->isDisabled() || ! $this->directory->attempt($ldapUser, $password)) {
                return null;
            }
        } catch (LdapRecordException $e) {
            // Directory unreachable: fail closed, keep the reason in the log.
            Log::warning('Active Directory sign-in failed: '.$e->getMessage());

            return null;
        }

        return $user ? $this->sync($user, $ldapUser) : $this->provision($ldapUser);
    }

    private function locate(?User $user, string $identifier): ?LdapUser
    {
        if ($user?->ldap_guid && ($found = $this->directory->findUserByGuid($user->ldap_guid))) {
            return $found;
        }

        return $this->directory->findUser($user?->username ?? $identifier);
    }

    private function sync(User $user, LdapUser $ldapUser): User
    {
        $user->forceFill(array_filter([
            'ldap_guid' => $ldapUser->getConvertedGuid(),
            'name' => ImportDirectoryUser::displayName($ldapUser),
            'email' => ImportDirectoryUser::email($ldapUser),
            'department_id' => ImportDirectoryUser::departmentId($ldapUser),
            'auth_source' => AuthSource::ActiveDirectory,
        ]))->save();

        return $user;
    }

    private function provision(LdapUser $ldapUser): ?User
    {
        if (! $this->settings->auto_create_users) {
            return null;
        }

        try {
            return $this->import->handle($ldapUser);
        } catch (\LogicException $e) {
            // The AD account is already linked to another SSO account: do not sign in as it.
            Log::warning('Active Directory sign-in not provisioned: '.$e->getMessage());

            return null;
        }
    }
}
