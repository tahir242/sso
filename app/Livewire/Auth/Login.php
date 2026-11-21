<?php

namespace App\Livewire\Auth;

use App\Domain\Audit\Enums\AuthEventType;
use App\Domain\Audit\Models\AuthEvent;
use App\Domain\Engagement\Services\LoginGreeting;
use App\Domain\Identity\Actions\AttemptLogin;
use App\Domain\Identity\Actions\CompleteLogin;
use App\Domain\Identity\Auth\PendingLogin;
use App\Domain\Identity\Auth\RecentAccounts;
use App\Domain\Identity\Mfa\LoginStep;
use App\Domain\Identity\Mfa\MfaPolicy;
use App\Domain\Identity\Models\User;
use App\Domain\Settings\GeneralSettings;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Sign-in screen. Like the Windows lock screen, accounts that signed in on
 * this browser before are offered as profiles: pick one and type only the
 * password, or choose "Other user" for the full form.
 */
#[Layout('layouts::auth')]
#[Title('Sign in')]
class Login extends Component
{
    #[Validate('required|string|max:100', as: 'username or Employee ID')]
    public string $identifier = '';

    // An empty password is stopped in the browser (never sent); this rule is
    // the server-side backstop and does not count towards the lock.
    #[Validate('required|string|max:255', message: 'Enter your password.')]
    public string $password = '';

    /** @var list<array{username: string, name: string, initials: string, color: string}> */
    #[Locked]
    public array $recent = [];

    /** Username of the chosen profile; null shows the full "Other user" form. */
    #[Locked]
    public ?string $selected = null;

    public function mount(): void
    {
        $this->recent = RecentAccounts::all();

        if ($this->recent !== []) {
            // The view autofocuses the password field on first load.
            $this->choose($this->recent[0]['username']);
        }
    }

    public function selectAccount(string $username): void
    {
        if ($this->choose($username)) {
            $this->dispatch('focus-password');
        }
    }

    private function choose(string $username): bool
    {
        $account = collect($this->recent)->firstWhere('username', $username);

        if (! $account) {
            return false;
        }

        $this->selected = $account['username'];
        $this->identifier = $account['username'];
        $this->reset('password');
        $this->resetValidation();

        return true;
    }

    public function otherUser(): void
    {
        $this->selected = null;
        $this->reset('identifier', 'password');
        $this->resetValidation();
        $this->dispatch('focus-identifier');
    }

    public function forgetAccount(string $username): void
    {
        RecentAccounts::forget($username);

        $this->recent = array_values(array_filter($this->recent, fn (array $a) => $a['username'] !== $username));

        if ($this->selected === $username) {
            $this->recent === [] ? $this->otherUser() : $this->selectAccount($this->recent[0]['username']);
        }
    }

    public function authenticate(AttemptLogin $attemptLogin, MfaPolicy $mfa, CompleteLogin $completeLogin): void
    {
        if ($this->selected !== null) {
            $this->identifier = $this->selected;
        }

        $this->validate();

        $user = $attemptLogin->handle($this->identifier, $this->password, (string) request()->ip());

        $this->reset('password');

        match ($mfa->nextStep($user, request())) {
            LoginStep::Complete => $this->redirect($completeLogin->handle($user, mfaVerified: false)),
            LoginStep::ChallengeMfa => $this->continueTo('login.two-factor', $user),
            LoginStep::EnrolMfa => $this->continueTo('login.two-factor.setup', $user),
        };
    }

    private function continueTo(string $route, User $user): void
    {
        PendingLogin::start($user);

        if ($route === 'login.two-factor') {
            AuthEvent::record(AuthEventType::MfaChallenged, $user);
        }

        $this->redirectRoute($route);
    }

    public function render(LoginGreeting $greeting, GeneralSettings $settings): View
    {
        return view('livewire.auth.login', [
            'greeting' => $greeting->forDate(),
            'settings' => $settings,
            'account' => $this->selected ? collect($this->recent)->firstWhere('username', $this->selected) : null,
        ]);
    }
}
