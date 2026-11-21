<?php

namespace App\Domain\Identity\Mfa;

use App\Domain\Identity\Models\User;
use App\Domain\Settings\MfaSettings;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Risk-based MFA decision made after the password is verified.
 *
 * - Policy requires MFA (admin role, or sign-in from outside the hospital
 *   network) and the user has not enrolled  -> must enrol now.
 * - User has MFA enabled and this browser is not trusted -> challenge.
 * - Otherwise -> sign in.
 */
final readonly class MfaPolicy
{
    public function __construct(
        private MfaSettings $settings,
        private TrustedDeviceManager $devices,
    ) {}

    public function nextStep(User $user, Request $request): LoginStep
    {
        if ($this->isRequired($user, (string) $request->ip()) && ! $user->hasTwoFactorEnabled()) {
            return LoginStep::EnrolMfa;
        }

        if ($user->hasTwoFactorEnabled() && ! $this->devices->isTrusted($user, $request)) {
            return LoginStep::ChallengeMfa;
        }

        return LoginStep::Complete;
    }

    public function isRequired(User $user, string $ip): bool
    {
        if ($this->settings->required_for_admins && $user->isAdministrator()) {
            return true;
        }

        return $this->settings->required_off_network && ! $this->isTrustedNetwork($ip);
    }

    /**
     * Whether the user may switch MFA off themselves (from inside the network).
     */
    public function canDisable(User $user): bool
    {
        return ! ($this->settings->required_for_admins && $user->isAdministrator());
    }

    public function isTrustedNetwork(string $ip): bool
    {
        return $ip !== '' && IpUtils::checkIp($ip, $this->settings->trusted_networks);
    }
}
