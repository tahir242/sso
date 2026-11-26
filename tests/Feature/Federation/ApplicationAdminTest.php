<?php

use App\Domain\Federation\Actions\GrantApplicationAccess;
use App\Domain\Federation\Models\Application;
use App\Domain\Identity\Models\User;
use App\Filament\Resources\Applications\Pages\CreateApplication;
use App\Filament\Resources\Applications\Pages\ListApplications;
use App\Livewire\MyApplications;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

it('registers an application with an OIDC client and a default role', function () {
    $this->actingAs(superAdmin());

    Livewire::test(CreateApplication::class)
        ->fillForm([
            'name' => 'Medical Records Coding',
            'slug' => 'mrcs',
            'base_url' => 'https://mrcs.example.test',
            'redirect_uris' => ['https://mrcs.example.test/sso/callback'],
            'active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertNotified('Application registered');

    $app = Application::where('slug', 'mrcs')->firstOrFail();
    expect($app->client->redirect_uris)->toBe(['https://mrcs.example.test/sso/callback'])
        ->and($app->client->secret)->not->toBeNull()
        ->and($app->roles()->pluck('key')->all())->toBe(['user']);
});

it('deletes an application role that users hold', function () {
    $app = makeApp('HR System');
    $role = $app->roles()->create(['key' => 'clerk', 'name' => 'Clerk']);
    $assignment = app(GrantApplicationAccess::class)->handle($app, User::factory()->create());
    $assignment->roles()->attach($role);

    $role->delete();

    expect($app->roles()->whereKey($role->getKey())->exists())->toBeFalse()
        ->and($assignment->roles()->pluck('key')->all())->not->toContain('clerk');
});

it('only shows app owners their own applications', function () {
    $owner = tap(User::factory()->create(), fn (User $u) => $u->assignRole(Role::findOrCreate('app-owner', 'web')));
    $mine = makeApp('HR System', $owner);
    $other = makeApp('Lab Reports');

    $this->actingAs($owner);

    Livewire::test(ListApplications::class)
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$other]);
});

it('lists the user’s current applications on the launcher', function () {
    $user = User::factory()->create();
    $hr = makeApp('HR System');
    $lab = makeApp('Lab Reports');
    makeApp('Payroll');
    app(GrantApplicationAccess::class)->handle($hr, $user);
    app(GrantApplicationAccess::class)->handle($lab, $user, expiresAt: now()->addDay());
    $this->travel(2)->days();

    $this->actingAs($user);

    Livewire::test(MyApplications::class)
        ->assertSee('HR System')
        ->assertDontSee('Lab Reports')
        ->assertDontSee('Payroll');
});
