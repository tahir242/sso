<?php

use App\Domain\Identity\Auth\PendingLogin;
use App\Domain\Identity\Mfa\RecoveryCodes;
use App\Domain\Identity\Mfa\TrustedDeviceManager;
use App\Domain\Identity\Models\User;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\TwoFactorChallenge;
use App\Livewire\Auth\TwoFactorSetup;
use Illuminate\Support\Facades\Cookie;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;
use Spatie\Permission\Models\Role;

function signInWithPassword(User $user): Testable
{
    return Livewire::test(Login::class)
        ->set('identifier', $user->username)
        ->set('password', 'correct-horse-battery')
        ->call('authenticate');
}

it('asks users with two-step verification for a code', function () {
    [$user] = userWithMfa();

    signInWithPassword($user)->assertRedirect(route('login.two-factor'));

    $this->assertGuest();
    expect(PendingLogin::user()?->is($user))->toBeTrue();
});

it('completes sign-in with a valid authenticator code', function () {
    [$user, $secret] = userWithMfa();
    PendingLogin::start($user);

    Livewire::test(TwoFactorChallenge::class)
        ->set('code', currentOtp($secret))
        ->call('verify')
        ->assertRedirect(route('home'));

    $this->assertAuthenticatedAs($user);
    expect(session('auth.mfa_at'))->not->toBeNull();
});

it('rejects a wrong code and does not allow the same code twice', function () {
    [$user, $secret] = userWithMfa();
    PendingLogin::start($user);
    $code = currentOtp($secret);

    Livewire::test(TwoFactorChallenge::class)->set('code', '000000')->call('verify')->assertHasErrors('code');
    $this->assertGuest();

    Livewire::test(TwoFactorChallenge::class)->set('code', $code)->call('verify')->assertHasNoErrors();
    auth()->logout();

    PendingLogin::start($user);
    Livewire::test(TwoFactorChallenge::class)->set('code', $code)->call('verify')->assertHasErrors('code');
});

it('sends the user back to the password step after too many wrong codes', function () {
    [$user] = userWithMfa();
    PendingLogin::start($user);

    $component = Livewire::test(TwoFactorChallenge::class);
    foreach (range(1, 4) as $ignored) {
        $component->set('code', '000000')->call('verify')->assertHasErrors('code');
    }

    $component->set('code', '000000')->call('verify')->assertRedirect(route('login'));
    expect(PendingLogin::user())->toBeNull();
});

it('accepts each recovery code only once', function () {
    [$user] = userWithMfa();
    $codes = app(RecoveryCodes::class)->regenerate($user);

    PendingLogin::start($user);
    Livewire::test(TwoFactorChallenge::class)
        ->call('toggleRecovery')
        ->set('code', $codes[0])
        ->call('verify')
        ->assertRedirect(route('home'));

    expect(app(RecoveryCodes::class)->remaining($user->fresh()))->toBe(7);

    auth()->logout();
    PendingLogin::start($user);
    Livewire::test(TwoFactorChallenge::class)
        ->call('toggleRecovery')
        ->set('code', $codes[0])
        ->call('verify')
        ->assertHasErrors('code');
});

it('remembers a trusted computer and skips the code next time', function () {
    [$user, $secret] = userWithMfa();
    PendingLogin::start($user);

    Livewire::test(TwoFactorChallenge::class)
        ->set('code', currentOtp($secret))
        ->set('trustDevice', true)
        ->call('verify');

    expect($user->trustedDevices()->count())->toBe(1);
    $token = collect(Cookie::getQueuedCookies())->firstWhere(fn ($c) => $c->getName() === TrustedDeviceManager::COOKIE)?->getValue();
    expect($token)->not->toBeNull();

    auth()->logout();

    Livewire::withCookie(TrustedDeviceManager::COOKIE, $token)
        ->test(Login::class)
        ->set('identifier', $user->username)
        ->set('password', 'correct-horse-battery')
        ->call('authenticate')
        ->assertRedirect(route('home'));

    $this->assertAuthenticatedAs($user);
});

it('requires enrolment when signing in from outside the hospital network', function () {
    treatEveryoneAsOffNetwork();
    $user = User::factory()->create(['password' => 'correct-horse-battery']);

    signInWithPassword($user)->assertRedirect(route('login.two-factor.setup'));
    $this->assertGuest();
});

it('lets normal users on the hospital network sign in without a code', function () {
    $user = User::factory()->create(['password' => 'correct-horse-battery']);

    signInWithPassword($user)->assertRedirect(route('home'));
    $this->assertAuthenticatedAs($user);
});

it('enrols an administrator during sign-in and shows recovery codes once', function () {
    $user = User::factory()->create(['password' => 'correct-horse-battery']);
    $user->assignRole(Role::findOrCreate('super-admin', 'web'));
    PendingLogin::start($user);

    $component = Livewire::test(TwoFactorSetup::class)->assertSee('Set up two-step verification');
    $secret = session('auth.mfa_setup_secret');

    $component->set('code', '000000')->call('confirm')->assertHasErrors('code');

    $component->set('code', app(Google2FA::class)->getCurrentOtp($secret))
        ->call('confirm')
        ->assertHasNoErrors()
        ->assertSee('Save your recovery codes');

    expect($user->fresh()->hasTwoFactorEnabled())->toBeTrue()
        ->and($component->get('recoveryCodes'))->toHaveCount(8);

    $component->call('finish')->assertRedirect('/admin');
    $this->assertAuthenticatedAs($user);
});

it('restarts sign-in when the pending login has expired', function () {
    Livewire::test(TwoFactorChallenge::class)->assertRedirect(route('login'));

    [$user] = userWithMfa();
    PendingLogin::start($user);
    $this->travel(11)->minutes();

    Livewire::test(TwoFactorChallenge::class)->assertRedirect(route('login'));
});
