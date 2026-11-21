<?php

use App\Domain\Audit\Enums\AuthEventType;
use App\Domain\Audit\Models\AuthEvent;
use App\Domain\Audit\Notifications\AccountSecurityAlert;
use App\Domain\Audit\Notifications\SecurityTeamAlert;
use App\Domain\Identity\Actions\DisableTwoFactor;
use App\Domain\Identity\Models\User;
use App\Domain\Settings\SecuritySettings;
use App\Livewire\Auth\Login;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Notification::fake();
    Role::findOrCreate('it-security', 'web');
    Role::findOrCreate('super-admin', 'web');
    $this->security = tap(User::factory()->create(), fn (User $u) => $u->assignRole('it-security'));
});

function login(User $user, ?string $deviceCookie = null): void
{
    $test = $deviceCookie ? Livewire::withCookie('sso_device', $deviceCookie) : Livewire::withoutLazyLoading();

    $test->test(Login::class)
        ->set('identifier', $user->username)
        ->set('password', 'correct-horse-battery')
        ->call('authenticate');

    auth()->logout();
}

it('alerts the user about a sign-in from a new browser, not the first or a known one', function () {
    $user = User::factory()->create(['password' => 'correct-horse-battery']);
    $known = str_repeat('k', 48);

    login($user, $known);   // very first sign-in: no alert
    login($user, $known);   // same browser: no alert
    Notification::assertNotSentTo($user, AccountSecurityAlert::class);

    login($user, str_repeat('n', 48)); // another browser
    Notification::assertSentTo($user, AccountSecurityAlert::class, fn ($n) => str_contains($n->heading, 'New sign-in'));
});

it('alerts the user and the security team about a lockout, once', function () {
    $user = User::factory()->create(['password' => 'correct-horse-battery']);
    $max = app(SecuritySettings::class)->lockout_max_attempts;

    foreach (range(1, $max + 3) as $ignored) {
        Livewire::test(Login::class)->set('identifier', $user->username)->set('password', 'wrong')->call('authenticate');
    }

    Notification::assertSentToTimes($user, AccountSecurityAlert::class, 1);
    Notification::assertSentToTimes($this->security, SecurityTeamAlert::class, 1);
});

it('tells the security team when someone gets an admin role', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('auditor', 'web'));

    Notification::assertSentTo($this->security, SecurityTeamAlert::class, fn ($n) => str_contains($n->message, 'auditor'));
    Notification::assertNotSentTo($user, SecurityTeamAlert::class);
});

it('does not alert for non-admin roles', function () {
    User::factory()->create()->assignRole('it-security');

    Notification::assertNotSentTo($this->security, SecurityTeamAlert::class);
});

it('alerts the user and the team when an admin resets two-step verification', function () {
    [$user] = userWithMfa();
    $admin = User::factory()->create(['name' => 'Helpdesk Hamid']);

    app(DisableTwoFactor::class)->handle($user, resetBy: $admin);

    Notification::assertSentTo($user, AccountSecurityAlert::class);
    Notification::assertSentTo($this->security, SecurityTeamAlert::class);
});

it('alerts the user when their password changes', function () {
    $user = User::factory()->create();

    AuthEvent::record(AuthEventType::PasswordChanged, $user);

    Notification::assertSentTo($user, AccountSecurityAlert::class, fn ($n) => str_contains($n->heading, 'password'));
});
