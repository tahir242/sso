<?php

namespace App\Domain\Federation;

/**
 * Scopes for the server-to-server API (client credentials). Every call is
 * limited to the calling application's own data, and each application may
 * only use the scopes an administrator enabled for it.
 */
final class ApiScopes
{
    public const USERS_READ = 'users.read';

    public const ROLES_READ = 'roles.read';

    public const ACCESS_REQUESTS_READ = 'access-requests.read';

    public const ACCESS_REQUESTS_WRITE = 'access-requests.write';

    /**
     * Not an API call: allows the OIDC `applications` claim (every application
     * a signed-in user can open, with roles). For trusted platforms such as
     * reporting applications only.
     */
    public const ASSIGNMENTS_READ = 'assignments.read';

    /** Enabled for a newly registered application. */
    public const DEFAULTS = [self::USERS_READ, self::ROLES_READ];

    /**
     * @return array<string, string> scope => description
     */
    public static function all(): array
    {
        return [
            self::USERS_READ => 'Read the users who can access this application',
            self::ROLES_READ => 'Read this application’s roles',
            self::ACCESS_REQUESTS_READ => 'Read access requests for this application',
            self::ACCESS_REQUESTS_WRITE => 'Request access to this application on behalf of a user (normal approvals apply)',
            self::ASSIGNMENTS_READ => 'At sign-in, see every application the user can open and their roles there (trusted platforms only)',
        ];
    }
}
