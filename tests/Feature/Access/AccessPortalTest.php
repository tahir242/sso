<?php

use App\Domain\Access\Enums\AccessRequestStatus;
use App\Domain\Access\Models\AccessRequest;
use App\Domain\Federation\Actions\RegisterApplication;
use App\Domain\Identity\Actions\CompleteLogin;
use App\Domain\Identity\Models\Department;
use App\Domain\Identity\Models\User;
use App\Livewire\Access\Approvals;
use App\Livewire\Access\MyRequests;
use App\Livewire\Access\RequestAccess;
use Livewire\Livewire;

beforeEach(function () {
    $this->hod = User::factory()->create();
    $this->owner = User::factory()->create();
    $department = Department::create(['code' => 'LAB', 'name' => 'Laboratory', 'hod_user_id' => $this->hod->id]);
    $this->employee = User::factory()->create(['department_id' => $department->id]);

    [$this->application] = app(RegisterApplication::class)->handle([
        'slug' => 'lab', 'name' => 'Lab Reports', 'base_url' => 'https://lab.example.test',
        'owner_user_id' => $this->owner->id, 'active' => true,
    ], ['https://lab.example.test/callback']);
});

it('lets a user request access from the portal', function () {
    $this->actingAs($this->employee);

    Livewire::withQueryParams(['app' => 'lab'])
        ->test(RequestAccess::class)
        ->assertSee('Head of department')
        ->set('justification', 'short')
        ->call('submit')
        ->assertHasErrors('justification')
        ->set('justification', 'I review laboratory results for ward patients.')
        ->call('submit')
        ->assertRedirect(route('access.requests'));

    expect(AccessRequest::where('user_id', $this->employee->id)->value('status'))->toBe(AccessRequestStatus::Pending);
});

it('shows approvers what is waiting and lets them decide', function () {
    $this->actingAs($this->employee);
    Livewire::withQueryParams(['app' => 'lab'])->test(RequestAccess::class)
        ->set('justification', 'I review laboratory results for ward patients.')->call('submit');

    $this->actingAs($this->hod);
    $step = AccessRequest::first()->currentStep();

    Livewire::test(Approvals::class)
        ->assertSee($this->employee->name)
        ->call('reject', $step->id)
        ->assertHasErrors("comments.{$step->id}")
        ->call('approve', $step->id)
        ->assertHasNoErrors()
        ->assertSee('Nothing waiting for you');

    $this->actingAs($this->owner);
    Livewire::test(Approvals::class)->call('approve', AccessRequest::first()->currentStep()->id);

    expect($this->application->assignmentFor($this->employee))->not->toBeNull();

    $this->actingAs($this->employee);
    Livewire::test(MyRequests::class)->assertSee('Approved');
});

it('offers a request-access button on the no-access page', function () {
    $this->actingAs($this->employee)
        ->withSession([CompleteLogin::STARTED_AT => now()->getTimestamp()])
        ->get('/oauth/authorize?'.http_build_query([
            'client_id' => $this->application->oauth_client_id, 'redirect_uri' => 'https://lab.example.test/callback',
            'response_type' => 'code', 'scope' => 'openid', 'state' => 's',
            'code_challenge' => str_repeat('a', 43), 'code_challenge_method' => 'S256',
        ]))
        ->assertForbidden()
        ->assertSee(route('access.request', ['app' => 'lab']), false);
});

it('shows pending approvals in the navigation', function () {
    $this->actingAs($this->employee);
    Livewire::withQueryParams(['app' => 'lab'])->test(RequestAccess::class)
        ->set('justification', 'I review laboratory results for ward patients.')->call('submit');

    $this->actingAs($this->hod)->get(route('home'))->assertOk()->assertSee('Approvals');
});
