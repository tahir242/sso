<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Audit\Enums\AuthEventType;
use App\Domain\Audit\Models\AuthEvent;
use App\Domain\Identity\Enums\AuthSource;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\Department;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Support\EmployeeInitials;
use Illuminate\Support\Str;
use LdapRecord\Models\ActiveDirectory\User as LdapUser;
use LogicException;

/**
 * Creates the SSO account for an Active Directory user — used both when an
 * administrator adds someone from AD and when a user signs in for the first
 * time (auto-create). The account signs in with the Windows password; the
 * profile (name, email, department, ...) comes from AD.
 */
final class ImportDirectoryUser
{
    /**
     * @throws LogicException when the person already has an SSO account
     */
    public function handle(LdapUser $ldapUser, ?User $by = null): User
    {
        if ($existing = self::existingAccount($ldapUser)) {
            throw new LogicException("{$existing->name} ({$existing->username}) already has an SSO account.");
        }

        $user = new User([
            'username' => $ldapUser->getFirstAttribute('samaccountname'),
            'status' => UserStatus::Active,
        ]);
        $user->forceFill(self::profile($ldapUser))->save();

        AuthEvent::record(AuthEventType::UserProvisioned, $user, meta: array_filter([
            'source' => 'active-directory',
            'by' => $by?->username,
        ]));

        return $user;
    }

    /**
     * Copies the AD details onto an existing SSO account and makes it an
     * Active Directory account (password checked by AD; no local password).
     * Used when a local account is switched to AD and by "Refresh from AD".
     * Values AD does not have are kept; existing initials are kept.
     *
     * @throws LogicException when this AD account is already linked to another SSO account
     */
    public static function applyTo(User $user, LdapUser $ldapUser): User
    {
        $guid = $ldapUser->getConvertedGuid();
        $owner = User::query()->where('ldap_guid', $guid)->whereKeyNot($user->getKey())->first();

        if ($owner) {
            throw new LogicException("This Active Directory account is already linked to {$owner->name} ({$owner->username}).");
        }

        $user->forceFill([
            ...array_filter(self::profile($ldapUser, $user), fn ($value) => $value !== null),
            'password' => null,
            'legacy_md5' => false,
        ])->save();

        return $user;
    }

    /**
     * SSO profile fields from an AD account. Employee ID and initials are
     * only taken when no other SSO account uses them.
     *
     * @return array<string, mixed>
     */
    public static function profile(LdapUser $ldapUser, ?User $for = null): array
    {
        $ignore = $for?->getKey();
        $empId = trim((string) $ldapUser->getFirstAttribute('employeeid'));
        $adInitials = mb_strtoupper(trim((string) $ldapUser->getFirstAttribute('initials')));
        $name = self::displayName($ldapUser);

        $initials = match (true) {
            filled($for?->emp_initial) => $for->emp_initial,
            $adInitials !== '' && mb_strlen($adInitials) <= EmployeeInitials::MAX && ! EmployeeInitials::taken($adInitials, $ignore) => $adInitials,
            default => EmployeeInitials::generate($name, $ignore),
        };

        $email = self::email($ldapUser);
        if ($email && User::where('email', $email)->when($ignore, fn ($q) => $q->whereKeyNot($ignore))->exists()) {
            $email = null; // already another SSO account's address
        }

        return [
            'name' => $name,
            'email' => $email,
            'emp_id' => $empId !== '' && ! User::where('emp_id', $empId)->when($ignore, fn ($q) => $q->whereKeyNot($ignore))->exists() ? $empId : null,
            'emp_initial' => $initials,
            'mobile' => $ldapUser->getFirstAttribute('mobile'),
            'designation' => $ldapUser->getFirstAttribute('title'),
            'department_id' => self::departmentId($ldapUser),
            'auth_source' => AuthSource::ActiveDirectory,
            'ldap_guid' => $ldapUser->getConvertedGuid(),
        ];
    }

    /**
     * The SSO account already linked to (or named like) this AD user.
     */
    public static function existingAccount(LdapUser $ldapUser): ?User
    {
        return User::query()
            ->where('ldap_guid', $ldapUser->getConvertedGuid())
            ->orWhere('username', (string) $ldapUser->getFirstAttribute('samaccountname'))
            ->first();
    }

    public static function displayName(LdapUser $ldapUser): string
    {
        return (string) ($ldapUser->getFirstAttribute('displayname') ?: $ldapUser->getFirstAttribute('cn') ?: $ldapUser->getFirstAttribute('samaccountname'));
    }

    public static function email(LdapUser $ldapUser): ?string
    {
        $mail = trim((string) $ldapUser->getFirstAttribute('mail'));

        return $mail !== '' ? mb_strtolower($mail) : null;
    }

    /**
     * AD `department` attribute -> SSO department (created on first sight, so
     * an administrator only has to set its head of department).
     */
    public static function departmentId(LdapUser $ldapUser): ?int
    {
        $name = trim((string) $ldapUser->getFirstAttribute('department'));

        if ($name === '') {
            return null;
        }

        $department = Department::query()->where('name', $name)->first();

        if (! $department) {
            $base = Str::limit(Str::upper(Str::slug($name, '_')), 26, '') ?: 'DEPT';
            $code = $base;
            for ($i = 2; Department::where('code', $code)->exists(); $i++) {
                $code = "{$base}_{$i}";
            }

            $department = Department::create(['name' => Str::limit($name, 150, ''), 'code' => $code, 'active' => true]);
        }

        return $department->getKey();
    }
}
