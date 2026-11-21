<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Audit\Enums\AuthEventType;
use App\Domain\Audit\Models\AuthEvent;
use App\Domain\Identity\Mfa\RecoveryCodes;
use App\Domain\Identity\Mfa\TotpAuthenticator;
use App\Domain\Identity\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Confirms a freshly scanned authenticator secret with a first code, then
 * turns MFA on and returns the recovery codes to show once.
 */
final readonly class EnableTwoFactor
{
    public function __construct(
        private TotpAuthenticator $totp,
        private RecoveryCodes $recoveryCodes,
    ) {}

    /**
     * @return array<int, string> plain recovery codes
     *
     * @throws ValidationException
     */
    public function handle(User $user, string $secret, string $code, string $field = 'code'): array
    {
        if (! $this->totp->verify($user, $secret, $code)) {
            throw ValidationException::withMessages([$field => __('That code is not valid. Check the time on your phone and try the newest code.')]);
        }

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
        ])->save();

        AuthEvent::record(AuthEventType::MfaEnabled, $user);

        return $this->recoveryCodes->regenerate($user);
    }
}
