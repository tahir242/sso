<?php

namespace App\Livewire\Account;

use App\Domain\Audit\Models\AuthEvent;
use App\Domain\Identity\Actions\ChangePassword;
use App\Domain\Identity\Actions\DisableTwoFactor;
use App\Domain\Identity\Actions\EnableTwoFactor;
use App\Domain\Identity\Enums\AuthSource;
use App\Domain\Identity\Mfa\MfaPolicy;
use App\Domain\Identity\Mfa\RecoveryCodes;
use App\Domain\Identity\Mfa\TotpAuthenticator;
use App\Domain\Identity\Mfa\TrustedDeviceManager;
use App\Domain\Identity\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts::app')]
#[Title('Security')]
class Security extends Component
{
    private const string SETUP_SECRET = 'account.mfa_setup_secret';

    public bool $settingUp = false;

    public string $code = '';

    /** @var array<int, string> */
    #[Locked]
    public array $newRecoveryCodes = [];

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    // ── Two-step verification ──────────────────────────────────────────

    public function startSetup(TotpAuthenticator $totp): void
    {
        session()->put(self::SETUP_SECRET, $totp->generateSecret());
        $this->settingUp = true;
        $this->reset('code', 'newRecoveryCodes');
    }

    public function cancelSetup(): void
    {
        session()->forget(self::SETUP_SECRET);
        $this->settingUp = false;
        $this->reset('code');
    }

    public function confirmSetup(EnableTwoFactor $enable): void
    {
        $this->validate(['code' => 'required|string|max:10']);

        $this->newRecoveryCodes = $enable->handle($this->user(), (string) session(self::SETUP_SECRET), $this->code);
        session()->forget(self::SETUP_SECRET);
        session()->put('auth.mfa_at', now()->getTimestamp());
        $this->settingUp = false;
        $this->reset('code');
    }

    public function regenerateRecoveryCodes(TotpAuthenticator $totp, RecoveryCodes $recoveryCodes): void
    {
        $this->requireCurrentCode($totp);

        $this->newRecoveryCodes = $recoveryCodes->regenerate($this->user());
    }

    public function disableTwoFactor(TotpAuthenticator $totp, MfaPolicy $policy, DisableTwoFactor $disable): void
    {
        abort_unless($policy->canDisable($this->user()), 403);

        $this->requireCurrentCode($totp);

        $disable->handle($this->user());
        $this->reset('newRecoveryCodes');
    }

    private function requireCurrentCode(TotpAuthenticator $totp): void
    {
        $this->validate(['code' => 'required|string|max:10']);
        $user = $this->user();

        if (! $totp->verify($user, (string) $user->two_factor_secret, $this->code)) {
            throw ValidationException::withMessages(['code' => __('That code is not valid.')]);
        }

        $this->reset('code');
    }

    // ── Devices and sessions ───────────────────────────────────────────

    public function revokeDevice(int $deviceId, TrustedDeviceManager $devices): void
    {
        $devices->revoke($this->user()->trustedDevices()->findOrFail($deviceId));
    }

    public function signOutOtherSessions(): void
    {
        DB::table('sessions')
            ->where('user_id', $this->user()->getKey())
            ->where('id', '!=', session()->getId())
            ->delete();

        session()->flash('sessions-status', __('Other sessions have been signed out.'));
    }

    // ── Password ───────────────────────────────────────────────────────

    public function changePassword(ChangePassword $changePassword): void
    {
        $changePassword->handle($this->user(), $this->current_password, $this->password, $this->password_confirmation, session()->getId());

        $this->reset('current_password', 'password', 'password_confirmation');
        session()->flash('password-status', __('Password changed. Other sessions were signed out.'));
    }

    public function render(TotpAuthenticator $totp, MfaPolicy $policy, RecoveryCodes $recoveryCodes): View
    {
        $user = $this->user();
        $secret = session(self::SETUP_SECRET);

        return view('livewire.account.security', [
            'user' => $user,
            'mfaRequired' => $policy->isRequired($user, (string) request()->ip()),
            'canDisableMfa' => $policy->canDisable($user),
            'recoveryCodesLeft' => $recoveryCodes->remaining($user),
            'qrCode' => $this->settingUp && $secret ? $totp->qrCodeSvg($user, $secret) : null,
            'secret' => $this->settingUp && $secret ? trim(chunk_split($secret, 4, ' ')) : null,
            'devices' => $user->trustedDevices()->valid()->latest('last_used_at')->get(),
            'sessions' => DB::table('sessions')->where('user_id', $user->getKey())->orderByDesc('last_activity')->get()
                ->map(fn (object $row) => (object) [
                    'current' => $row->id === session()->getId(),
                    'device' => TrustedDeviceManager::describe((string) $row->user_agent),
                    'ip' => $row->ip_address,
                    'last_active' => CarbonImmutable::createFromTimestamp($row->last_activity),
                ]),
            'events' => AuthEvent::where('user_id', $user->getKey())->latest('created_at')->limit(8)->get(),
            'isLocalAccount' => $user->auth_source === AuthSource::Local,
        ]);
    }

    private function user(): User
    {
        /** @var User */
        return auth()->user();
    }
}
