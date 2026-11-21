<?php

namespace App\Domain\Identity\Directory;

use App\Domain\Settings\LdapSettings;
use LdapRecord\Connection;
use LdapRecord\Container;
use LdapRecord\Models\ActiveDirectory\User as LdapUser;
use LdapRecord\Query\Filter\Parser;

/**
 * Thin wrapper around LdapRecord that builds the AD connection from the
 * database settings (not from .env / config files).
 */
class ActiveDirectory
{
    public const string CONNECTION = 'default';

    public const string NO_SERVICE_ACCOUNT = 'No service account is saved. Active Directory does not allow anonymous searches, so enter the service account username and password under “Service account”, save, and test again.';

    /**
     * Turns common Active Directory error codes into actionable messages
     * (codes from the server's diagnostic text, e.g. "000004DC" or "data 52e").
     */
    public static function explain(string $error): string
    {
        return match (true) {
            str_contains($error, '000004DC') || str_contains($error, 'successful bind must be completed') => self::NO_SERVICE_ACCOUNT.' (Active Directory: '.$error.')',
            str_contains($error, 'data 52e') => 'The service account username or password is wrong (Active Directory: '.$error.').',
            str_contains($error, 'data 775') => 'The service account is locked out in Active Directory.',
            str_contains($error, 'data 533') => 'The service account is disabled in Active Directory.',
            str_contains($error, 'data 532') || str_contains($error, 'data 773') => 'The service account password has expired or must be changed in Active Directory.',
            str_contains($error, '0000202B') || str_contains($error, 'No such object') => 'The Base DN does not exist on this domain controller. Check it (for example DC=corp,DC=example,DC=com).',
            default => $error,
        };
    }

    public function __construct(private readonly LdapSettings $settings) {}

    public function isEnabled(): bool
    {
        return $this->settings->isConfigured();
    }

    /**
     * Registers the connection with LdapRecord's container on first use.
     * Tests replace it with DirectoryEmulator::setup('default').
     */
    public function connection(): Connection
    {
        if (! Container::hasConnection(self::CONNECTION)) {
            Container::addConnection(new Connection($this->connectionConfig()), self::CONNECTION);
        }

        return Container::getConnection(self::CONNECTION);
    }

    /**
     * @return array<string, mixed>
     */
    public function connectionConfig(): array
    {
        return [
            'hosts' => $this->settings->hosts,
            'port' => $this->settings->port,
            'base_dn' => $this->settings->base_dn,
            'username' => (string) $this->settings->bind_username,
            'password' => (string) $this->settings->bind_password,
            // LdapRecord v4 naming: use_tls = LDAPS (ldaps://), use_starttls = StartTLS.
            'use_tls' => $this->settings->encryption === 'ssl',
            'use_starttls' => $this->settings->encryption === 'tls',
            'timeout' => $this->settings->timeout,
            'follow_referrals' => false,
            'options' => [
                // Domain controllers usually use an internal CA: install it in
                // the server's trust store and keep verification on.
                LDAP_OPT_X_TLS_REQUIRE_CERT => $this->settings->verify_certificate
                    ? LDAP_OPT_X_TLS_DEMAND
                    : LDAP_OPT_X_TLS_NEVER,
            ],
        ];
    }

    public function findUser(string $username): ?LdapUser
    {
        $this->connection();

        $query = LdapUser::on(self::CONNECTION)->where('samaccountname', '=', $username);

        if ($filter = trim((string) $this->settings->user_filter)) {
            Parser::parse($filter); // throws on an invalid filter instead of silently matching nothing
            $query->rawFilter($filter);
        }

        return $query->first();
    }

    /**
     * People in AD whose username, name, email or employee ID matches, for
     * adding them to the SSO. Values are escaped by LdapRecord; the optional
     * user filter from the settings still applies.
     *
     * @return list<LdapUser>
     */
    public function search(string $term, int $limit = 25): array
    {
        $term = trim($term);

        if (mb_strlen($term) < 2) {
            return [];
        }

        $this->connection();

        $query = LdapUser::on(self::CONNECTION)
            ->select(['objectguid', 'samaccountname', 'displayname', 'cn', 'mail', 'employeeid', 'title', 'department', 'useraccountcontrol'])
            // orFilter groups these as (|(...)(...)): any one of them may match.
            ->orFilter(fn ($q) => $q
                ->whereStartsWith('samaccountname', $term)
                ->whereContains('displayname', $term)
                ->whereStartsWith('mail', $term)
                ->whereEquals('employeeid', $term))
            ->limit($limit);

        if ($filter = trim((string) $this->settings->user_filter)) {
            Parser::parse($filter);
            $query->rawFilter($filter);
        }

        return array_values($query->get()->all());
    }

    public function findUserByGuid(string $guid): ?LdapUser
    {
        $this->connection();

        return LdapUser::on(self::CONNECTION)->findByGuid($guid);
    }

    /**
     * Verifies a password with an LDAP bind, then re-binds as the service account.
     */
    public function attempt(LdapUser $user, string $password): bool
    {
        return $this->connection()->auth()->attempt($user->getDn(), $password);
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function test(): array
    {
        if (blank($this->settings->bind_username) || blank($this->settings->bind_password)) {
            return ['ok' => false, 'message' => self::NO_SERVICE_ACCOUNT];
        }

        try {
            $connection = $this->connection();
            $connection->connect();

            $count = LdapUser::on(self::CONNECTION)->limit(1)->get()->count();

            return ['ok' => true, 'message' => "Connected and searched the directory successfully ({$count} sample user found)."];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => self::explain($e->getMessage())];
        } finally {
            // Settings may have just changed: drop the cached connection.
            Container::removeConnection(self::CONNECTION);
        }
    }

    /**
     * Forget the cached connection after settings change.
     */
    public function reset(): void
    {
        if (Container::hasConnection(self::CONNECTION)) {
            Container::removeConnection(self::CONNECTION);
        }
    }
}
