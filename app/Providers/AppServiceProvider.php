<?php

namespace App\Providers;

use App\Domain\Audit\SecurityAlerts;
use App\Domain\Federation\ApiScopes;
use App\Domain\Federation\Models\OAuthClient;
use App\Domain\Identity\Models\User;
use App\Support\Database\SqlServerConnection;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Dedoc\Scramble\Support\Generator\SecuritySchemes\OAuthFlow;
use Filament\Tables\Table;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Connection;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Spatie\Permission\Events\RoleAttachedEvent;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // SQL Server: return BIGINT ids as integers, like the other databases.
        Connection::resolverFor('sqlsrv', fn ($pdo, $database, $prefix, $config) => new SqlServerConnection($pdo, $database, $prefix, $config));
    }

    public function boot(): void
    {
        // Admin tables never force sideways scrolling on phones: rows become stacked cards.
        Table::configureUsing(fn (Table $table) => $table->stackedOnMobile());

        // Server-to-server API: 120 calls a minute per application.
        RateLimiter::for('application-api', fn (Request $request) => Limit::perMinute(120)
            ->by('app:'.($request->attributes->get('application')?->getKey() ?? $request->ip())));

        // Token endpoint: generous per application (one app server signs in
        // many users), strict for unknown clients guessing secrets or codes.
        RateLimiter::for('oauth-token', function (Request $request) {
            $client = (string) ($request->input('client_id') ?? $request->getUser() ?? '');
            // Client IDs are UUIDs; SQL Server errors on comparing anything else with that column.
            $known = Str::isUuid($client) && OAuthClient::whereKey($client)->where('revoked', false)->exists();

            return $known
                ? Limit::perMinute(600)->by('token:'.$client.'|'.$request->ip())
                : Limit::perMinute(20)->by('token-unknown:'.$request->ip());
        });

        $this->documentApi();

        // Alert the security team whenever someone gets an admin-console role.
        Event::listen(function (RoleAttachedEvent $event): void {
            if (! $event->model instanceof User) {
                return;
            }

            $names = collect(is_iterable($event->rolesOrIds) ? $event->rolesOrIds : [$event->rolesOrIds])
                ->map(fn ($role) => match (true) {
                    $role instanceof Role => $role->name,
                    is_numeric($role) => Role::find($role)?->name,
                    default => (string) $role,
                })
                ->filter(fn (?string $name) => in_array($name, User::ADMIN_ROLES, true))
                ->values()
                ->all();

            if ($names !== []) {
                /** @var User|null $by */
                $by = auth()->user();
                app(SecurityAlerts::class)->adminRoleGranted($event->model, $names, $by);
            }
        });
    }

    /**
     * OpenAPI documentation at /docs/api (Scramble), for super-admins and
     * application owners. Describes the client-credentials flow and scopes.
     */
    private function documentApi(): void
    {
        Gate::define('viewApiDocs', fn (?User $user) => (bool) $user?->hasAnyRole(['super-admin', 'app-owner']));

        Scramble::configure()
            ->routes(fn (Route $route) => str_starts_with($route->uri, 'api/v1/'))
            ->withDocumentTransformers(function (OpenApi $openApi): void {
                $openApi->info->description = <<<'MD'
                    Server-to-server API for applications registered in the SSO.

                    **Authentication.** Get an access token with the OAuth 2.0 *client credentials* grant using the
                    application's client ID and secret, asking for the scopes you need (space-separated):

                    ```
                    POST /oauth/token
                    grant_type=client_credentials&client_id=…&client_secret=…&scope=users.read roles.read
                    ```

                    Send it as `Authorization: Bearer <token>`. Tokens are short-lived; request a new one when you get `401`.

                    **Limits.** Every call only sees the calling application's own users and requests. An application may
                    only use the scopes an administrator enabled for it (Applications → Single sign-on); others get `403`.
                    120 calls per minute per application (`429` after that).
                    MD;

                $openApi->secure(
                    SecurityScheme::oauth2()->flow('clientCredentials', function (OAuthFlow $flow): void {
                        $flow->tokenUrl(url('/oauth/token'));
                        foreach (ApiScopes::all() as $scope => $description) {
                            $flow->addScope($scope, $description);
                        }
                    }),
                );
            });
    }
}
