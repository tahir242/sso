<?php

use App\Domain\Federation\Actions\GrantApplicationAccess;
use App\Domain\Federation\Oidc\Claims;
use App\Domain\Identity\Models\User;

it('lists only the users assigned to the calling application', function () {
    [$hr, $secret] = apiApp('hr');
    [$lab] = apiApp('lab');
    $ayesha = User::factory()->create(['name' => 'Ayesha', 'emp_initial' => 'AS']);
    $bilal = User::factory()->create(['name' => 'Bilal']);
    app(GrantApplicationAccess::class)->handle($hr, $ayesha);
    app(GrantApplicationAccess::class)->handle($lab, $bilal);

    $this->withToken(clientToken($hr, $secret))->getJson('/api/v1/users')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.user_id', $ayesha->id)
        ->assertJsonPath('data.0.emp_initial', 'AS')
        ->assertJsonPath('data.0.access.roles', ['user'])
        ->assertJsonPath('meta.total', 1);

    $this->withToken(clientToken($hr, $secret))->getJson("/api/v1/users/{$ayesha->id}")->assertOk()->assertJsonPath('data.name', 'Ayesha');
    $this->withToken(clientToken($hr, $secret))->getJson("/api/v1/users/{$bilal->id}")->assertNotFound();
});

it('searches by name, username or employee ID', function () {
    [$hr, $secret] = apiApp('hr');
    foreach (['Ayesha Siddiqui', 'Bilal Ahmed'] as $name) {
        app(GrantApplicationAccess::class)->handle($hr, User::factory()->create(['name' => $name]));
    }

    $this->withToken(clientToken($hr, $secret))->getJson('/api/v1/users?search=bilal')
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Bilal Ahmed');
});

it('rejects user tokens, missing scopes and deactivated applications', function () {
    [$hr, $secret] = apiApp('hr');

    $this->getJson('/api/v1/users')->assertUnauthorized();
    $this->withToken(clientToken($hr, $secret, 'openid'))->getJson('/api/v1/users')->assertForbidden();

    $token = clientToken($hr, $secret);
    $hr->update(['active' => false]);
    $this->withToken($token)->getJson('/api/v1/users')->assertForbidden();
});

it('includes the numeric user id and initials in the employee claims', function () {
    [$hr] = apiApp('hr');
    $user = User::factory()->create(['emp_id' => '20456', 'emp_initial' => 'AK']);

    $claims = app(Claims::class)->for($user, $hr, ['openid', 'employee']);

    expect($claims)->toMatchArray(['user_id' => $user->id, 'emp_id' => '20456', 'emp_initial' => 'AK']);
});
