<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Audit\Enums\AuthEventType;
use App\Domain\Audit\Models\AuthEvent;
use App\Domain\Identity\Directory\ActiveDirectoryAuthenticator;
use App\Domain\Identity\Enums\AuthSource;
use App\Domain\Identity\Models\User;
use App\Domain\Settings\SecuritySettings;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * First sign-in step: verifies username/EmpID + password (local hash or AD
 * bind). It does NOT start the session; MFA may still be required.
 *
 * - Throttled per account and per IP (values from SecuritySettings).
 * - Generic error messages, so the form never reveals whether a user exists.
 * - Legacy MD5 hashes are upgraded to Argon2id on first successful login.
 */
final readonly class AttemptLogin
{
    public function __construct(
        private SecuritySettings $security,
        private ActiveDirectoryAuthenticator $activeDirectory,
    ) {}

    /**
     * @throws ValidationException
     */
    public function handle(string $identifier, string $password, string $ip): User
    {
        $identifier = trim($identifier);
        $accountKey = 'login:account:'.Str::lower($identifier);
        $ipKey = 'login:ip:'.$ip;
        $decay = $this->security->lockout_decay_minutes * 60;

        $this->ensureNotThrottled($accountKey, $this->security->lockout_max_attempts, $identifier);
        $this->ensureNotThrottled($ipKey, $this->security->ip_max_attempts, $identifier);

        $local = User::query()
            ->where('username', $identifier)
            ->orWhere('emp_id', $identifier)
            ->first();

        $user = $this->verify($local, $identifier, $password);

        if (! $user) {
            RateLimiter::hit($accountKey, $decay);
            RateLimiter::hit($ipKey, $decay);
            AuthEvent::record(AuthEventType::LoginFailed, $local, $identifier);

            throw ValidationException::withMessages([
                'identifier' => __('These credentials do not match our records.'),
            ]);
        }

        if (! $user->status->canSignIn()) {
            AuthEvent::record(AuthEventType::LoginFailed, $user, $identifier, ['reason' => 'status:'.$user->status->value]);

            throw ValidationException::withMessages([
                'identifier' => __('Your account is :status. Please contact the IT helpdesk.', ['status' => $user->status->value]),
            ]);
        }

        RateLimiter::clear($accountKey);

        return $user;
    }

    private function verify(?User $local, string $identifier, string $password): ?User
    {
        // Unknown locally, or an AD account: Active Directory decides.
        if (! $local || $local->auth_source === AuthSource::ActiveDirectory) {
            return $this->activeDirectory->authenticate($local, $identifier, $password);
        }

        $hash = $local->getAuthPassword();

        $valid = match (true) {
            ! $hash => false,
            $local->legacy_md5 => hash_equals($hash, md5($password)),
            default => Hash::check($password, $hash),
        };

        if (! $valid) {
            return null;
        }

        if ($local->legacy_md5 || Hash::needsRehash($hash)) {
            $local->forceFill(['password' => $password, 'legacy_md5' => false])->saveQuietly();
        }

        return $local;
    }

    private function ensureNotThrottled(string $key, int $maxAttempts, string $identifier): void
    {
        if (! RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            return;
        }

        AuthEvent::record(AuthEventType::LoginLocked, identifier: $identifier, meta: ['scope' => str_starts_with($key, 'login:ip:') ? 'ip' : 'account']);

        $minutes = (int) ceil(RateLimiter::availableIn($key) / 60);

        throw ValidationException::withMessages([
            'identifier' => trans_choice('Too many sign-in attempts. Try again in :count minute.|Too many sign-in attempts. Try again in :count minutes.', $minutes),
        ]);
    }
}
