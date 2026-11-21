<?php

namespace App\Livewire\Auth;

use App\Domain\Identity\Actions\CompleteLogin;
use App\Domain\Identity\Actions\EnableTwoFactor;
use App\Domain\Identity\Auth\PendingLogin;
use App\Domain\Identity\Mfa\TotpAuthenticator;
use App\Livewire\Auth\Concerns\UsesAuthShell;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Mandatory enrolment during sign-in when policy requires MFA
 * (administrators, or sign-in from outside the hospital network).
 */
#[Layout('layouts::auth')]
#[Title('Set up two-step verification')]
class TwoFactorSetup extends Component
{
    use UsesAuthShell;

    private const string SECRET = 'auth.mfa_setup_secret';

    public string $code = '';

    /** @var array<int, string> shown once after enabling */
    #[Locked]
    public array $recoveryCodes = [];

    public function mount(TotpAuthenticator $totp): void
    {
        if (! $user = $this->pendingUserOrRestart()) {
            return;
        }

        if ($user->hasTwoFactorEnabled()) {
            $this->redirectRoute('login.two-factor');

            return;
        }

        // The secret lives in the session until it is confirmed.
        if (! session()->has(self::SECRET)) {
            session()->put(self::SECRET, $totp->generateSecret());
        }
    }

    public function confirm(EnableTwoFactor $enable): void
    {
        $this->validate(['code' => 'required|string|max:10']);

        if (! $user = $this->pendingUserOrRestart()) {
            return;
        }

        $this->recoveryCodes = $enable->handle($user, (string) session(self::SECRET), $this->code);
        session()->forget(self::SECRET);
        $this->reset('code');
    }

    public function finish(CompleteLogin $completeLogin): void
    {
        if (! $user = $this->pendingUserOrRestart()) {
            return;
        }

        if (! $user->hasTwoFactorEnabled()) {
            return;
        }

        $this->redirect($completeLogin->handle($user, mfaVerified: true));
    }

    public function render(TotpAuthenticator $totp): View
    {
        $user = PendingLogin::user();
        $secret = session(self::SECRET);

        return view('livewire.auth.two-factor-setup', [
            ...$this->shellData(),
            'qrCode' => $user && $secret ? $totp->qrCodeSvg($user, $secret) : null,
            'secret' => $secret ? trim(chunk_split($secret, 4, ' ')) : null,
        ]);
    }
}
