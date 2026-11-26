<?php

namespace App\Http\Middleware;

use App\Domain\Federation\Models\Application;
use App\Domain\Federation\Models\ClientSession;
use App\Domain\Federation\Oidc\AuthCodeRepository;
use App\Domain\Identity\Actions\CompleteLogin;
use App\Domain\Identity\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs in front of Passport's /oauth/authorize for signed-in users:
 *
 * 1. the client must be an active registered application,
 * 2. the user must be assigned to it (else a "no access" page),
 * 3. `max_age` is enforced (re-authentication),
 * 4. the (SSO session, client) pair is recorded for `sid`/`auth_time`/`amr`
 *    and back-channel logout (on sign-out or when the SSO session expires).
 *
 * Guests pass straight through: Passport sends them to /login and back.
 */
class AuthorizeApplicationAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $clientId = (string) $request->query('client_id');
        // Client IDs are UUIDs; SQL Server errors on comparing anything else with that column.
        $application = Str::isUuid($clientId) ? Application::query()->active()->where('oauth_client_id', $clientId)->first() : null;

        if (! $application) {
            // Unknown or disabled client (disabled apps have a revoked client):
            // Passport returns the standard OAuth error.
            return $next($request);
        }

        // PKCE (S256) is required, except for confidential (secret-holding,
        // server-side) clients that an administrator explicitly exempted
        // because the product cannot send it (e.g. Moodle, Koha). A
        // challenge that is sent must always be S256 ("plain" is refused).
        $challenge = $request->query('code_challenge');
        $pkceOptional = ! $application->require_pkce && $application->client?->confidential();

        if ((is_string($challenge) && $request->query('code_challenge_method') !== 'S256')
            || (! is_string($challenge) && ! $pkceOptional)) {
            return response()->view('oidc.error', [
                'message' => __(':app is not configured correctly (PKCE with S256 is required). Please report this to the IT helpdesk.', ['app' => $application->name]),
            ], 400);
        }

        /** @var User|null $user */
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $session = $request->session();

        if ($this->exceedsMaxAge($request)) {
            Auth::guard('web')->logout();
            $session->invalidate();
            $session->regenerateToken();
            $session->put('url.intended', $request->fullUrl());

            return redirect()->route('login');
        }

        if (! $application->canBeOpenedBy($user)) {
            return response()->view('oidc.no-access', ['application' => $application], 403);
        }

        $clientSession = ClientSession::updateOrCreate(
            ['sid' => $this->sid($request), 'oauth_client_id' => $application->oauth_client_id],
            [
                'session_id' => $session->getId(),
                'user_id' => $user->getKey(),
                'auth_time' => now()->setTimestamp((int) $session->get(CompleteLogin::STARTED_AT, now()->getTimestamp())),
                'amr' => $session->get(CompleteLogin::MFA_AT) ? ['pwd', 'otp'] : ['pwd'],
                'last_authorized_at' => now(),
                'logged_out_at' => null,
            ],
        );

        $request->attributes->set(AuthCodeRepository::CLIENT_SESSION_ATTRIBUTE, $clientSession->getKey());

        return $next($request);
    }

    private function exceedsMaxAge(Request $request): bool
    {
        $maxAge = $request->query('max_age');
        $startedAt = $request->session()->get(CompleteLogin::STARTED_AT);

        return is_numeric($maxAge) && $startedAt && now()->getTimestamp() - $startedAt > (int) $maxAge;
    }

    private function sid(Request $request): string
    {
        // Sessions started before `sid` existed get one now.
        return $request->session()->get(CompleteLogin::SID)
            ?? tap(bin2hex(random_bytes(20)), fn (string $sid) => $request->session()->put(CompleteLogin::SID, $sid));
    }
}
