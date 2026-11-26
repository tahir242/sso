<?php

namespace App\Domain\Identity\Mfa;

use App\Domain\Audit\Enums\AuthEventType;
use App\Domain\Audit\Models\AuthEvent;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Checks an authenticator or recovery code at sign-in, with a per-user attempt
 * limit.
 */
final readonly class VerifySecondFactor
{
    public const int MAX_ATTEMPTS = 5;

    public function __construct(
        private TotpAuthenticator $totp,
        private RecoveryCodes $recoveryCodes,
    ) {}

    public function handle(User $user, string $code, bool $isRecoveryCode): SecondFactorResult
    {
        $key = 'mfa:challenge:'.$user->getKey();

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            return SecondFactorResult::TooManyAttempts;
        }

        $valid = $isRecoveryCode
            ? $this->recoveryCodes->consume($user, $code)
            : $this->totp->verify($user, (string) $user->two_factor_secret, $code);

        if (! $valid) {
            RateLimiter::hit($key, 600);
            AuthEvent::record(AuthEventType::MfaFailed, $user);

            return RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)
                ? SecondFactorResult::TooManyAttempts
                : SecondFactorResult::Invalid;
        }

        RateLimiter::clear($key);
        AuthEvent::record($isRecoveryCode ? AuthEventType::MfaRecoveryCodeUsed : AuthEventType::MfaSucceeded, $user);

        return SecondFactorResult::Valid;
    }

    public static function invalidMessage(bool $isRecoveryCode): string
    {
        return $isRecoveryCode
            ? __('That recovery code is not valid or has already been used.')
            : __('That code is not valid. Try the newest code from your authenticator app.');
    }
}
