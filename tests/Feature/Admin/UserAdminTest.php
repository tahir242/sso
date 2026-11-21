<?php

use App\Domain\Audit\Enums\AuthEventType;
use App\Domain\Audit\Models\AuthEvent;
use App\Domain\Identity\Directory\ActiveDirectory;
use App\Domain\Identity\Models\User;
use App\Domain\Settings\LdapSettings;
use App\Filament\Pages\Settings\ManageActiveDirectory;
use App\Filament\Resources\Users\Pages\ListUsers;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

function admin(string $role = 'super-admin'): User
{
    return tap(User::factory()->create(), fn (User $user) => $user->assignRole(Role::findOrCreate($role, 'web')));
}

it('lets helpdesk reset a user’s two-step verification', function () {
    [$user] = userWithMfa();
    $this->actingAs($helpdesk = admin('helpdesk'));

    Livewire::test(ListUsers::class)
        ->assertCanSeeTableRecords([$user])
        ->callTableAction('resetMfa', $user);

    expect($user->fresh()->hasTwoFactorEnabled())->toBeFalse();
    expect(AuthEvent::where('event', AuthEventType::MfaReset)->where('user_id', $user->id)->value('meta'))
        ->toBe(['by' => $helpdesk->username]);
});

it('keeps normal users out of user management', function () {
    $this->actingAs(User::factory()->create())->get('/admin/users')->assertForbidden();
});

it('never sends the stored AD service password back to the browser', function () {
    $settings = app(LdapSettings::class);
    $settings->bind_password = 'Sup3r-Secret';
    $settings->save();

    $this->actingAs(admin());

    Livewire::test(ManageActiveDirectory::class)
        ->assertSet('data.bind_password', null)
        ->set('data.base_dn', 'DC=corp,DC=local')
        ->call('save')
        ->assertHasNoErrors();

    expect(app(LdapSettings::class)->bind_password)->toBe('Sup3r-Secret')
        ->and(app(LdapSettings::class)->base_dn)->toBe('DC=corp,DC=local');
});

it('saves Active Directory settings with optional fields left empty', function () {
    $this->actingAs(admin());

    Livewire::test(ManageActiveDirectory::class)
        ->set('data.enabled', false)
        ->set('data.base_dn', null)
        ->set('data.hosts', null)
        ->call('save')
        ->assertHasNoErrors();

    $settings = app(LdapSettings::class)->refresh();
    expect($settings->base_dn)->toBe('')
        ->and($settings->hosts)->toBe([])
        ->and($settings->isConfigured())->toBeFalse();
});

it('still requires the base DN when Active Directory sign-in is on', function () {
    $this->actingAs(admin());

    Livewire::test(ManageActiveDirectory::class)
        ->set('data.enabled', true)
        ->set('data.base_dn', null)
        ->call('save')
        ->assertHasErrors(['data.base_dn']);
});

it('requires a service account when Active Directory sign-in is on', function () {
    $this->actingAs(admin());

    Livewire::test(ManageActiveDirectory::class)
        ->set('data.enabled', true)
        ->set('data.hosts', ['dc01.example.test'])
        ->set('data.base_dn', 'DC=example,DC=test')
        ->set('data.bind_username', null)
        ->set('data.bind_password', null)
        ->call('save')
        ->assertHasErrors(['data.bind_username', 'data.bind_password']);
});

it('explains a missing service account instead of a raw LDAP error', function () {
    $settings = app(LdapSettings::class);
    $settings->fill(['enabled' => true, 'hosts' => ['dc01.example.test'], 'base_dn' => 'DC=example,DC=test', 'bind_username' => null, 'bind_password' => null])->save();

    $result = app(ActiveDirectory::class)->test();
    expect($result['ok'])->toBeFalse()->and($result['message'])->toContain('No service account is saved');

    $explain = fn (string $e) => ActiveDirectory::explain($e);
    expect($explain('Operations error: 000004DC: LdapErr: DSID-0C090CE5, comment: In order to perform this operation a successful bind must be completed'))->toContain('No service account is saved')
        ->and($explain('Invalid credentials: 80090308: LdapErr: DSID-0C09041C, AcceptSecurityContext error, data 52e, v4563'))->toContain('username or password is wrong')
        ->and($explain('Something else'))->toBe('Something else');
});
