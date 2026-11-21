<?php

namespace App\Domain\Federation\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Laravel\Passport\Client;

/**
 * Passport client. Every client belongs to a registered application,
 * so the consent screen is skipped (first-party apps only).
 */
class OAuthClient extends Client
{
    public function application(): HasOne
    {
        return $this->hasOne(Application::class, 'oauth_client_id');
    }

    public function skipsAuthorization(Authenticatable $user, array $scopes): bool
    {
        return true;
    }
}
