<?php

namespace App\Domain\Federation\Oidc;

use App\Domain\Federation\ApiScopes;
use App\Domain\Federation\Models\Application;
use App\Domain\Identity\Models\User;

/**
 * Which user claims each scope releases. Clients only receive what they ask
 * for, and `roles` only ever contains roles of the requesting application.
 */
final class Claims
{
    /** @var array<string, string> scope => description (also the Passport scope list) */
    public const array SCOPES = [
        'openid' => 'Sign you in',
        'profile' => 'Your name and username',
        'email' => 'Your email address',
        'employee' => 'Your employee ID, department and designation',
        'roles' => 'Your roles in this application',
        'applications' => 'The applications you can open and your roles in them',
    ];

    /**
     * Scopes a normal client asks for. `applications` is only for clients an
     * administrator allowed to read assignments (ApiScopes::ASSIGNMENTS_READ),
     * such as a reporting application.
     */
    public const array CLIENT_SCOPES = ['openid', 'profile', 'email', 'employee', 'roles'];

    /**
     * @param  array<int, string>  $scopes
     * @return array<string, mixed>
     */
    public function for(User $user, ?Application $application, array $scopes): array
    {
        $claims = [];

        if (in_array('profile', $scopes, true)) {
            $claims['name'] = $user->name;
            // given/family names (standard OIDC) for products that need them separately, e.g. Koha and Moodle.
            [$claims['given_name'], $claims['family_name']] = self::splitName((string) $user->name);
            $claims['preferred_username'] = $user->username;
            $claims['updated_at'] = $user->updated_at?->getTimestamp();
        }

        if (in_array('email', $scopes, true)) {
            $claims['email'] = $user->email;
            $claims['email_verified'] = false;
        }

        if (in_array('employee', $scopes, true)) {
            // Numeric SSO user id. Existing applications store it in their
            // own tables (e.g. "created by"); users migrated from the old SSO
            // kept their old UserID, so those references stay valid.
            $claims['user_id'] = $user->getKey();
            $claims['emp_id'] = $user->emp_id;
            $claims['emp_initial'] = $user->emp_initial;
            $claims['department'] = $user->department?->name;
            $claims['designation'] = $user->designation;
        }

        if (in_array('roles', $scopes, true)) {
            $claims['roles'] = $application?->roleKeysFor($user) ?? [];
        }

        // Every application the user can open, with their roles there — released
        // only to applications an administrator allowed to read assignments.
        if (in_array('applications', $scopes, true) && $application?->allowsApiScopes([ApiScopes::ASSIGNMENTS_READ])) {
            $claims['applications'] = Application::query()->openableBy($user)->orderBy('slug')->get()
                ->map(fn (Application $app) => ['slug' => $app->slug, 'name' => $app->name, 'roles' => $app->roleKeysFor($user)])
                ->values()->all();
        }

        return array_filter($claims, fn ($value) => $value !== null);
    }

    /**
     * "Muhammad Ali Khan" -> ["Muhammad", "Ali Khan"]; a single name is the given name.
     *
     * @return array{0: ?string, 1: ?string}
     */
    public static function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name), 2) ?: [];

        return [($parts[0] ?? '') ?: null, ($parts[1] ?? '') ?: null];
    }
}
