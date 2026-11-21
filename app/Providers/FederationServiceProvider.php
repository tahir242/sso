<?php

namespace App\Providers;

use App\Domain\Federation\ApiScopes;
use App\Domain\Federation\Models\OAuthClient;
use App\Domain\Federation\Oidc\AuthCodeRepository;
use App\Domain\Federation\Oidc\Claims;
use App\Domain\Federation\Oidc\IdTokenResponse;
use App\Domain\Federation\Oidc\SigningKeys;
use App\Domain\Settings\SessionSettings;
use DateInterval;
use Error;
use Illuminate\Database\QueryException;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Bridge\AuthCodeRepository as PassportAuthCodeRepository;
use Laravel\Passport\Passport;
use Spatie\LaravelSettings\Exceptions\MissingSettings;

/**
 * Turns Passport into the OpenID Connect provider.
 */
class FederationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        Passport::ignoreRoutes(); // routes/oidc.php exposes only what we use
        Passport::useClientModel(OAuthClient::class);
        Passport::tokensCan([
            ...Claims::SCOPES,
            // Server-to-server API (client credentials), always limited to the calling application.
            ...ApiScopes::all(),
        ]);
        Passport::useAuthorizationServerResponseType(new IdTokenResponse);
        // Required by Passport's controller signature; never shown because
        // every registered application skips consent.
        Passport::authorizationView(fn () => abort(403));

        $this->app->bind(PassportAuthCodeRepository::class, AuthCodeRepository::class);
        $this->app->singleton(SigningKeys::class);
    }

    public function boot(): void
    {
        [$accessMinutes, $refreshHours] = [15, 10];

        try {
            $settings = $this->app->make(SessionSettings::class);
            [$accessMinutes, $refreshHours] = [$settings->access_token_minutes, $settings->refresh_token_hours];
        } catch (MissingSettings|QueryException|Error) {
            // Fresh install, or a stale settings cache during deploy: safe defaults.
        }

        Passport::tokensExpireIn(new DateInterval("PT{$accessMinutes}M"));
        Passport::refreshTokensExpireIn(new DateInterval("PT{$refreshHours}H"));
    }
}
