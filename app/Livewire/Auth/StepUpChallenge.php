<?php

namespace App\Livewire\Auth;

use App\Domain\Identity\Actions\CompleteLogin;
use App\Domain\Identity\Mfa\SecondFactorResult;
use App\Domain\Identity\Mfa\VerifySecondFactor;
use App\Domain\Identity\Models\User;
use App\Livewire\Auth\Concerns\UsesAuthShell;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * "Verify it's you" for an already signed-in user, e.g. before opening an
 * application that requires two-step verification.
 */
#[Layout('layouts::auth')]
#[Title('Verify it’s you')]
class StepUpChallenge extends Component
{
    use UsesAuthShell;

    public string $code = '';

    public bool $usingRecoveryCode = false;

    public function mount(): void
    {
        if (! $this->user()->hasTwoFactorEnabled()) {
            session()->flash('status', __('This application requires two-step verification. Set it up below, then open the application again.'));
            $this->redirectRoute('account.security');
        }
    }

    public function toggleRecovery(): void
    {
        $this->usingRecoveryCode = ! $this->usingRecoveryCode;
        $this->reset('code');
        $this->resetErrorBag();
    }

    public function verify(VerifySecondFactor $verifier): void
    {
        $this->validate(['code' => 'required|string|max:20']);

        switch ($verifier->handle($this->user(), $this->code, $this->usingRecoveryCode)) {
            case SecondFactorResult::TooManyAttempts:
                throw ValidationException::withMessages(['code' => __('Too many incorrect codes. Try again in a few minutes.')]);
            case SecondFactorResult::Invalid:
                $this->reset('code');

                throw ValidationException::withMessages(['code' => VerifySecondFactor::invalidMessage($this->usingRecoveryCode)]);
            case SecondFactorResult::Valid:
                session()->put(CompleteLogin::MFA_AT, now()->getTimestamp());
                $this->redirect(session()->pull('url.intended', route('home')));
        }
    }

    public function render(): View
    {
        return view('livewire.auth.two-factor-challenge', [
            ...$this->shellData(),
            'canTrustDevice' => false,
            'trustDays' => 0,
            'cancelUrl' => route('home'),
        ]);
    }

    private function user(): User
    {
        /** @var User */
        return auth()->user();
    }
}
