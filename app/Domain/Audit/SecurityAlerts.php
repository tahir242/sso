<?php

namespace App\Domain\Audit;

use App\Domain\Audit\Enums\AuthEventType;
use App\Domain\Audit\Models\AuthEvent;
use App\Domain\Audit\Notifications\AccountSecurityAlert;
use App\Domain\Audit\Notifications\SecurityTeamAlert;
use App\Domain\Identity\Mfa\TrustedDeviceManager;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

/**
 * Turns security events into alerts:
 *
 * To the user (about their own account):
 *   - sign-in from a new browser/computer, password changed,
 *     two-step verification turned off or reset, account locked out
 * To the security team:
 *   - account or IP lockouts, MFA reset by an admin, admin role granted
 *
 * Each alert is sent at most once per 15 minutes per subject, so a burst of
 * events (e.g. a password-guessing run) does not flood anyone.
 */
final class SecurityAlerts
{
    public const array TEAM_ROLES = ['it-security', 'super-admin'];

    private const int THROTTLE_SECONDS = 900;

    public function handleAuthEvent(AuthEvent $event): void
    {
        $user = $event->user;
        $when = $event->created_at->format('D j M, H:i');
        $device = TrustedDeviceManager::describe((string) $event->user_agent);

        match ($event->event) {
            AuthEventType::LoginSucceeded => ($event->meta['new_device'] ?? false) && $user
                ? $this->toUser($user, 'new-device', __('New sign-in to your account'),
                    __('Your account was used to sign in from :device (IP :ip) on :when.', ['device' => $device, 'ip' => $event->ip_address, 'when' => $when]))
                : null,

            AuthEventType::PasswordChanged => $user
                ? $this->toUser($user, 'password', __('Your password was changed'),
                    __('The password for your account was changed on :when.', ['when' => $when]))
                : null,

            AuthEventType::MfaDisabled => $user
                ? $this->toUser($user, 'mfa-off', __('Two-step verification was turned off'),
                    __('Two-step verification was turned off for your account on :when.', ['when' => $when]))
                : null,

            AuthEventType::MfaReset => $this->mfaReset($event, $when),

            AuthEventType::LoginLocked => $this->lockout($event, $when),

            default => null,
        };
    }

    public function adminRoleGranted(User $user, array $roles, ?User $by): void
    {
        $this->toTeam("role:{$user->getKey()}:".implode(',', $roles), __('Administrator role granted'),
            __(':name (:username) was given the :roles role(s) by :by.', [
                'name' => $user->name,
                'username' => $user->username,
                'roles' => implode(', ', $roles),
                'by' => $by?->name ?? __('the system'),
            ]), exclude: $user);
    }

    private function mfaReset(AuthEvent $event, string $when): void
    {
        if (! $event->user) {
            return;
        }

        $by = $event->meta['by'] ?? __('an administrator');

        $this->toUser($event->user, 'mfa-reset', __('Your two-step verification was reset'),
            __('An administrator (:by) reset two-step verification for your account on :when. You will be asked to set it up again.', ['by' => $by, 'when' => $when]));

        $this->toTeam("mfa-reset:{$event->user_id}", __('Two-step verification reset'),
            __(':by reset two-step verification for :name (:username).', ['by' => $by, 'name' => $event->user->name, 'username' => $event->user->username]),
            exclude: $event->user);
    }

    private function lockout(AuthEvent $event, string $when): void
    {
        $scope = $event->meta['scope'] ?? 'account';

        if ($scope === 'ip') {
            $this->toTeam("lockout-ip:{$event->ip_address}", __('Sign-ins blocked from one address'),
                __('Too many failed sign-ins from IP :ip (last tried “:who”) on :when. Sign-ins from this address are paused.', [
                    'ip' => $event->ip_address, 'who' => $event->identifier, 'when' => $when,
                ]));

            return;
        }

        $user = $event->user ?? User::query()->where('username', $event->identifier)->orWhere('emp_id', $event->identifier)->first();

        if ($user) {
            $this->toUser($user, 'lockout', __('Someone tried to sign in to your account'),
                __('There were several wrong passwords for your account (from IP :ip) on :when, so sign-in is paused for a few minutes.', ['ip' => $event->ip_address, 'when' => $when]));
        }

        $this->toTeam('lockout:'.mb_strtolower((string) $event->identifier), __('Account locked out'),
            __('Too many failed sign-ins for “:who”:name from IP :ip on :when.', [
                'who' => $event->identifier,
                'name' => $user ? " ({$user->name})" : '',
                'ip' => $event->ip_address,
                'when' => $when,
            ]), exclude: $user);
    }

    private function toUser(User $user, string $rule, string $heading, string $message): void
    {
        if ($this->throttled("user:{$user->getKey()}:{$rule}")) {
            return;
        }

        $user->notify(new AccountSecurityAlert($heading, $message));
    }

    private function toTeam(string $key, string $heading, string $message, ?User $exclude = null): void
    {
        if ($this->throttled("team:{$key}")) {
            return;
        }

        $team = $this->team()->reject(fn (User $u) => $exclude && $u->is($exclude));

        Notification::send($team, new SecurityTeamAlert($heading, $message));
    }

    /**
     * @return Collection<int, User>
     */
    private function team(): Collection
    {
        $roles = Role::whereIn('name', self::TEAM_ROLES)->pluck('name')->all();

        return $roles === [] ? collect() : User::role($roles)->where('status', 'active')->get();
    }

    private function throttled(string $key): bool
    {
        return ! Cache::add("security-alert:{$key}", true, self::THROTTLE_SECONDS);
    }
}
