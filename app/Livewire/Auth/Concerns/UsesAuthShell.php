<?php

namespace App\Livewire\Auth\Concerns;

use App\Domain\Engagement\Services\LoginGreeting;
use App\Domain\Identity\Auth\PendingLogin;
use App\Domain\Identity\Models\User;
use App\Domain\Settings\GeneralSettings;

/**
 * Shared helpers for the MFA steps of the sign-in flow.
 */
trait UsesAuthShell
{
    /**
     * @return array{greeting: array<string, mixed>|null, settings: GeneralSettings}
     */
    protected function shellData(): array
    {
        return [
            'greeting' => app(LoginGreeting::class)->forDate(),
            'settings' => app(GeneralSettings::class),
        ];
    }

    /**
     * The user waiting to finish signing in, or a redirect back to /login.
     */
    protected function pendingUserOrRestart(): ?User
    {
        if ($user = PendingLogin::user()) {
            return $user;
        }

        session()->flash('status', __('Your sign-in session expired. Please sign in again.'));
        $this->redirectRoute('login');

        return null;
    }
}
