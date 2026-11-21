<?php

namespace App\Http\Controllers\Oidc;

use App\Domain\Federation\Models\Application;
use App\Domain\Federation\Oidc\Issuer;
use App\Domain\Federation\Oidc\SigningKeys;
use App\Domain\Identity\Actions\EndSsoSession;
use App\Domain\Identity\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Lcobucci\JWT\UnencryptedToken;
use Lcobucci\JWT\Validation\Constraint\IssuedBy;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Throwable;

/**
 * OIDC RP-Initiated Logout (`end_session_endpoint`).
 *
 * With a valid `id_token_hint` for the signed-in user the SSO session ends
 * immediately; otherwise the user confirms first, so a third-party page
 * cannot silently sign people out. `post_logout_redirect_uri` must be
 * registered for the application.
 */
class EndSessionController
{
    public function show(Request $request, EndSsoSession $endSession, SigningKeys $keys): View|RedirectResponse
    {
        $hint = $this->parseHint($request->input('id_token_hint'), $keys);
        $application = $this->application($request, $hint);
        $redirect = $this->redirectUri($request, $application);

        /** @var User|null $user */
        $user = $request->user();

        if ($user && $hint?->claims()->get('sub') !== (string) $user->ulid) {
            $request->session()->put('oidc.logout', ['redirect' => $redirect, 'state' => $request->input('state')]);

            return view('oidc.confirm-logout', ['application' => $application, 'user' => $user]);
        }

        if ($user) {
            $endSession->handle($request);
        }

        return $this->finish($redirect, $request->input('state'));
    }

    public function confirm(Request $request, EndSsoSession $endSession): RedirectResponse
    {
        $pending = $request->session()->pull('oidc.logout', []);

        $endSession->handle($request);

        return $this->finish($pending['redirect'] ?? null, $pending['state'] ?? null);
    }

    private function finish(?string $redirect, mixed $state): RedirectResponse
    {
        if (! $redirect) {
            return redirect()->route('login')->with('status', __('You have been signed out.'));
        }

        $query = is_string($state) && $state !== '' ? (str_contains($redirect, '?') ? '&' : '?').http_build_query(['state' => $state]) : '';

        return redirect()->away($redirect.$query);
    }

    private function parseHint(mixed $hint, SigningKeys $keys): ?UnencryptedToken
    {
        if (! is_string($hint) || $hint === '') {
            return null;
        }

        try {
            $config = $keys->jwt();
            $token = $config->parser()->parse($hint);

            // Expired hints are fine (the user may have been idle); forged ones are not.
            $valid = $token instanceof UnencryptedToken && $config->validator()->validate(
                $token,
                new SignedWith($config->signer(), $config->verificationKey()),
                new IssuedBy(Issuer::url()),
            );

            return $valid ? $token : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function application(Request $request, ?UnencryptedToken $hint): ?Application
    {
        $clientId = $hint?->claims()->get('aud')[0] ?? $request->input('client_id');

        return is_string($clientId) && Str::isUuid($clientId) ? Application::where('oauth_client_id', $clientId)->first() : null;
    }

    private function redirectUri(Request $request, ?Application $application): ?string
    {
        $uri = $request->input('post_logout_redirect_uri');

        return is_string($uri) && in_array($uri, $application?->post_logout_redirect_uris ?? [], true) ? $uri : null;
    }
}
