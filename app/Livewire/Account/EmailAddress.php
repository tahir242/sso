<?php

namespace App\Livewire\Account;

use App\Domain\Identity\Models\User;
use App\Domain\Settings\GeneralSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * One-time step after sign-in for accounts without an email address:
 * every account needs one for password recovery and security alerts.
 */
#[Layout('layouts::auth')]
#[Title('Add your email address')]
class EmailAddress extends Component
{
    public string $email = '';

    public function mount(): void
    {
        $this->email = (string) auth()->user()->email;
    }

    public function save(): void
    {
        /** @var User $user */
        $user = auth()->user();

        $this->email = trim($this->email);

        $this->validate([
            'email' => ['required', 'string', 'email:rfc', 'max:191', Rule::unique('users', 'email')->ignore($user->getKey())],
        ], attributes: ['email' => 'email address']);

        $user->forceFill(['email' => mb_strtolower($this->email)])->save();

        $this->redirect(session()->pull('url.intended', route('home')));
    }

    public function render(GeneralSettings $settings): View
    {
        return view('livewire.account.email-address', [
            'settings' => $settings,
            'user' => auth()->user(),
        ]);
    }
}
