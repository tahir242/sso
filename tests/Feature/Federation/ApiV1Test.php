<?php

use App\Domain\Access\Enums\AccessRequestStatus;
use App\Domain\Access\Models\AccessRequest;
use App\Domain\Federation\Actions\GrantApplicationAccess;
use App\Domain\Federation\ApiScopes;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\Department;
use App\Domain\Identity\Models\User;

beforeEach(function () {
    [$this->hr, $this->secret] = apiApp('hr');
    $this->hr->roles()->create(['key' => 'clerk', 'name' => 'Clerk']);
    $this->hod = User::factory()->create();
    $this->department = Department::create(['code' => 'FIN', 'name' => 'Finance', 'hod_user_id' => $this->hod->id]);
    $this->ayesha = User::factory()->create(['username' => 'ayesha.khan', 'emp_id' => '20456', 'department_id' => $this->department->id]);
});

function enableScopes(...$scopes): void
{
    test()->hr->update(['api_scopes' => $scopes]);
}

it('gives new applications the default scopes only', function () {
    expect($this->hr->api_scopes)->toBe(ApiScopes::DEFAULTS);

    $this->withToken(clientToken($this->hr, $this->secret, 'access-requests.read'))
        ->getJson('/api/v1/access-requests')
        ->assertForbidden()
        ->assertJsonPath('message', 'This application is not allowed to use: access-requests.read.');
});

it('lists the application’s roles with how many people hold them', function () {
    app(GrantApplicationAccess::class)->handle($this->hr, $this->ayesha, [$this->hr->roles()->where('key', 'clerk')->value('id')]);

    $this->withToken(clientToken($this->hr, $this->secret, 'roles.read'))->getJson('/api/v1/roles')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.key', 'clerk')
        ->assertJsonPath('data.0.users_count', 1)
        ->assertJsonPath('data.1.key', 'user')
        ->assertJsonPath('data.1.is_default', true);
});

it('requests access for a user through the normal approvals', function () {
    enableScopes(ApiScopes::ACCESS_REQUESTS_WRITE, ApiScopes::ACCESS_REQUESTS_READ);
    $token = clientToken($this->hr, $this->secret, 'access-requests.write access-requests.read');

    $id = $this->withToken($token)->postJson('/api/v1/access-requests', [
        'emp_id' => '20456',
        'roles' => ['clerk'],
        'justification' => 'Joining the payroll team next week.',
        'access_until' => now()->addMonth()->toDateString(),
    ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.user.username', 'ayesha.khan')
        ->assertJsonPath('data.requested_by.username', 'ayesha.khan')
        ->assertJsonPath('data.roles', ['clerk'])
        ->assertJsonPath('data.steps.0.type', 'hod')
        ->assertJsonPath('data.steps.0.status', 'pending')
        ->json('data.id');

    expect(AccessRequest::where('ulid', $id)->value('status'))->toBe(AccessRequestStatus::Pending)
        ->and($this->hr->assignmentFor($this->ayesha))->toBeNull()
        ->and($this->hod->notifications()->count())->toBe(1);

    $this->withToken($token)->getJson("/api/v1/access-requests/{$id}")->assertOk()->assertJsonPath('data.id', $id);
    $this->withToken($token)->getJson('/api/v1/access-requests?status=pending')->assertOk()->assertJsonPath('meta.total', 1);
    $this->withToken($token)->getJson('/api/v1/access-requests?status=approved')->assertOk()->assertJsonPath('meta.total', 0);

    // A second request for the same user is refused while the first is pending.
    $this->withToken($token)->postJson('/api/v1/access-requests', ['username' => 'ayesha.khan', 'justification' => 'Joining the payroll team next week.'])
        ->assertUnprocessable()->assertJsonValidationErrors('application');
});

it('validates who the request is for and who is asking', function () {
    enableScopes(ApiScopes::ACCESS_REQUESTS_WRITE);
    $token = clientToken($this->hr, $this->secret, 'access-requests.write');
    $post = fn (array $body) => $this->withToken($token)->postJson('/api/v1/access-requests', ['justification' => 'Joining the payroll team next week.', ...$body]);

    $post([])->assertUnprocessable()->assertJsonValidationErrors('user_id');
    $post(['username' => 'ayesha.khan', 'emp_id' => '20456'])->assertUnprocessable();
    $post(['username' => 'nobody'])->assertUnprocessable()->assertJsonValidationErrors('username');
    $post(['username' => 'ayesha.khan', 'roles' => ['admin']])->assertUnprocessable()->assertJsonValidationErrors('roles.0');
    $post(['username' => 'ayesha.khan', 'requested_by' => 'ghost'])->assertUnprocessable()->assertJsonValidationErrors('requested_by');

    $this->ayesha->update(['status' => UserStatus::Disabled]);
    $post(['user_id' => $this->ayesha->id])->assertUnprocessable()->assertJsonValidationErrors('user_id');
});

it('never shows another application’s access requests', function () {
    [$lab, $labSecret] = apiApp('lab');
    enableScopes(ApiScopes::ACCESS_REQUESTS_WRITE);
    $lab->update(['api_scopes' => [ApiScopes::ACCESS_REQUESTS_READ]]);

    $id = $this->withToken(clientToken($this->hr, $this->secret, 'access-requests.write'))
        ->postJson('/api/v1/access-requests', ['username' => 'ayesha.khan', 'justification' => 'Joining the payroll team next week.'])
        ->assertCreated()->json('data.id');

    $labToken = clientToken($lab, $labSecret, 'access-requests.read');
    $this->withToken($labToken)->getJson("/api/v1/access-requests/{$id}")->assertNotFound();
    $this->withToken($labToken)->getJson('/api/v1/access-requests')->assertJsonPath('meta.total', 0);
});

it('rate-limits each application separately', function () {
    $token = clientToken($this->hr, $this->secret, 'roles.read');

    for ($i = 0; $i < 120; $i++) {
        $this->withToken($token)->getJson('/api/v1/roles')->assertOk();
    }

    $this->withToken($token)->getJson('/api/v1/roles')->assertTooManyRequests();
});

it('shows the API documentation to app owners and super-admins only', function () {
    if (onUnsupportedSqlServer()) {
        $this->markTestSkipped('API docs need SQL Server 2017+ (schema inspection uses STRING_AGG).');
    }

    $this->get('/docs/api')->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->get('/docs/api')->assertForbidden();
    $this->actingAs(superAdmin())->get('/docs/api')->assertOk();
    $spec = $this->get('/docs/api.json')->assertOk()->assertJsonPath('info.title', 'SSO API')->json();

    expect($spec['components']['securitySchemes']['oauth2']['flows']['clientCredentials']['scopes'])->toBe(ApiScopes::all())
        ->and(array_keys($spec['paths']))->toContain('/v1/roles', '/v1/access-requests', '/v1/access-requests/{id}');
});
