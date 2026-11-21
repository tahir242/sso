<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Audit\Enums\AuthEventType;
use App\Domain\Audit\Models\AuthEvent;
use App\Domain\Identity\Mfa\TrustedDeviceManager;
use App\Domain\Identity\Models\User;

/**
 * Removes the authenticator, recovery codes and trusted devices.
 * Used by the user (when policy allows) and by admins ("reset MFA").
 */
final readonly class DisableTwoFactor
{
    public function __construct(private TrustedDeviceManager $devices) {}

    public function handle(User $user, ?User $resetBy = null): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        $this->devices->revokeAll($user);

        AuthEvent::record(
            $resetBy ? AuthEventType::MfaReset : AuthEventType::MfaDisabled,
            $user,
            meta: $resetBy ? ['by' => $resetBy->username] : [],
        );
    }
}
