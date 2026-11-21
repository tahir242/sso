<?php

use App\Domain\Federation\Actions\GrantApplicationAccess;
use App\Domain\Federation\Actions\RegisterApplication;
use App\Domain\Federation\Models\Application;
use App\Domain\Federation\Models\ClientSession;
use App\Domain\Federation\Oidc\Claims;
use App\Domain\Federation\Oidc\SigningKeys;
use App\Domain\Identity\Actions\CompleteLogin;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Lcobucci\JWT\UnencryptedToken;
use Lcobucci\JWT\Validation\Constraint\SignedWith;

const REDIRECT_URI = 'https://hr.example.test/sso/callback';

/**
 * @return array{0: Application, 1: string}
 */
function registerApp(array $attributes = []): array
{
    return app(RegisterApplication::class)->handle([
        'slug' => 'hr',
        'name' => 'HR System',
        'base_url' => 'https://hr.example.test',
        'post_logout_redirect_uris' => ['https://hr.example.test/'],
        'backchannel_logout_uri' => 'https://hr.example.test/sso/backchannel-logout',
        'active' => true,
        ...$attributes,
    ], [REDIRECT_URI]);
}

function pkce(): array
{
    $verifier = Str::random(64);

    return [$verifier, rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=')];
}

function authorizeUrl(Application $app, string $challenge, array $extra = []): string
{
    return '/oauth/authorize?'.http_build_query([
        'client_id' => $app->oauth_client_id,
        'redirect_uri' => REDIRECT_URI,
        'response_type' => 'code',
        'scope' => 'openid profile email employee roles',
        'state' => 'state-123',
        'nonce' => 'nonce-456',
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
        ...$extra,
    ]);
}

function signedInSession(bool $mfa = false): array
{
    return [
        CompleteLogin::STARTED_AT => now()->subMinutes(5)->getTimestamp(),
        CompleteLogin::MFA_AT => $mfa ? now()->getTimestamp() : null,
        CompleteLogin::SID => 'sid-abc',
    ];
}

/**
 * Runs the browser half of the flow and returns the authorization code.
 */
function obtainCode(User $user, Application $app, string $challenge, bool $mfa = false, array $extra = []): string
{
    $response = test()->actingAs($user)->withSession(signedInSession($mfa))->get(authorizeUrl($app, $challenge, $extra));

    $response->assertRedirect();
    $location = $response->headers->get('Location');
    expect($location)->toStartWith(REDIRECT_URI);
    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
    expect($query['state'])->toBe('state-123');

    return $query['code'];
}

function exchangeCode(Application $app, string $secret, string $code, string $verifier): TestResponse
{
    return test()->post('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $app->oauth_client_id,
        'client_secret' => $secret,
        'redirect_uri' => REDIRECT_URI,
        'code' => $code,
        'code_verifier' => $verifier,
    ]);
}

function decodeJwt(string $jwt): UnencryptedToken
{
    $config = app(SigningKeys::class)->jwt();
    $token = $config->parser()->parse($jwt);

    expect($config->validator()->validate($token, new SignedWith($config->signer(), $config->verificationKey())))->toBeTrue();

    return $token;
}

function assignedUser(Application $app, array $attributes = []): User
{
    $user = User::factory()->create(['name' => 'Ayesha Siddiqui', 'email' => 'ayesha@example.test', ...$attributes]);
    app(GrantApplicationAccess::class)->handle($app, $user);

    return $user;
}

it('publishes discovery metadata and the signing key', function () {
    $this->getJson('/.well-known/openid-configuration')
        ->assertOk()
        ->assertJsonPath('issuer', rtrim(config('app.url'), '/'))
        ->assertJsonPath('code_challenge_methods_supported', ['S256'])
        ->assertJsonPath('backchannel_logout_supported', true);

    $this->getJson('/oauth/jwks')
        ->assertOk()
        ->assertJsonPath('keys.0.kty', 'RSA')
        ->assertJsonPath('keys.0.alg', 'RS256')
        ->assertJsonPath('keys.0.kid', app(SigningKeys::class)->kid());
});

it('signs a user in with authorization code + PKCE and issues a valid ID token', function () {
    [$app, $secret] = registerApp();
    $user = assignedUser($app, ['emp_id' => '20456']);
    [$verifier, $challenge] = pkce();

    $response = exchangeCode($app, $secret, obtainCode($user, $app, $challenge), $verifier)
        ->assertOk()
        ->assertJsonStructure(['access_token', 'refresh_token', 'id_token', 'expires_in', 'token_type']);

    $claims = decodeJwt($response->json('id_token'))->claims();

    expect($claims->get('iss'))->toBe(rtrim(config('app.url'), '/'))
        ->and($claims->get('aud'))->toBe([$app->oauth_client_id])
        ->and($claims->get('sub'))->toBe((string) $user->ulid)
        ->and($claims->get('nonce'))->toBe('nonce-456')
        ->and($claims->get('sid'))->toBe('sid-abc')
        ->and($claims->get('amr'))->toBe(['pwd'])
        ->and($claims->get('name'))->toBe('Ayesha Siddiqui')
        ->and($claims->get('email'))->toBe('ayesha@example.test')
        ->and($claims->get('emp_id'))->toBe('20456')
        ->and($claims->get('roles'))->toBe(['user'])
        ->and($response->json('expires_in'))->toBeLessThanOrEqual(15 * 60);
});

it('accepts an authorization code only once', function () {
    [$app, $secret] = registerApp();
    $user = assignedUser($app);
    [$verifier, $challenge] = pkce();
    $code = obtainCode($user, $app, $challenge);

    exchangeCode($app, $secret, $code, $verifier)->assertOk();
    exchangeCode($app, $secret, $code, $verifier)->assertStatus(400);
});

it('rejects a wrong PKCE verifier and a wrong client secret', function () {
    [$app, $secret] = registerApp();
    $user = assignedUser($app);
    [, $challenge] = pkce();

    exchangeCode($app, $secret, obtainCode($user, $app, $challenge), 'wrong-verifier-'.Str::random(43))->assertStatus(400);

    [$verifier, $challenge] = pkce();
    exchangeCode($app, 'not-the-secret', obtainCode($user, $app, $challenge), $verifier)->assertStatus(401);
});

it('returns claims from the userinfo endpoint', function () {
    [$app, $secret] = registerApp();
    $user = assignedUser($app);
    [$verifier, $challenge] = pkce();
    $token = exchangeCode($app, $secret, obtainCode($user, $app, $challenge), $verifier)->json('access_token');

    auth()->forgetGuards();

    $this->withToken($token)->getJson('/oauth/userinfo')
        ->assertOk()
        ->assertJsonPath('sub', (string) $user->ulid)
        ->assertJsonPath('preferred_username', $user->username)
        ->assertJsonPath('roles', ['user']);
});

it('issues a fresh ID token on refresh', function () {
    [$app, $secret] = registerApp();
    $user = assignedUser($app);
    [$verifier, $challenge] = pkce();
    $tokens = exchangeCode($app, $secret, obtainCode($user, $app, $challenge), $verifier)->json();

    $refreshed = $this->post('/oauth/token', [
        'grant_type' => 'refresh_token',
        'refresh_token' => $tokens['refresh_token'],
        'client_id' => $app->oauth_client_id,
        'client_secret' => $secret,
    ])->assertOk();

    $claims = decodeJwt($refreshed->json('id_token'))->claims();
    expect($claims->get('sub'))->toBe((string) $user->ulid)
        ->and($claims->get('sid'))->toBe('sid-abc')
        ->and($claims->has('nonce'))->toBeFalse();
});

it('sends guests to the SSO login first', function () {
    [$app] = registerApp();
    [, $challenge] = pkce();

    $this->get(authorizeUrl($app, $challenge))->assertRedirect(route('login'));
});

it('requires PKCE with S256', function () {
    [$app] = registerApp();
    $user = assignedUser($app);

    $this->actingAs($user)->withSession(signedInSession())
        ->get(authorizeUrl($app, 'x', ['code_challenge_method' => 'plain']))
        ->assertStatus(400);
});

it('shows a no-access page to users who are not assigned', function () {
    [$app] = registerApp();
    [, $challenge] = pkce();

    $this->actingAs(User::factory()->create())->withSession(signedInSession())
        ->get(authorizeUrl($app, $challenge))
        ->assertForbidden()
        ->assertSee('You don’t have access to HR System');
});

it('treats expired access as no access', function () {
    [$app] = registerApp();
    $user = User::factory()->create();
    app(GrantApplicationAccess::class)->handle($app, $user, expiresAt: now()->addDay());
    $this->travel(2)->days();
    [, $challenge] = pkce();

    $this->actingAs($user)->withSession(signedInSession())->get(authorizeUrl($app, $challenge))->assertForbidden();
});

it('asks for a second factor before opening an application that requires it', function () {
    [$app, $secret] = registerApp(['requires_mfa' => true]);
    [$user] = userWithMfa();
    app(GrantApplicationAccess::class)->handle($app, $user);
    [$verifier, $challenge] = pkce();

    $this->actingAs($user)->withSession(signedInSession(mfa: false))
        ->get(authorizeUrl($app, $challenge))
        ->assertRedirect(route('verify'));

    $idToken = exchangeCode($app, $secret, obtainCode($user, $app, $challenge, mfa: true), $verifier)->json('id_token');
    expect(decodeJwt($idToken)->claims()->get('amr'))->toBe(['pwd', 'otp']);
});

it('blocks deactivated applications', function () {
    [$app] = registerApp();
    $user = assignedUser($app);
    $app->update(['active' => false]);
    [, $challenge] = pkce();

    $response = $this->actingAs($user)->withSession(signedInSession())->get(authorizeUrl($app, $challenge));

    expect($response->isRedirect() && str_contains((string) $response->headers->get('Location'), 'code='))->toBeFalse();
});

it('signs the user out of every application with back-channel logout', function () {
    Http::fake();
    [$app] = registerApp();
    $user = assignedUser($app);
    [, $challenge] = pkce();
    obtainCode($user, $app, $challenge);

    $this->post('/logout')->assertRedirect(route('login'));

    expect(ClientSession::first()->logged_out_at)->not->toBeNull();

    Http::assertSent(function ($request) use ($app, $user) {
        $token = decodeJwt($request['logout_token']);

        return $request->url() === 'https://hr.example.test/sso/backchannel-logout'
            && $token->claims()->get('sid') === 'sid-abc'
            && $token->claims()->get('sub') === (string) $user->ulid
            && $token->claims()->get('aud') === [$app->oauth_client_id]
            && array_key_exists('http://schemas.openid.net/event/backchannel-logout', (array) $token->claims()->get('events'));
    });
});

it('supports RP-initiated logout to a registered address only', function () {
    Http::fake();
    [$app, $secret] = registerApp();
    $user = assignedUser($app);
    [$verifier, $challenge] = pkce();
    $idToken = exchangeCode($app, $secret, obtainCode($user, $app, $challenge), $verifier)->json('id_token');

    $this->get('/oauth/logout?'.http_build_query([
        'id_token_hint' => $idToken,
        'post_logout_redirect_uri' => 'https://hr.example.test/',
        'state' => 'bye',
    ]))->assertRedirect('https://hr.example.test/?state=bye');
    $this->assertGuest();

    $this->get('/oauth/logout?'.http_build_query([
        'client_id' => $app->oauth_client_id,
        'post_logout_redirect_uri' => 'https://evil.example/',
    ]))->assertRedirect(route('login'));
});

it('asks for confirmation when logout is requested without a valid ID token', function () {
    [$app] = registerApp();
    $user = assignedUser($app);

    $this->actingAs($user)->withSession(signedInSession())
        ->get('/oauth/logout')
        ->assertOk()
        ->assertSee('Sign out everywhere?');

    $this->assertAuthenticatedAs($user);
});

it('revokes tokens when access is removed or the account is disabled', function () {
    Http::fake();
    [$app, $secret] = registerApp();
    $user = assignedUser($app);
    [$verifier, $challenge] = pkce();
    $tokens = exchangeCode($app, $secret, obtainCode($user, $app, $challenge), $verifier)->json();

    $user->update(['status' => UserStatus::Disabled]);

    $this->post('/oauth/token', [
        'grant_type' => 'refresh_token',
        'refresh_token' => $tokens['refresh_token'],
        'client_id' => $app->oauth_client_id,
        'client_secret' => $secret,
    ])->assertStatus(400);

    expect($user->tokens()->where('revoked', false)->count())->toBe(0);
});

it('keeps PKCE mandatory unless a confidential client is explicitly exempted', function () {
    [$app, $secret] = registerApp();
    $user = assignedUser($app);
    $withoutPkce = fn () => preg_replace('/&code_challenge[^&]*/', '', authorizeUrl($app, 'unused'));

    // Default: a request without PKCE is refused.
    $this->actingAs($user)->withSession(signedInSession())->get($withoutPkce())->assertStatus(400);

    // Exempted confidential client (e.g. Moodle, Koha): the code flow works with the secret alone.
    $app->update(['require_pkce' => false]);
    $response = $this->actingAs($user)->withSession(signedInSession())->get($withoutPkce())->assertRedirect();
    parse_str((string) parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);

    $this->post('/oauth/token', [
        'grant_type' => 'authorization_code', 'client_id' => $app->oauth_client_id, 'client_secret' => $secret,
        'redirect_uri' => REDIRECT_URI, 'code' => $query['code'],
    ])->assertOk()->assertJsonStructure(['access_token', 'id_token']);

    // A challenge that is sent must still be S256.
    $this->actingAs($user)->withSession(signedInSession())
        ->get(authorizeUrl($app, 'x', ['code_challenge_method' => 'plain']))
        ->assertStatus(400);
});

it('sends given and family names for products that need them', function () {
    expect(Claims::splitName('Muhammad Ali Khan'))->toBe(['Muhammad', 'Ali Khan'])
        ->and(Claims::splitName('Ayesha'))->toBe(['Ayesha', null]);

    [$app] = registerApp();
    $user = User::factory()->create(['name' => 'Sara Ahmed Qureshi']);

    expect(app(Claims::class)->for($user, $app, ['openid', 'profile']))
        ->toMatchArray(['given_name' => 'Sara', 'family_name' => 'Ahmed Qureshi']);
});

it('lists the applications a user can open only for clients allowed to read assignments', function () {
    [$spi, $secret] = registerApp(['slug' => 'spi', 'name' => 'Reporting App']);
    $user = assignedUser($spi);
    $hr = makeApp('HR Scans');
    app(GrantApplicationAccess::class)->handle($hr, $user);
    makeApp('Pharmacy'); // not assigned: never listed

    $claimsFor = function () use ($spi, $secret, $user): array {
        [$verifier, $challenge] = pkce();
        $code = obtainCode($user, $spi->fresh(), $challenge, extra: ['scope' => 'openid roles applications']);

        return decodeJwt(exchangeCode($spi, $secret, $code, $verifier)->json('id_token'))->claims()->all();
    };

    expect($claimsFor())->not->toHaveKey('applications');

    $spi->update(['api_scopes' => [...$spi->api_scopes, 'assignments.read']]);
    $apps = collect($claimsFor()['applications'])->keyBy('slug');

    expect($apps->keys()->all())->toBe(['hr-scans', 'spi'])
        ->and($apps['hr-scans']['roles'])->toBe(['user'])
        ->and(Claims::CLIENT_SCOPES)->not->toContain('applications');
});
