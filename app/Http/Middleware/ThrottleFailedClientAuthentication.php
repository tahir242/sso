<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Token endpoint protection that does not limit real traffic: one application
 * server exchanges codes and refreshes tokens for all of its users, so only
 * failed client authentication (unknown client ID or wrong secret, HTTP 401
 * `invalid_client`) is counted. Expired or revoked codes and refresh tokens
 * (HTTP 400 `invalid_grant`) are normal at scale and never count.
 */
class ThrottleFailedClientAuthentication
{
    /** Failures a minute for one client ID from one address. */
    public const int PER_CLIENT = 20;

    /** Failures a minute from one address, whatever client ID it tries. */
    public const int PER_ADDRESS = 60;

    public function handle(Request $request, Closure $next): Response
    {
        $client = (string) ($request->input('client_id') ?? $request->getUser() ?? '');
        $keys = [
            'token-auth:'.sha1($client.'|'.$request->ip()) => self::PER_CLIENT,
            'token-auth-ip:'.$request->ip() => self::PER_ADDRESS,
        ];

        foreach ($keys as $key => $max) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                return response()->json([
                    'error' => 'invalid_client',
                    'error_description' => 'Too many failed client authentications. Try again later.',
                ], 429, ['Retry-After' => RateLimiter::availableIn($key)]);
            }
        }

        $response = $next($request);

        if ($response->getStatusCode() === 401) {
            foreach (array_keys($keys) as $key) {
                RateLimiter::hit($key, 60);
            }
        }

        return $response;
    }
}
