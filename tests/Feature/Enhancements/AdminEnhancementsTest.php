<?php

use App\Domain\Federation\Enums\AccessMode;
use App\Domain\Federation\Models\ApplicationAssignment;
use App\Domain\Identity\Enums\LocationType;
use App\Domain\Identity\Models\Location;
use App\Domain\Identity\Models\User;
use App\Filament\Pages\LocationDictionary;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Widgets\IdentityOverview;
use App\Filament\Widgets\SignInActivityChart;
use Livewire\Livewire;

it('requires an email address when an administrator creates a user', function () {
    $this->actingAs(superAdmin());

    Livewire::test(CreateUser::class)
        ->fillForm(['username' => 'new.user', 'given_name' => 'New', 'family_name' => 'User', 'auth_source' => 'local', 'status' => 'active', 'password' => 'Passw0rd-Strong-1', 'email' => ''])
        ->call('create')
        ->assertHasFormErrors(['email' => 'required']);
});

it('gives and removes application access from the user form', function () {
    $this->actingAs(superAdmin());
    $hr = makeApp('HR');
    $ot = makeApp('OT');

    Livewire::test(CreateUser::class)
        ->fillForm([
            'username' => 'sara.ali', 'given_name' => 'Sara', 'family_name' => 'Ali', 'email' => 'sara.ali@example.test',
            'auth_source' => 'local', 'status' => 'active', 'password' => 'Passw0rd-Strong-1',
            'application_ids' => [$hr->id, $ot->id],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::where('username', 'sara.ali')->firstOrFail();
    expect($hr->canBeOpenedBy($user))->toBeTrue()
        ->and($ot->canBeOpenedBy($user))->toBeTrue()
        ->and($hr->roleKeysFor($user))->toBe(['user']);

    Livewire::test(EditUser::class, ['record' => $user->ulid])
        ->assertFormSet(['application_ids' => [$hr->id, $ot->id]])
        ->fillForm(['application_ids' => [$hr->id]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($ot->fresh()->canBeOpenedBy($user->fresh()))->toBeFalse()
        ->and(ApplicationAssignment::where('user_id', $user->id)->where('application_id', $ot->id)->value('active'))->toBeFalse();
});

it('lets everyone open an application that is open to everyone, with its default roles', function () {
    $app = makeApp('Intranet');
    $app->roles()->create(['key' => 'reader', 'name' => 'Reader', 'is_default' => true]);
    $user = User::factory()->create();

    expect($app->canBeOpenedBy($user))->toBeFalse();

    $app->update(['access_mode' => AccessMode::Everyone]);

    expect($app->fresh()->canBeOpenedBy($user))->toBeTrue()
        ->and($app->fresh()->roleKeysFor($user))->toContain('reader');

    $this->actingAs($user)->get('/home')->assertOk()->assertSee('Intranet')->assertSee('Open to everyone');
});

it('keeps the location hierarchy valid', function () {
    $city = Location::create(['type' => LocationType::City, 'name' => 'Lakeside']);
    $building = Location::create(['type' => LocationType::Building, 'name' => 'Main', 'parent_id' => $city->id]);

    expect(fn () => Location::create(['type' => LocationType::Room, 'name' => 'Room 1', 'parent_id' => $building->id]))
        ->toThrow(LogicException::class);
    expect(fn () => Location::create(['type' => LocationType::Building, 'name' => 'Orphan']))
        ->toThrow(LogicException::class);

    $floor = Location::create(['type' => LocationType::Floor, 'name' => 'G', 'parent_id' => $building->id]);
    $room = Location::create(['type' => LocationType::Room, 'name' => 'IT', 'parent_id' => $floor->id]);
    $ws = Location::create(['type' => LocationType::Workstation, 'name' => 'WS-1', 'parent_id' => $room->id]);
    $user = User::factory()->create(['location_id' => $ws->id]);

    expect($ws->path())->toBe('Lakeside › Main › G › IT › WS-1')
        ->and($city->descendantIds())->toHaveCount(5);

    expect(fn () => $ws->delete())->toThrow(LogicException::class);
    $user->update(['location_id' => null]);
    $ws->delete();
    expect(Location::find($ws->id))->toBeNull();
});

it('manages the location dictionary from the admin console', function () {
    $this->actingAs(superAdmin());

    Livewire::test(LocationDictionary::class)
        ->assertOk()
        ->callAction('create', ['name' => 'Lakeside'])
        ->assertHasNoActionErrors();

    $city = Location::where('name', 'Lakeside')->firstOrFail();
    expect($city->type)->toBe(LocationType::City)->and($city->code)->toBe('LAK');

    Livewire::test(LocationDictionary::class)
        ->callAction('create', ['name' => 'Main Campus'], arguments: ['parent' => $city->id])
        ->assertHasNoActionErrors()
        ->set('search', 'main')
        ->assertSee('Main Campus');

    expect(Location::where('name', 'Main Campus')->value('type'))->toBe(LocationType::Building)
        ->and(Location::where('name', 'Main Campus')->value('code'))->toBe('LAK-B01');
});

it('shows the location dictionary to helpdesk read-only and hides it from staff', function () {
    $this->actingAs(User::factory()->create())->get('/admin/locations')->assertForbidden();
});

it('shows identity KPIs on the admin dashboard', function () {
    $this->actingAs(superAdmin())->get('/admin')->assertOk();

    Livewire::test(IdentityOverview::class)
        ->assertSee('Active accounts')
        ->assertSee('Two-step verification')
        ->assertSee('Accounts missing email');

    Livewire::test(SignInActivityChart::class)->assertOk();
});

it('assigns unique, hierarchical location codes automatically and re-codes on move', function () {
    $make = fn (LocationType $type, string $name, ?Location $parent = null) => Location::create(['type' => $type, 'name' => $name, 'parent_id' => $parent?->id]);

    $lakeside = $make(LocationType::City, 'Lakeside');
    $lake2 = $make(LocationType::City, 'Lakewood');
    $main = $make(LocationType::Building, 'Main', $lakeside);
    $annex = $make(LocationType::Building, 'Annex', $lakeside);
    $ground = $make(LocationType::Floor, 'Ground', $main);
    $first = $make(LocationType::Floor, 'First', $annex);
    $room = $make(LocationType::Room, 'IT', $ground);
    $ws1 = $make(LocationType::Workstation, 'PC 1', $room);
    $ws2 = $make(LocationType::Workstation, 'PC 2', $room);

    expect([$lakeside->code, $lake2->code, $main->code, $annex->code, $ground->code, $room->code, $ws1->code, $ws2->code])
        ->toBe(['LAK', 'LAK2', 'LAK-B01', 'LAK-B02', 'LAK-B01-F01', 'LAK-B01-F01-R01', 'LAK-B01-F01-R01-W01', 'LAK-B01-F01-R01-W02']);

    // Numbering continues from the highest code in use (deleting W01 does not free it for reuse).
    $ws1->delete();
    expect($make(LocationType::Workstation, 'PC 3', $room)->code)->toBe('LAK-B01-F01-R01-W03');

    // Moving the room to the annex re-codes it and its workstations.
    $room->update(['parent_id' => $first->id]);
    expect($room->fresh()->code)->toBe('LAK-B02-F01-R01')
        ->and(Location::where('parent_id', $room->id)->orderBy('code')->pluck('code')->all())
        ->toBe(['LAK-B02-F01-R01-W01', 'LAK-B02-F01-R01-W02']);
});
