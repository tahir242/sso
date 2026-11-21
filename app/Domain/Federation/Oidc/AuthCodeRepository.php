<?php

namespace App\Domain\Federation\Oidc;

use Laravel\Passport\Bridge\AuthCodeRepository as PassportAuthCodeRepository;
use Laravel\Passport\Passport;
use League\OAuth2\Server\Entities\AuthCodeEntityInterface;

/**
 * Stores the OIDC `nonce` and the SSO client session next to each
 * authorization code, so the token endpoint can put them in the ID token.
 */
class AuthCodeRepository extends PassportAuthCodeRepository
{
    public const string CLIENT_SESSION_ATTRIBUTE = 'oidc.client_session_id';

    public function persistNewAuthCode(AuthCodeEntityInterface $authCodeEntity): void
    {
        parent::persistNewAuthCode($authCodeEntity);

        $nonce = request()->query('nonce');

        Passport::authCode()->newQuery()->whereKey($authCodeEntity->getIdentifier())->update([
            'nonce' => is_string($nonce) ? mb_substr($nonce, 0, 255) : null,
            'client_session_id' => request()->attributes->get(self::CLIENT_SESSION_ATTRIBUTE),
        ]);
    }
}
