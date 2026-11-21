<?php

use App\Domain\Audit\Enums\AuthEventType;
use App\Domain\Audit\Models\AuthEvent;
use App\Domain\Identity\Actions\ImportDirectoryUser;
use App\Domain\Identity\Directory\ActiveDirectory;
use App\Domain\Identity\Enums\AuthSource;
use App\Domain\Identity\Models\User;
use App\Filament\Resources\Users\Pages\ListUsers;
use LdapRecord\Laravel\Testing\DirectoryEmulator;
use LdapRecord\Models\ActiveDirectory\User as LdapUser;
use Livewire\Livewire;

afterEach(fn () => DirectoryEmulator::tearDown());

function directoryPerson(array $attributes = []): LdapUser
{
    return LdapUser::create([
        'cn' => 'Sana Mirza',
        'displayname' => 'Sana Mirza',
        'samaccountname' => 'sana.mirza',
        'mail' => 'Sana.Mirza@example.test',
        'employeeid' => '30111',
        'title' => 'Staff Nurse',
        'department' => 'Nephrology',
        'useraccountcontrol' => 512,
        ...$attributes,
    ]);
}

it('finds people in Active Directory by username, name, email or employee ID', function () {
    fakeActiveDirectory();
    directoryPerson();
    directoryPerson(['cn' => 'Omar Farooq', 'displayname' => 'Omar Farooq', 'samaccountname' => 'omar.farooq', 'mail' => 'omar@example.test', 'employeeid' => '30222']);

    $find = fn (string $term) => collect(app(ActiveDirectory::class)->search($term))->map(fn ($p) => $p->getFirstAttribute('samaccountname'))->all();

    expect($find('sana'))->toBe(['sana.mirza'])
        ->and($find('Farooq'))->toBe(['omar.farooq'])
        ->and($find('omar@'))->toBe(['omar.farooq'])
        ->and($find('30111'))->toBe(['sana.mirza'])
        ->and($find('s'))->toBe([]); // at least 2 characters
});

it('creates an SSO account from an Active Directory person', function () {
    fakeActiveDirectory();
    $person = directoryPerson();

    $user = app(ImportDirectoryUser::class)->handle($person->fresh(), superAdmin());

    expect($user->auth_source)->toBe(AuthSource::ActiveDirectory)
        ->and($user->username)->toBe('sana.mirza')
        ->and($user->name)->toBe('Sana Mirza')
        ->and($user->email)->toBe('sana.mirza@example.test')
        ->and($user->emp_id)->toBe('30111')
        ->and($user->designation)->toBe('Staff Nurse')
        ->and($user->department->name)->toBe('Nephrology')
        ->and($user->ldap_guid)->toBe($person->fresh()->getConvertedGuid())
        ->and($user->password)->toBeNull()
        ->and(AuthEvent::where('event', AuthEventType::UserProvisioned)->where('user_id', $user->id)->exists())->toBeTrue();

    expect(fn () => app(ImportDirectoryUser::class)->handle($person->fresh()))->toThrow(LogicException::class);
});

it('adds people from Active Directory on the Users screen and gives them applications', function () {
    fakeActiveDirectory();
    $person = directoryPerson();
    $app = makeApp('HR');
    $this->actingAs(superAdmin());

    Livewire::test(ListUsers::class)
        ->assertActionEnabled('addFromActiveDirectory')
        ->callAction('addFromActiveDirectory', ['people' => [$person->fresh()->getConvertedGuid()], 'applications' => [$app->id]])
        ->assertHasNoActionErrors()
        ->assertNotified('1 person added');

    $user = User::where('username', 'sana.mirza')->firstOrFail();
    expect($app->canBeOpenedBy($user))->toBeTrue();
});

it('offers the action only when Active Directory sign-in is on', function () {
    $this->actingAs(superAdmin());

    Livewire::test(ListUsers::class)->assertActionDisabled('addFromActiveDirectory');
});
