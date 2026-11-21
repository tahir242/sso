<?php

namespace App\Http\Middleware;

use App\Domain\Federation\Models\Application;
use Closure;
use Illuminate\Http\Request;
use Laravel\Passport\AccessToken;
use Laravel\Passport\Http\Middleware\EnsureClientIsResourceOwner;
use Symfony\Component\HttpFoundation\Response;

/**
 * Server-to-server API calls: requires a client-credentials access token
 * (not a user token) with the given scopes, from an active registered
 * application. The application is available as the `application` request
 * attribute, so every query can be limited to that application's data.
 */
class AuthenticateApplicationClient extends EnsureClientIsResourceOwner
{
    public function handle(Request $request, Closure $next, string ...$params): Response
    {
        $token = $this->validateToken($request);
        $this->validate($token, ...$params);

        $clientId = $token instanceof AccessToken ? $token->oauth_client_id : null;
        $application = $clientId ? Application::query()->active()->where('oauth_client_id', $clientId)->first() : null;

        abort_unless($application, 403, 'This client is not an active application.');
        abort_unless($application->allowsApiScopes($params), 403, 'This application is not allowed to use: '.implode(', ', $params).'.');

        $request->attributes->set('application', $application);

        return $next($request);
    }
}
