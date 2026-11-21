<?php

use App\Domain\Audit\Enums\AuthEventType;
use App\Domain\Audit\Models\AuthEvent;
use App\Domain\Identity\Enums\AuthSource;
use App\Domain\Identity\Models\User;
use App\Livewire\Auth\Login;
use LdapRecord\Laravel\Testing\DirectoryEmulator;
use LdapRecord\Models\ActiveDirectory\User as LdapUser;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

afterEach(fn () => DirectoryEmulator::tearDown());

function adUser(array $attributes = []): LdapUser
{
    return LdapUser::create([
        'cn' => 'Ahmed Khan',
        'displayname' => 'Ahmed Khan',
        'samaccountname' => 'ahmed.khan',
        'mail' => 'ahmed.khan@example.test',
        'employeeid' => '20456',
        'title' => 'Medical Officer',
        'useraccountcontrol' => 512, // normal, enabled account
        ...$attributes,
    ]);
}

function signIn(string $identifier, string $password = 'Windows-Pass-1'): Testable
{
    return Livewire::test(Login::class)
        ->set('identifier', $identifier)
        ->set('password', $password)
        ->call('authenticate');
}

it('creates the SSO account on first Active Directory sign-in', function () {
    $fake = fakeActiveDirectory();
    $fake->actingAs($ldapUser = adUser());

    signIn('ahmed.khan')->assertHasNoErrors()->assertRedirect(route('home'));

    $user = User::where('username', 'ahmed.khan')->firstOrFail();
    expect($user->auth_source)->toBe(AuthSource::ActiveDirectory)
        ->and($user->name)->toBe('Ahmed Khan')
        ->and($user->emp_id)->toBe('20456')
        ->and($user->designation)->toBe('Medical Officer')
        ->and($user->ldap_guid)->toBe($ldapUser->fresh()->getConvertedGuid())
        ->and($user->password)->toBeNull();

    $this->assertAuthenticatedAs($user);
    expect(AuthEvent::where('event', AuthEventType::UserProvisioned)->count())->toBe(1);
});

it('signs in an existing AD user by Employee ID and refreshes their profile', function () {
    $fake = fakeActiveDirectory();
    $fake->actingAs($ldapUser = adUser(['displayname' => 'Dr. Ahmed Khan']));

    $user = User::factory()->activeDirectory()->create([
        'username' => 'ahmed.khan', 'emp_id' => '20456', 'name' => 'Old Name', 'ldap_guid' => null,
    ]);

    signIn('20456')->assertHasNoErrors();

    expect($user->fresh()->name)->toBe('Dr. Ahmed Khan')
        ->and($user->fresh()->ldap_guid)->toBe($ldapUser->fresh()->getConvertedGuid());
    $this->assertAuthenticatedAs($user);
});

it('rejects a wrong Windows password', function () {
    fakeActiveDirectory(); // no actingAs: every bind fails
    adUser();

    signIn('ahmed.khan', 'wrong')->assertHasErrors(['identifier' => 'These credentials do not match our records.']);
    $this->assertGuest();
    expect(User::count())->toBe(0);
});

it('rejects accounts that are disabled in Active Directory', function () {
    $fake = fakeActiveDirectory();
    $fake->actingAs(adUser(['useraccountcontrol' => 514])); // 512 + ACCOUNTDISABLE

    signIn('ahmed.khan')->assertHasErrors('identifier');
    $this->assertGuest();
});

it('does not create accounts when automatic creation is off', function () {
    $fake = fakeActiveDirectory(['auto_create_users' => false]);
    $fake->actingAs(adUser());

    signIn('ahmed.khan')->assertHasErrors('identifier');
    expect(User::count())->toBe(0);
});

it('ignores Active Directory entirely when it is switched off', function () {
    $fake = fakeActiveDirectory(['enabled' => false]);
    $fake->actingAs(adUser());

    signIn('ahmed.khan')->assertHasErrors('identifier');
});

it('keeps local accounts on their local password even when AD is on', function () {
    fakeActiveDirectory();
    $local = User::factory()->create(['username' => 'vendor.user', 'password' => 'correct-horse-battery']);

    signIn('vendor.user', 'correct-horse-battery')->assertHasNoErrors();
    $this->assertAuthenticatedAs($local);
});

it('puts new AD users in their AD department', function () {
    $fake = fakeActiveDirectory();
    $fake->actingAs(adUser(['department' => 'Human Resources']));

    signIn('ahmed.khan')->assertHasNoErrors();

    $user = User::where('username', 'ahmed.khan')->firstOrFail();
    expect($user->department?->name)->toBe('Human Resources')
        ->and($user->department->code)->toBe('HUMAN_RESOURCES');
});
