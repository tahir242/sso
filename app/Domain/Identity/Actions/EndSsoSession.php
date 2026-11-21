<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Audit\Enums\AuthEventType;
use App\Domain\Audit\Models\AuthEvent;
use App\Domain\Federation\Actions\EndClientSessions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Signs the current browser out of the SSO and of every application that was
 * opened in this session (single logout).
 */
final readonly class EndSsoSession
{
    public function __construct(private EndClientSessions $clientSessions) {}

    public function handle(Request $request, AuthEventType $reason = AuthEventType::Logout): void
    {
        if ($user = $request->user()) {
            AuthEvent::record($reason, $user);
        }

        $this->clientSessions->forSid($request->session()->get(CompleteLogin::SID));

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
