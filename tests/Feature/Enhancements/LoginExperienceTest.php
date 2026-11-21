<?php

use App\Domain\Identity\Auth\RecentAccounts;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\User;
use App\Livewire\Account\EmailAddress;
use App\Livewire\Auth\Login;
use Illuminate\Support\Facades\Cookie;
use Livewire\Livewire;

it('remembers who signed in on this browser', function () {
    $user = User::factory()->create(['password' => 'correct-horse-battery', 'name' => 'Ayesha Khan']);

    Livewire::test(Login::class)
        ->set('identifier', $user->username)
        ->set('password', 'correct-horse-battery')
        ->call('authenticate')
        ->assertRedirect(route('home'));

    $cookie = collect(Cookie::getQueuedCookies())->firstWhere(fn ($c) => $c->getName() === RecentAccounts::COOKIE);
    expect($cookie)->not->toBeNull()
        ->and(json_decode($cookie->getValue(), true))->toBe([$user->username]);
});

it('offers remembered profiles and signs in with the password only', function () {
    $user = User::factory()->create(['password' => 'correct-horse-battery', 'name' => 'Ayesha Khan']);

    $this->withCookie(RecentAccounts::COOKIE, json_encode([$user->username]))
        ->get('/login')
        ->assertOk()
        ->assertSee('Ayesha Khan')
        ->assertSee('Other user');

    Livewire::withCookie(RecentAccounts::COOKIE, json_encode([$user->username]))->test(Login::class)
        ->assertSet('selected', $user->username)
        ->set('password', 'correct-horse-battery')
        ->call('authenticate')
        ->assertHasNoErrors()
        ->assertRedirect(route('home'));

    $this->assertAuthenticatedAs($user);
});

it('switches to the full form for another user and can forget a profile', function () {
    $user = User::factory()->create();
    Livewire::withCookie(RecentAccounts::COOKIE, json_encode([$user->username]))->test(Login::class)
        ->call('otherUser')
        ->assertSet('selected', null)
        ->assertSet('identifier', '')
        ->call('selectAccount', $user->username)
        ->assertSet('selected', $user->username)
        ->call('forgetAccount', $user->username)
        ->assertSet('recent', [])
        ->assertSet('selected', null);
});

it('never offers disabled or unknown accounts, or accounts not chosen on this browser', function () {
    $disabled = User::factory()->create(['status' => UserStatus::Disabled]);
    request()->cookies->set(RecentAccounts::COOKIE, json_encode([$disabled->username, 'ghost']));

    expect(RecentAccounts::all())->toBe([]);

    Livewire::test(Login::class)
        ->assertSet('selected', null)
        ->call('selectAccount', 'someone-else')
        ->assertSet('selected', null);
});

it('asks accounts without an email address for one before continuing', function () {
    $user = User::factory()->create(['password' => 'correct-horse-battery', 'email' => null]);

    Livewire::test(Login::class)
        ->set('identifier', $user->username)
        ->set('password', 'correct-horse-battery')
        ->call('authenticate')
        ->assertRedirect(route('account.email'));

    $this->get('/home')->assertRedirect(route('account.email'));

    Livewire::test(EmailAddress::class)
        ->set('email', '')
        ->call('save')
        ->assertHasErrors(['email' => 'required'])
        ->set('email', 'not-an-email')
        ->call('save')
        ->assertHasErrors(['email'])
        ->set('email', 'Ayesha.Khan@EXAMPLE.test')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('home'));

    expect($user->fresh()->email)->toBe('ayesha.khan@example.test');
    $this->get('/home')->assertOk();
});

it('rejects an email address already used by another account', function () {
    User::factory()->create(['email' => 'taken@example.test']);
    $this->actingAs(User::factory()->create(['email' => null]));

    Livewire::test(EmailAddress::class)
        ->set('email', 'taken@example.test')
        ->call('save')
        ->assertHasErrors(['email' => 'unique']);
});
