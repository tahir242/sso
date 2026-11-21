<?php

use App\Domain\Identity\Enums\AuthSource;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Support\EmployeeInitials;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use Illuminate\Support\Facades\Cache;
use LdapRecord\Laravel\Testing\DirectoryEmulator;
use LdapRecord\Models\ActiveDirectory\User as LdapUser;
use Livewire\Livewire;

beforeEach(fn () => Cache::flush());
afterEach(fn () => DirectoryEmulator::tearDown());

function adPerson(array $attributes = []): LdapUser
{
    return LdapUser::create([
        'cn' => 'Sana Mirza',
        'displayname' => 'Sana Mirza',
        'samaccountname' => 'sana.mirza',
        'mail' => 'sana.mirza@example.test',
        'employeeid' => '30111',
        'title' => 'Staff Nurse',
        'department' => 'Nephrology',
        'useraccountcontrol' => 512,
        ...$attributes,
    ]);
}

it('generates initials from the name and keeps them unique', function () {
    expect(EmployeeInitials::generate('Jane Ann Doe'))->toBe('JAD')
        ->and(EmployeeInitials::generate('Muhammad Khalid Jamil'))->toBe('KJ')
        ->and(EmployeeInitials::generate('Dr. Syed Ali Raza'))->toBe('AR');

    User::factory()->create(['name' => 'Khalid Jamil', 'emp_initial' => 'KJ']);

    $next = EmployeeInitials::generate('Muhammad Khalid Jamil');
    expect($next)->not->toBe('KJ')->and($next)->toStartWith('KJ');
});

it('gives every new account initials automatically', function () {
    $user = User::factory()->create(['name' => 'Jane Ann Doe', 'emp_initial' => null]);

    expect($user->emp_initial)->toBe('JAD');
});

it('refuses a local account whose username belongs to someone in Active Directory', function () {
    fakeActiveDirectory();
    adPerson();
    $this->actingAs(superAdmin());

    Livewire::test(CreateUser::class)
        ->fillForm(['auth_source' => AuthSource::Local->value, 'username' => 'sana.mirza', 'name' => 'Sana', 'email' => 'x@example.test', 'password' => 'Very-Strong-Pass-123'])
        ->call('create')
        ->assertHasFormErrors(['username']);
});

it('refuses an Active Directory account that does not exist in AD', function () {
    fakeActiveDirectory();
    $this->actingAs(superAdmin());

    Livewire::test(CreateUser::class)
        ->fillForm(['auth_source' => AuthSource::ActiveDirectory->value, 'username' => 'nobody.here', 'name' => 'Nobody', 'email' => 'n@example.test'])
        ->call('create')
        ->assertHasFormErrors(['username']);
});

it('fills an Active Directory account from AD when it is created', function () {
    fakeActiveDirectory();
    adPerson();
    $this->actingAs(superAdmin());

    Livewire::test(CreateUser::class)
        ->fillForm(['auth_source' => AuthSource::ActiveDirectory->value])
        ->fillForm(['username' => 'sana.mirza'])
        ->assertSchemaStateSet(['name' => 'Sana Mirza', 'email' => 'sana.mirza@example.test', 'emp_id' => '30111'])
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::where('username', 'sana.mirza')->firstOrFail();
    expect($user->auth_source)->toBe(AuthSource::ActiveDirectory)
        ->and($user->emp_initial)->toBe('SM')
        ->and($user->ldap_guid)->not->toBeNull()
        ->and($user->department->name)->toBe('Nephrology')
        ->and($user->password)->toBeNull();
});

it('takes the details from AD when a local account is switched to Active Directory', function () {
    fakeActiveDirectory();
    adPerson();
    $this->actingAs(superAdmin());
    $user = User::factory()->create(['username' => 'sana.mirza', 'name' => 'sana', 'email' => 'old@example.test', 'emp_initial' => 'SMZ', 'auth_source' => AuthSource::Local]);

    Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
        ->fillForm(['auth_source' => AuthSource::ActiveDirectory->value])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified('Switched to Active Directory — details updated from AD');

    $user->refresh();
    expect($user->auth_source)->toBe(AuthSource::ActiveDirectory)
        ->and($user->name)->toBe('Sana Mirza')
        ->and($user->email)->toBe('sana.mirza@example.test')
        ->and($user->designation)->toBe('Staff Nurse')
        ->and($user->emp_initial)->toBe('SMZ') // kept
        ->and($user->password)->toBeNull();
});

it('refreshes an Active Directory account from AD on request', function () {
    fakeActiveDirectory();
    $person = adPerson();
    $this->actingAs(superAdmin());
    $user = User::factory()->create(['username' => 'sana.mirza', 'name' => 'Old Name', 'auth_source' => AuthSource::ActiveDirectory, 'password' => null]);

    $person->setAttribute('title', 'Head Nurse');
    $person->save();

    Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
        ->assertActionVisible('refreshFromDirectory')
        ->callAction('refreshFromDirectory')
        ->assertNotified('Details updated from Active Directory');

    expect($user->refresh()->name)->toBe('Sana Mirza')->and($user->designation)->toBe('Head Nurse');
});
