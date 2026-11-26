<?php

use App\Domain\Identity\Actions\CompleteLogin;
use App\Domain\Identity\Models\User;
use App\Livewire\Account\Security;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;
use Spatie\Permission\Models\Role;

it('shows the security page to signed-in users', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('account.security'))
        ->assertOk()
        ->assertSee('Two-step verification')
        ->assertSee('Where you’re signed in');
});

it('lets a user turn on two-step verification', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $component = Livewire::test(Security::class)->call('startSetup');
    $secret = session('account.mfa_setup_secret');

    $component->set('code', app(Google2FA::class)->getCurrentOtp($secret))
        ->call('confirmSetup')
        ->assertHasNoErrors()
        ->assertSee('Save your recovery codes');

    expect($user->fresh()->hasTwoFactorEnabled())->toBeTrue();
});

it('needs a current code to turn two-step verification off', function () {
    [$user, $secret] = userWithMfa();
    $this->actingAs($user);

    Livewire::test(Security::class)->set('code', '000000')->call('disableTwoFactor')->assertHasErrors('code');
    expect($user->fresh()->hasTwoFactorEnabled())->toBeTrue();

    Livewire::test(Security::class)->set('code', currentOtp($secret))->call('disableTwoFactor')->assertHasNoErrors();
    expect($user->fresh()->hasTwoFactorEnabled())->toBeFalse();
});

it('lets administrators turn two-step verification off too', function () {
    [$user, $secret] = userWithMfa();
    $user->assignRole(Role::findOrCreate('super-admin', 'web'));
    $this->actingAs($user);

    Livewire::test(Security::class)->set('code', currentOtp($secret))->call('disableTwoFactor')->assertHasNoErrors();
    expect($user->fresh()->hasTwoFactorEnabled())->toBeFalse();
});

it('changes a local password', function () {
    $user = User::factory()->create(['password' => 'correct-horse-battery']);
    $this->actingAs($user);

    Livewire::test(Security::class)
        ->set('current_password', 'wrong')
        ->set('password', 'Brand-new-pass-2026')
        ->set('password_confirmation', 'Brand-new-pass-2026')
        ->call('changePassword')
        ->assertHasErrors('current_password');

    Livewire::test(Security::class)
        ->set('current_password', 'correct-horse-battery')
        ->set('password', 'short1')
        ->set('password_confirmation', 'short1')
        ->call('changePassword')
        ->assertHasErrors('password');

    Livewire::test(Security::class)
        ->set('current_password', 'correct-horse-battery')
        ->set('password', 'Brand-new-pass-2026')
        ->set('password_confirmation', 'Brand-new-pass-2026')
        ->call('changePassword')
        ->assertHasNoErrors();

    expect(Hash::check('Brand-new-pass-2026', $user->fresh()->password))->toBeTrue();
});

it('ends sessions older than the maximum session length', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession([CompleteLogin::STARTED_AT => now()->subHours(11)->getTimestamp()])
        ->get(route('home'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});
