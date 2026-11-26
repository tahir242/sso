<?php

namespace App\Livewire\Auth;

use App\Domain\Identity\Actions\CompleteLogin;
use App\Domain\Identity\Auth\PendingLogin;
use App\Domain\Identity\Mfa\SecondFactorResult;
use App\Domain\Identity\Mfa\TrustedDeviceManager;
use App\Domain\Identity\Mfa\VerifySecondFactor;
use App\Domain\Settings\MfaSettings;
use App\Livewire\Auth\Concerns\UsesAuthShell;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts::auth')]
#[Title('Verify it’s you')]
class TwoFactorChallenge extends Component
{
    use UsesAuthShell;

    public string $code = '';

    public bool $usingRecoveryCode = false;

    public bool $trustDevice = false;

    public function mount(): void
    {
        $this->pendingUserOrRestart();
    }

    public function toggleRecovery(): void
    {
        $this->usingRecoveryCode = ! $this->usingRecoveryCode;
        $this->reset('code');
        $this->resetErrorBag();
    }

    public function verify(VerifySecondFactor $verifier, TrustedDeviceManager $devices, CompleteLogin $completeLogin): void
    {
        $this->validate(['code' => 'required|string|max:20']);

        if (! $user = $this->pendingUserOrRestart()) {
            return;
        }

        switch ($verifier->handle($user, $this->code, $this->usingRecoveryCode)) {
            case SecondFactorResult::TooManyAttempts:
                PendingLogin::clear();
                session()->flash('status', __('Too many incorrect codes. Please sign in again.'));
                $this->redirectRoute('login');

                return;

            case SecondFactorResult::Invalid:
                $this->reset('code');

                throw ValidationException::withMessages(['code' => VerifySecondFactor::invalidMessage($this->usingRecoveryCode)]);
            case SecondFactorResult::Valid:
                if ($this->trustDevice) {
                    $devices->trust($user, request());
                }

                $this->redirect($completeLogin->handle($user, mfaVerified: true));
        }
    }

    public function render(TrustedDeviceManager $devices, MfaSettings $settings): View
    {
        return view('livewire.auth.two-factor-challenge', [
            ...$this->shellData(),
            'canTrustDevice' => $devices->enabled(),
            'trustDays' => $settings->trusted_device_days,
        ]);
    }
}
