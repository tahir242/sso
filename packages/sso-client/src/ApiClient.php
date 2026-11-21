<?php

namespace SsoClient;

use SsoClient\Http\Transport;

/**
 * Calls the SSO API as the application itself (client credentials). Each
 * call asks only for the scope it needs; tokens are cached on disk per scope
 * until shortly before they expire. The application can only use scopes an
 * SSO administrator enabled for it (otherwise the SSO answers 403).
 *
 * Full reference: <issuer>/docs/api
 */
final class ApiClient
{
    public function __construct(
        private readonly Config $config,
        private readonly Provider $provider,
        private readonly Transport $http,
    ) {}

    /**
     * Users who have (or had) access to this application. Scope: users.read
     *
     * @param  array{search?: string, active?: bool, page?: int, per_page?: int}  $query
     * @return array{data: array<int, array<string, mixed>>, meta: array<string, int>}
     */
    public function users(array $query = []): array
    {
        return $this->call('GET', '/api/v1/users', 'users.read', $this->query($query));
    }

    /**
     * One user of this application, or null if they never had access. Scope: users.read
     *
     * @return array<string, mixed>|null
     */
    public function user(int $userId): ?array
    {
        return $this->orNull(fn () => $this->call('GET', "/api/v1/users/{$userId}", 'users.read')['data']);
    }

    /**
     * This application's roles. Scope: roles.read
     *
     * @return array<int, array{key: string, name: string, description: ?string, is_default: bool, users_count: int}>
     */
    public function roles(): array
    {
        return $this->call('GET', '/api/v1/roles', 'roles.read')['data'];
    }

    /**
     * Access requests for this application, newest first. Scope: access-requests.read
     *
     * @param  array{status?: string, user_id?: int, page?: int, per_page?: int}  $query
     * @return array{data: array<int, array<string, mixed>>, meta: array<string, int>}
     */
    public function accessRequests(array $query = []): array
    {
        return $this->call('GET', '/api/v1/access-requests', 'access-requests.read', $this->query($query));
    }

    /**
     * One access request (to follow its approvals), or null. Scope: access-requests.read
     *
     * @return array<string, mixed>|null
     */
    public function accessRequest(string $id): ?array
    {
        return $this->orNull(fn () => $this->call('GET', '/api/v1/access-requests/'.rawurlencode($id), 'access-requests.read')['data']);
    }

    /**
     * Asks for access on a user's behalf. The normal approvals apply; the
     * user gets access only once every approver agrees. Scope: access-requests.write
     *
     * Identify the user with exactly one of user_id, username or emp_id.
     *
     * @param  array{user_id?: int, username?: string, emp_id?: string, roles?: array<int, string>, justification: string, access_until?: string, requested_by?: string}  $request
     * @return array<string, mixed> the created request
     *
     * @throws SsoException code 422 with the SSO's validation message (unknown user, already has access, ...)
     */
    public function requestAccess(array $request): array
    {
        return $this->call('POST', '/api/v1/access-requests', 'access-requests.write', [], $request)['data'];
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>|null  $form
     * @return array<string, mixed>
     */
    private function call(string $method, string $path, string $scope, array $query = [], ?array $form = null, bool $retried = false): array
    {
        $url = $this->config->issuerUrl().$path.($query ? '?'.http_build_query($query) : '');
        $headers = ['Authorization' => 'Bearer '.$this->token($scope, $retried)];
        if ($form !== null) {
            $headers['Content-Type'] = 'application/x-www-form-urlencoded';
        }

        $response = $this->http->request($method, $url, $headers, $form);

        if ($response['status'] === 401 && ! $retried) {
            return $this->call($method, $path, $scope, $query, $form, true);
        }

        $data = json_decode($response['body'], true);

        if (! in_array($response['status'], [200, 201], true) || ! is_array($data)) {
            $message = is_array($data) ? (string) ($data['message'] ?? '') : '';
            throw new SsoException(trim("SSO API request failed (HTTP {$response['status']}). {$message}"), $response['status']);
        }

        return $data;
    }

    private function token(string $scope, bool $refresh): string
    {
        $file = $this->config->cacheDir().DIRECTORY_SEPARATOR.'api-token-'.md5($scope).'.json';
        $cached = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;

        if (! $refresh && is_array($cached) && ($cached['expires_at'] ?? 0) > time() + 60) {
            return $cached['access_token'];
        }

        $tokens = $this->provider->token(['grant_type' => 'client_credentials', 'scope' => $scope]);

        @file_put_contents($file, json_encode([
            'access_token' => $tokens['access_token'],
            'expires_at' => time() + (int) ($tokens['expires_in'] ?? 300),
        ]), LOCK_EX);

        return $tokens['access_token'];
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function query(array $query): array
    {
        if (isset($query['active'])) {
            $query['active'] = $query['active'] ? '1' : '0';
        }

        return $query;
    }

    /**
     * @template T
     *
     * @param  callable(): T  $call
     * @return T|null
     */
    private function orNull(callable $call): mixed
    {
        try {
            return $call();
        } catch (SsoException $e) {
            if ($e->getCode() === 404) {
                return null;
            }

            throw $e;
        }
    }
}
