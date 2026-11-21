<?php

namespace SsoClient\Tests;

use PHPUnit\Framework\TestCase;
use SsoClient\Client;
use SsoClient\Config;
use SsoClient\Identity;
use SsoClient\SsoException;

final class ClientTest extends TestCase
{
    private FakeSso $sso;

    private Client $client;

    private string $cacheDir;

    protected function setUp(): void
    {
        $_SESSION = [];
        $this->cacheDir = sys_get_temp_dir().'/sso-test-'.bin2hex(random_bytes(4));
        $this->sso = new FakeSso;
        $this->client = new Client(new Config(
            issuer: FakeSso::ISSUER,
            clientId: FakeSso::CLIENT_ID,
            clientSecret: 'secret',
            redirectUri: 'https://hr.example.test/sso/callback.php',
            postLogoutRedirectUri: 'https://hr.example.test/',
            cacheDir: $this->cacheDir,
        ), $this->sso);
    }

    public function test_authorization_url_uses_pkce_state_and_nonce(): void
    {
        parse_str(parse_url($this->client->authorizationUrl('/hr/app/dashboard.php'), PHP_URL_QUERY), $query);

        $this->assertSame(FakeSso::CLIENT_ID, $query['client_id']);
        $this->assertSame('S256', $query['code_challenge_method']);
        $this->assertSame(43, strlen($query['code_challenge']));
        $this->assertNotEmpty($query['state']);
        $this->assertNotEmpty($query['nonce']);
        $this->assertStringContainsString('employee', $query['scope']);
    }

    public function test_callback_signs_the_user_in(): void
    {
        $identity = $this->signIn(['roles' => ['clerk'], 'user_id' => 42, 'emp_initial' => 'AK'])['identity'];

        $this->assertSame('user-ulid', $identity->sub);
        $this->assertSame(42, $identity->userId);
        $this->assertSame('AK', $identity->empInitial);
        $this->assertTrue($identity->hasRole('clerk'));
        $this->assertSame('user-ulid', $this->client->identity()?->sub);
    }

    public function test_callback_returns_only_local_paths(): void
    {
        $this->assertSame('/hr/app/item.php', $this->signIn([], '/hr/app/item.php')['return_to']);

        $_SESSION = [];
        $this->assertNull($this->signIn([], 'https://evil.example/')['return_to']);
    }

    public function test_rejects_unknown_state(): void
    {
        $this->expectException(SsoException::class);
        $this->client->handleCallback(['code' => 'abc', 'state' => 'forged']);
    }

    public function test_rejects_a_mismatched_nonce(): void
    {
        $this->expectException(SsoException::class);
        $this->signIn(['nonce' => 'other']);
    }

    public function test_rejects_tokens_for_another_application(): void
    {
        $this->expectException(SsoException::class);
        $this->signIn(['aud' => ['someone-else']]);
    }

    public function test_back_channel_logout_ends_the_local_session(): void
    {
        $this->signIn(['sid' => 'sid-1']);

        $this->client->handleBackChannelLogout(FakeSso::sign([
            'sub' => 'user-ulid',
            'sid' => 'sid-1',
            'jti' => 'x',
            'events' => ['http://schemas.openid.net/event/backchannel-logout' => new \stdClass],
        ]));

        $this->assertNull($this->client->identity());
    }

    public function test_back_channel_logout_rejects_other_tokens(): void
    {
        $this->expectException(SsoException::class);
        $this->client->handleBackChannelLogout(FakeSso::sign(['sub' => 'u', 'sid' => 's', 'nonce' => 'n']));
    }

    public function test_refreshes_expired_tokens_and_picks_up_new_roles(): void
    {
        $this->signIn(['roles' => ['clerk']], expiresIn: 0);

        $this->sso->tokenHandler = fn (array $form) => $form['grant_type'] === 'refresh_token'
            ? $this->sso->json(['access_token' => 'a2', 'refresh_token' => 'r2', 'expires_in' => 900, 'id_token' => FakeSso::sign(['sub' => 'user-ulid', 'roles' => ['admin']])])
            : $this->sso->json(['error' => 'unexpected'], 400);

        $this->assertSame(['admin'], $this->client->identity()?->roles);
    }

    public function test_signs_out_locally_when_the_sso_refuses_to_refresh(): void
    {
        $this->signIn([], expiresIn: 0);
        $this->sso->tokenHandler = fn () => $this->sso->json(['error' => 'invalid_grant'], 400);

        $this->assertNull($this->client->identity());
    }

    public function test_api_calls_ask_for_one_scope_each_and_cache_tokens_per_scope(): void
    {
        $this->sso->tokenHandler = fn (array $form) => $this->sso->json(['access_token' => 'tok-'.$form['scope'], 'expires_in' => 900]);
        $this->sso->apiHandler = fn (string $method, string $path) => match ($path) {
            '/api/v1/roles' => $this->sso->json(['data' => [['key' => 'clerk', 'name' => 'Clerk', 'description' => null, 'is_default' => true, 'users_count' => 3]]]),
            '/api/v1/users?search=ali' => $this->sso->json(['data' => [], 'meta' => ['total' => 0, 'page' => 1, 'per_page' => 25, 'last_page' => 1]]),
            default => $this->sso->json(['message' => 'Not found'], 404),
        };

        $api = $this->client->api();
        $this->assertSame('clerk', $api->roles()[0]['key']);
        $api->roles();
        $this->assertSame(0, $api->users(['search' => 'ali'])['meta']['total']);
        $this->assertNull($api->user(99));

        $scopes = array_column(array_column(array_filter($this->sso->requests, fn ($r) => str_ends_with($r['url'], '/oauth/token')), 'form'), 'scope');
        $this->assertSame(['roles.read', 'users.read'], $scopes);
    }

    public function test_request_access_posts_the_form_and_reports_validation_errors(): void
    {
        $this->sso->tokenHandler = fn (array $form) => $this->sso->json(['access_token' => 'tok', 'expires_in' => 900]);
        $this->sso->apiHandler = fn (string $method, string $path, ?array $form) => ($form['username'] ?? '') === 'ayesha'
            ? $this->sso->json(['data' => ['id' => '01J', 'status' => 'pending', 'roles' => $form['roles']]], 201)
            : $this->sso->json(['message' => 'No active SSO user matches.'], 422);

        $created = $this->client->api()->requestAccess(['username' => 'ayesha', 'roles' => ['clerk'], 'justification' => 'Joins payroll team.']);
        $this->assertSame('pending', $created['status']);
        $this->assertSame('POST', end($this->sso->requests)['method']);

        try {
            $this->client->api()->requestAccess(['username' => 'nobody', 'justification' => 'Joins payroll team.']);
            $this->fail('Expected a validation error.');
        } catch (SsoException $e) {
            $this->assertSame(422, $e->getCode());
            $this->assertStringContainsString('No active SSO user matches.', $e->getMessage());
        }
    }

    public function test_logout_url_includes_the_id_token_hint(): void
    {
        $this->signIn();

        parse_str(parse_url($this->client->logoutUrl(), PHP_URL_QUERY), $query);

        $this->assertNotEmpty($query['id_token_hint']);
        $this->assertSame('https://hr.example.test/', $query['post_logout_redirect_uri']);
        $this->assertNull($this->client->identity());
    }

    /**
     * @return array{identity: Identity, return_to: ?string}
     */
    private function signIn(array $claims = [], ?string $returnTo = null, int $expiresIn = 900): array
    {
        parse_str(parse_url($this->client->authorizationUrl($returnTo), PHP_URL_QUERY), $query);

        $this->sso->tokenHandler = fn (array $form) => $this->sso->json([
            'access_token' => 'a1',
            'refresh_token' => 'r1',
            'expires_in' => $expiresIn,
            'id_token' => FakeSso::sign(['sub' => 'user-ulid', 'nonce' => $query['nonce'], 'sid' => 'sid-1', ...$claims]),
        ]);

        return $this->client->handleCallback(['code' => 'code-1', 'state' => $query['state']]);
    }
}
