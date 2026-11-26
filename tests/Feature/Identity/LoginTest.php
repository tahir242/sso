<?php

use App\Domain\Engagement\Models\Quote;
use App\Domain\Identity\Models\User;
use App\Domain\Settings\SecuritySettings;
use App\Livewire\Auth\Login;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

it('renders the branded login page with the greeting', function () {
    Quote::create(['text' => 'Unity, Faith, Discipline.', 'author' => 'Quaid-e-Azam']);

    $this->get('/login')
        ->assertOk()
        ->assertSee('Username or Employee ID')
        ->assertSee('Unity, Faith, Discipline.');
});

it('redirects guests from protected pages to the SSO login', function () {
    $this->get('/home')->assertRedirect('/login');
    $this->get('/admin')->assertRedirect('/login');
});

it('signs in with username or employee ID', function (string $field) {
    $user = User::factory()->create(['password' => 'correct-horse-battery']);

    Livewire::test(Login::class)
        ->set('identifier', $user->{$field})
        ->set('password', 'correct-horse-battery')
        ->call('authenticate')
        ->assertHasNoErrors()
        ->assertRedirect(route('home'));

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->last_login_at)->not->toBeNull();
})->with(['username', 'emp_id']);

it('signs administrators in without two-step verification when they have not turned it on', function () {
    $user = User::factory()->create(['password' => 'correct-horse-battery']);
    $user->assignRole(Role::findOrCreate('super-admin', 'web'));

    Livewire::test(Login::class)
        ->set('identifier', $user->username)
        ->set('password', 'correct-horse-battery')
        ->call('authenticate')
        ->assertRedirect('/admin');

    $this->assertAuthenticatedAs($user);
});

it('uses one generic message for unknown users and wrong passwords', function () {
    $user = User::factory()->create();

    foreach ([[$user->username, 'wrong-password'], ['nobody', 'whatever']] as [$identifier, $password]) {
        Livewire::test(Login::class)
            ->set('identifier', $identifier)
            ->set('password', $password)
            ->call('authenticate')
            ->assertHasErrors(['identifier' => 'These credentials do not match our records.']);
    }

    $this->assertGuest();
});

it('blocks disabled accounts even with the right password', function () {
    $user = User::factory()->disabled()->create(['password' => 'correct-horse-battery']);

    Livewire::test(Login::class)
        ->set('identifier', $user->username)
        ->set('password', 'correct-horse-battery')
        ->call('authenticate')
        ->assertHasErrors('identifier');

    $this->assertGuest();
});

it('locks an account after too many failed attempts', function () {
    $settings = app(SecuritySettings::class);
    $user = User::factory()->create(['password' => 'correct-horse-battery']);

    foreach (range(1, $settings->lockout_max_attempts) as $ignored) {
        Livewire::test(Login::class)->set('identifier', $user->username)->set('password', 'nope')->call('authenticate');
    }

    Livewire::test(Login::class)
        ->set('identifier', $user->username)
        ->set('password', 'correct-horse-battery')
        ->call('authenticate')
        ->assertHasErrors('identifier');

    $this->assertGuest();
});

it('stops an empty password in the browser and never counts it', function () {
    $settings = app(SecuritySettings::class);
    $user = User::factory()->create(['password' => 'correct-horse-battery']);

    // The form checks the field before sending anything.
    $this->get(route('login'))->assertOk()
        ->assertSee('Enter your password.')
        ->assertDontSee('wire:submit="authenticate"', false);

    // If it reaches the server anyway (scripts off), it is refused and not counted.
    foreach (range(1, $settings->lockout_max_attempts + 2) as $ignored) {
        Livewire::test(Login::class)->set('identifier', $user->username)->call('authenticate')
            ->assertHasErrors(['password' => 'required']);
    }

    Livewire::test(Login::class)
        ->set('identifier', $user->username)
        ->set('password', 'correct-horse-battery')
        ->call('authenticate')
        ->assertHasNoErrors();
});

it('upgrades a legacy MD5 password to Argon2id on first login', function () {
    $user = User::factory()->create();
    // Old SSO hash written raw, as the one-time migration stored it.
    DB::table('users')->where('id', $user->id)->update(['password' => md5('old-legacy-pass'), 'legacy_md5' => true]);

    Livewire::test(Login::class)
        ->set('identifier', $user->username)
        ->set('password', 'old-legacy-pass')
        ->call('authenticate')
        ->assertHasNoErrors();

    $user->refresh();
    expect($user->legacy_md5)->toBeFalse()
        ->and(Hash::info($user->password)['algoName'])->toBe('argon2id')
        ->and(Hash::check('old-legacy-pass', $user->password))->toBeTrue();
});

it('does not let Active Directory users sign in until LDAP is configured', function () {
    $user = User::factory()->activeDirectory()->create();

    Livewire::test(Login::class)
        ->set('identifier', $user->username)
        ->set('password', 'anything')
        ->call('authenticate')
        ->assertHasErrors('identifier');
});

it('keeps non-admin users out of the admin console', function () {
    $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
});

it('logs out and invalidates the session', function () {
    $this->actingAs(User::factory()->create())
        ->post('/logout')
        ->assertRedirect('/login');

    $this->assertGuest();
});

it('does not send a non-administrator to a console page remembered from someone else', function () {
    // An administrator signed out of /admin; the browser remembered /admin as the page to return to.
    session()->put('url.intended', url('/admin/users'));
    $user = User::factory()->create(['password' => 'correct-horse-battery']);

    Livewire::test(Login::class)
        ->set('identifier', $user->username)
        ->set('password', 'correct-horse-battery')
        ->call('authenticate')
        ->assertRedirect(route('home'));

    $this->get('/home')->assertOk();
});

it('still returns a user to the non-console page they asked for', function () {
    session()->put('url.intended', url('/approvals'));
    $user = User::factory()->create(['password' => 'correct-horse-battery']);

    Livewire::test(Login::class)
        ->set('identifier', $user->username)
        ->set('password', 'correct-horse-battery')
        ->call('authenticate')
        ->assertRedirect(url('/approvals'));
});
