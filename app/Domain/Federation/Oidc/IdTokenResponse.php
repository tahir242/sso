<?php

namespace App\Domain\Federation\Oidc;

use App\Domain\Federation\Models\Application;
use App\Domain\Federation\Models\ClientSession;
use App\Domain\Identity\Models\User;
use DateTimeImmutable;
use Laravel\Passport\Passport;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\ResponseTypes\BearerTokenResponse;
use Throwable;

/**
 * Token endpoint response: the usual access/refresh tokens plus a signed
 * OIDC ID token whenever the `openid` scope was granted.
 */
class IdTokenResponse extends BearerTokenResponse
{
    protected function getExtraParams(AccessTokenEntityInterface $accessToken): array
    {
        $scopes = array_map(fn ($scope) => $scope->getIdentifier(), $accessToken->getScopes());

        if (! in_array('openid', $scopes, true) || ! $accessToken->getUserIdentifier()) {
            return [];
        }

        $user = User::find($accessToken->getUserIdentifier());
        $clientId = $accessToken->getClient()->getIdentifier();

        if (! $user) {
            return [];
        }

        [$nonce, $session] = $this->authorizationContext($user, $clientId);

        return ['id_token' => $this->idToken($user, $clientId, $scopes, $accessToken->getExpiryDateTime(), $nonce, $session)];
    }

    /**
     * @param  array<int, string>  $scopes
     */
    private function idToken(User $user, string $clientId, array $scopes, DateTimeImmutable $expiresAt, ?string $nonce, ?ClientSession $session): string
    {
        $keys = app(SigningKeys::class);
        $now = new DateTimeImmutable;
        $application = Application::where('oauth_client_id', $clientId)->first();

        $builder = $keys->jwt()->builder()
            ->withHeader('kid', $keys->kid())
            ->issuedBy(Issuer::url())
            ->permittedFor($clientId)
            ->relatedTo((string) $user->ulid)
            ->issuedAt($now)
            ->expiresAt($expiresAt);

        if ($nonce !== null) {
            $builder = $builder->withClaim('nonce', $nonce);
        }

        if ($session) {
            $builder = $builder
                ->withClaim('sid', $session->sid)
                ->withClaim('auth_time', $session->auth_time->getTimestamp())
                ->withClaim('amr', $session->amr)
                ->withClaim('acr', in_array('otp', $session->amr, true) ? Issuer::ACR_MFA : Issuer::ACR_PASSWORD);
        }

        foreach (app(Claims::class)->for($user, $application, $scopes) as $name => $value) {
            $builder = $builder->withClaim($name, $value);
        }

        return $builder->getToken($keys->jwt()->signer(), $keys->jwt()->signingKey())->toString();
    }

    /**
     * Nonce and SSO session for this token request: from the authorization
     * code on first exchange, otherwise the latest session for this client.
     *
     * @return array{0: ?string, 1: ?ClientSession}
     */
    private function authorizationContext(User $user, string $clientId): array
    {
        if (request()->input('grant_type') === 'authorization_code' && is_string($code = request()->input('code'))) {
            try {
                $payload = json_decode($this->decrypt($code), true, 512, JSON_THROW_ON_ERROR);
                $authCode = Passport::authCode()->newQuery()->find($payload['auth_code_id'] ?? null);

                if ($authCode) {
                    return [$authCode->nonce, ClientSession::find($authCode->client_session_id)];
                }
            } catch (Throwable) {
                // Fall through: the grant itself already validated the code.
            }
        }

        $session = ClientSession::query()
            ->where('user_id', $user->getKey())
            ->where('oauth_client_id', $clientId)
            ->whereNull('logged_out_at')
            ->latest('last_authorized_at')
            ->first();

        return [null, $session];
    }
}
