<?php

namespace App\Domain\Identity\Mfa;

use App\Domain\Identity\Models\User;
use Illuminate\Http\Request;

/**
 * MFA decision made after the password is verified. Two-step verification is
 * the user's own choice (Account → Security); nothing forces it on.
 *
 * - User has MFA enabled and this browser is not trusted -> challenge.
 * - Otherwise -> sign in.
 */
final readonly class MfaPolicy
{
    public function __construct(
        private TrustedDeviceManager $devices,
    ) {}

    public function nextStep(User $user, Request $request): LoginStep
    {
        if ($user->hasTwoFactorEnabled() && ! $this->devices->isTrusted($user, $request)) {
            return LoginStep::ChallengeMfa;
        }

        return LoginStep::Complete;
    }
}
