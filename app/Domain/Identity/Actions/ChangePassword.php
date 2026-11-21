<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Audit\Enums\AuthEventType;
use App\Domain\Audit\Models\AuthEvent;
use App\Domain\Identity\Enums\AuthSource;
use App\Domain\Identity\Models\User;
use App\Domain\Settings\SecuritySettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Self-service password change for local accounts. Other sessions of the
 * user are signed out afterwards. (AD password changes over LDAPS come with
 * the self-service password reset work.)
 */
final readonly class ChangePassword
{
    public function __construct(private SecuritySettings $security) {}

    /**
     * @throws ValidationException
     */
    public function handle(User $user, string $current, string $new, string $confirmation, string $currentSessionId): void
    {
        if ($user->auth_source !== AuthSource::Local) {
            throw ValidationException::withMessages(['current_password' => __('This account’s password is managed by Windows (Active Directory).')]);
        }

        Validator::make(
            ['current_password' => $current, 'password' => $new, 'password_confirmation' => $confirmation],
            [
                'current_password' => ['required', 'string'],
                'password' => ['required', 'string', 'confirmed', 'different:current_password', Password::min($this->security->password_min_length)->letters()->numbers()],
            ],
            attributes: ['password' => 'new password'],
        )->validate();

        $valid = $user->legacy_md5
            ? hash_equals((string) $user->getAuthPassword(), md5($current))
            : Hash::check($current, (string) $user->getAuthPassword());

        if (! $valid) {
            throw ValidationException::withMessages(['current_password' => __('Your current password is incorrect.')]);
        }

        $user->forceFill([
            'password' => $new,
            'legacy_md5' => false,
            'password_changed_at' => now(),
        ])->save();

        DB::table('sessions')->where('user_id', $user->getKey())->where('id', '!=', $currentSessionId)->delete();

        AuthEvent::record(AuthEventType::PasswordChanged, $user);
    }
}
