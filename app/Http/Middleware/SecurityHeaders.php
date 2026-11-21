<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Browser hardening headers on every response.
 *
 * - The SSO is never framed (clickjacking of the login page).
 * - The CSP allows what Livewire, Alpine and Filament need ('unsafe-eval'
 *   for Alpine expressions, inline styles/scripts they inject) and the
 *   Bunny font CDN Filament uses; everything else must come from here.
 *   The API reference (/docs/api) loads its viewer from a CDN, so it gets
 *   no CSP.
 * - HSTS only over HTTPS.
 */
class SecurityHeaders
{
    private const CSP = [
        "default-src 'self'",
        "script-src 'self' 'unsafe-inline' 'unsafe-eval'",
        "style-src 'self' 'unsafe-inline' https://fonts.bunny.net",
        "font-src 'self' data: https://fonts.bunny.net",
        "img-src 'self' data: blob:",
        "connect-src 'self'",
        "worker-src 'self' blob:", // FilePond (file uploads) runs image-preview workers from blob: URLs
        "object-src 'none'",
        "base-uri 'self'",
        "frame-ancestors 'none'",
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        header_remove('X-Powered-By');
        $response->headers->remove('X-Powered-By');

        $headers = $response->headers;
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('Referrer-Policy', 'same-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');

        // A response with its own, stricter policy (the logo) keeps it.
        if (! $request->is('docs/api', 'docs/api.json') && ! $headers->has('Content-Security-Policy')) {
            $headers->set('Content-Security-Policy', implode('; ', self::CSP));
        }

        if ($request->isSecure()) {
            // Browsers ignore (and log an error for) COOP on plain HTTP, so it is HTTPS-only.
            $headers->set('Cross-Origin-Opener-Policy', 'same-origin');
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // Pages with tokens, codes or personal data must not be stored by browsers or proxies.
        // The public logo is the same for everyone and stays cacheable.
        if (($request->is('oauth/*', 'login*', 'account/*', 'api/*') || $request->user()) && ! $request->routeIs('branding.logo')) {
            $headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }
}
