<?php

namespace SsoClient\Tests;

use PHPUnit\Framework\TestCase;
use SsoClient\Gate;
use SsoClient\GateResponse;
use SsoClient\Identity;

final class GateTest extends TestCase
{
    private FakeSso $sso;

    /** @var array<string, mixed> */
    private array $config;

    protected function setUp(): void
    {
        $_SESSION = [];
        Gate::reset();
        $this->sso = new FakeSso;
        $this->config = [
            'issuer' => FakeSso::ISSUER,
            'client_id' => FakeSso::CLIENT_ID,
            'client_secret' => 'secret',
            'redirect_uri' => 'https://apps.example.test/lab/index.php?sso=callback',
            'post_logout_redirect_uri' => 'https://apps.example.test/lab/',
            'cache_dir' => sys_get_temp_dir().'/sso-gate-test-'.bin2hex(random_bytes(4)),
        ];
    }

    /**
     * @param  array<string, mixed>  $get
     */
    private function handle(array $get = [], string $uri = '/lab/reports.php?id=7', string $method = 'GET', array $post = []): Identity|GateResponse
    {
        return Gate::handle($this->config, $get, $post, ['REQUEST_URI' => $uri, 'REQUEST_METHOD' => $method], $this->sso);
    }

    /**
     * Follows the redirect to the SSO and back, returning the callback result.
     *
     * @param  array<string, mixed>  $claims
     */
    private function signIn(array $claims = []): Identity|GateResponse
    {
        $redirect = $this->handle();
        parse_str(parse_url($redirect->headers['Location'], PHP_URL_QUERY), $query);

        $this->sso->tokenHandler = fn (array $form) => $this->sso->json([
            'access_token' => 'a1', 'refresh_token' => 'r1', 'expires_in' => 900,
            'id_token' => FakeSso::sign(['sub' => 'u1', 'nonce' => $query['nonce'], 'sid' => 'sid-1', 'name' => 'Ayesha', 'roles' => ['clerk'], ...$claims]),
        ]);

        return $this->handle(['sso' => 'callback', 'code' => 'c1', 'state' => $query['state']], '/lab/index.php?sso=callback&code=c1');
    }

    public function test_sends_visitors_to_the_sso_and_back_to_the_page_they_asked_for(): void
    {
        $redirect = $this->handle();

        $this->assertInstanceOf(GateResponse::class, $redirect);
        $this->assertStringStartsWith(FakeSso::ISSUER.'/oauth/authorize?', $redirect->headers['Location']);
        parse_str(parse_url($redirect->headers['Location'], PHP_URL_QUERY), $query);
        $this->assertSame('https://apps.example.test/lab/index.php?sso=callback', $query['redirect_uri']);

        $back = $this->signIn();
        $this->assertSame(302, $back->status);
        $this->assertSame('/lab/reports.php?id=7', $back->headers['Location']);

        $user = $this->handle();
        $this->assertInstanceOf(Identity::class, $user);
        $this->assertSame('Ayesha', $user->name);
    }

    public function test_shows_an_error_page_for_a_bad_callback(): void
    {
        $response = $this->handle(['sso' => 'callback', 'state' => 'forged', 'code' => 'x']);

        $this->assertSame(400, $response->status);
        $this->assertStringContainsString('Sign-in could not be completed', $response->body);
    }

    public function test_blocks_users_without_a_required_role(): void
    {
        $this->config['roles'] = ['admin'];
        $this->signIn();

        $response = $this->handle();
        $this->assertSame(403, $response->status);
    }

    public function test_signs_out_here_and_at_the_sso(): void
    {
        $this->signIn();
        $_SESSION['cart'] = ['app data'];

        $response = $this->handle(['sso' => 'logout']);
        $this->assertSame([], $_SESSION, 'the application session is cleared too');
        $this->assertStringStartsWith(FakeSso::ISSUER.'/oauth/logout?', $response->headers['Location']);
        $this->assertSame('https://apps.example.test/lab/index.php?sso=logout', Gate::logoutUrl());
        $this->assertInstanceOf(GateResponse::class, $this->handle());
    }

    public function test_back_channel_logout_ends_the_session(): void
    {
        $this->signIn();

        $this->assertSame(405, $this->handle(['sso' => 'backchannel-logout'])->status);
        $this->assertSame(400, $this->handle(['sso' => 'backchannel-logout'], method: 'POST', post: ['logout_token' => 'junk'])->status);

        $token = FakeSso::sign(['sid' => 'sid-1', 'events' => ['http://schemas.openid.net/event/backchannel-logout' => new \stdClass]]);
        $this->assertSame(200, $this->handle(['sso' => 'backchannel-logout'], method: 'POST', post: ['logout_token' => $token])->status);

        $this->assertInstanceOf(GateResponse::class, $this->handle());
    }
}
