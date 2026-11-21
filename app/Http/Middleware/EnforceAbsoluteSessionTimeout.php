<?php

namespace App\Http\Middleware;

use App\Domain\Audit\Enums\AuthEventType;
use App\Domain\Identity\Actions\CompleteLogin;
use App\Domain\Identity\Actions\EndSsoSession;
use App\Domain\Settings\SessionSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ends an SSO session (and its application sessions) after the configured
 * maximum length, however active it is. The idle timeout is Laravel's
 * session lifetime.
 */
class EnforceAbsoluteSessionTimeout
{
    public function __construct(
        private readonly SessionSettings $settings,
        private readonly EndSsoSession $endSession,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            return $next($request);
        }

        // Sessions not started by CompleteLogin (none expected) start counting now.
        $startedAt = $request->session()->get(CompleteLogin::STARTED_AT)
            ?? tap(now()->getTimestamp(), fn (int $now) => $request->session()->put(CompleteLogin::STARTED_AT, $now));

        if (now()->getTimestamp() - $startedAt > $this->settings->absolute_timeout_hours * 3600) {
            $this->endSession->handle($request, AuthEventType::SessionExpired);

            return redirect()->route('login')->with('status', __('Your session has ended. Please sign in again.'));
        }

        return $next($request);
    }
}
