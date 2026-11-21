<?php

use App\Domain\Access\Enums\ApprovalStepType;
use App\Domain\Access\Models\AccessRequest;
use App\Domain\Federation\Enums\AccessMode;
use App\Domain\Identity\Models\Department;
use App\Domain\Identity\Models\User;
use App\Domain\Requests\Actions\CompleteNewStaffRequest;
use App\Domain\Requests\Actions\SubmitChangeRequest;
use App\Domain\Requests\Actions\UpdateChangeRequest;
use App\Domain\Requests\Enums\ChangeRequestStatus;
use App\Domain\Requests\Enums\ChangeRequestType;
use App\Domain\Requests\Enums\NewStaffStatus;
use App\Domain\Requests\Enums\Priority;
use App\Domain\Requests\Models\ChangeRequest;
use App\Domain\Requests\Models\NewStaffRequest;
use App\Filament\Resources\ChangeRequests\Pages\ListChangeRequests;
use App\Filament\Resources\ChangeRequests\Pages\ViewChangeRequest;
use App\Filament\Resources\NewStaffRequests\Pages\ViewNewStaffRequest;
use App\Livewire\Access\NewStaffApprovals;
use App\Livewire\Requests\ChangeRequestForm;
use App\Livewire\Requests\NewStaffForm;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

// Change requests ("Suggest an improvement") and new staff accounts: request
// workflows that stayed in SSO when the software portfolio was removed.

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->hod = User::factory()->create();
    $this->department = Department::create(['code' => 'LAB', 'name' => 'Laboratory', 'hod_user_id' => $this->hod->id]);
    $this->lab = makeApp('Lab Reports', $this->owner);
});

it('sends suggestions to the application owner who can reply', function () {
    $this->lab->update(['access_mode' => AccessMode::Everyone]);
    $user = User::factory()->create();

    Livewire::actingAs($user)->withQueryParams(['app' => 'lab-reports'])->test(ChangeRequestForm::class)
        ->assertSet('app', 'lab-reports')
        ->set('title', 'Bug')
        ->call('submit')
        ->assertHasErrors(['title', 'description'])
        ->set('type', 'bug') // problems go to the helpdesk, not here
        ->call('submit')
        ->assertHasErrors(['type'])
        ->set('type', 'change')
        ->set('title', 'Print reports in landscape')
        ->set('description', 'Wide creatinine trend reports are cut off on the right when printed.')
        ->set('attachments', [UploadedFile::fake()->image('screen.png')])
        ->call('submit')
        ->assertRedirect(route('access.requests', ['tab' => 'changes']));

    $request = ChangeRequest::sole();
    expect($request->getMedia('attachments'))->toHaveCount(1)
        ->and($this->owner->notifications()->count())->toBe(1)
        ->and(UpdateChangeRequest::canManage($request, $user))->toBeFalse();

    app(UpdateChangeRequest::class)->handle($request, $this->owner, ChangeRequestStatus::InProgress, 'Looking into it.');

    expect($request->fresh()->status)->toBe(ChangeRequestStatus::InProgress)
        ->and($user->notifications()->count())->toBe(1);

    $this->actingAs($user)->get(route('access.requests', ['tab' => 'changes']))->assertSee('Looking into it.');
});

it('offers suggestions only for applications the user can open', function () {
    $user = User::factory()->create();

    // Assigned-only application without access: not offered, ?app= ignored.
    Livewire::actingAs($user)->withQueryParams(['app' => 'lab-reports'])->test(ChangeRequestForm::class)
        ->assertSet('app', '')
        ->assertDontSee('Lab Reports')
        ->set('app', 'lab-reports')
        ->set('title', 'Add a landscape print option')
        ->set('description', 'Wide reports are cut off when printed on A4 paper.')
        ->call('submit')
        ->assertHasErrors(['app']);

    expect(ChangeRequest::count())->toBe(0);
});

it('turns a new staff request from a colleague into an account with access requests', function () {
    Role::findOrCreate('helpdesk');
    $helpdesk = User::factory()->create()->assignRole('helpdesk');
    $colleague = User::factory()->create(['department_id' => $this->department->id]);

    Livewire::actingAs($colleague)->test(NewStaffForm::class)
        ->assertSet('department_id', $this->department->id) // the requester's department by default
        ->assertSee('Lab Reports')
        ->set('full_name', 'Ayesha Khan')
        ->set('email', 'ayesha@example.test')
        ->set('designation', 'Lab Technologist')
        ->set('application_ids', [$this->lab->id])
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('reference', fn ($ref) => strlen($ref) === 6);

    $request = NewStaffRequest::sole();
    expect($request->status)->toBe(NewStaffStatus::Pending)
        ->and($this->hod->notifications()->count())->toBe(1);

    Livewire::actingAs($this->hod)->test(NewStaffApprovals::class)
        ->assertSee('Ayesha Khan')
        ->call('decide', $request->id, false)
        ->assertHasErrors("comments.{$request->id}")
        ->call('decide', $request->id, true);

    expect($request->fresh()->status)->toBe(NewStaffStatus::Approved)
        ->and($helpdesk->notifications()->count())->toBe(1);

    $user = app(CompleteNewStaffRequest::class)->handle($request->fresh(), $helpdesk, ['username' => 'ayesha.khan', 'auth_source' => 'ad']);

    expect($user->department_id)->toBe($this->department->id)
        ->and($request->fresh()->status)->toBe(NewStaffStatus::Completed);

    // The HOD already confirmed, so the access request waits on the owner.
    $access = AccessRequest::where('user_id', $user->id)->sole();
    expect($access->currentStep()->type)->toBe(ApprovalStepType::ApplicationOwner);
});

it('needs sign-in for the new staff form, which is no longer public', function () {
    $this->get('/requests/new-staff')->assertRedirect('/login');
    $this->get('/new-staff')->assertNotFound();

    $this->actingAs(User::factory()->create())->get(route('requests.new'))
        ->assertOk()->assertSee(route('requests.new-staff'));
});

it('shows app owners only their own change requests and lets them reply', function () {
    $owner = User::factory()->create()->assignRole(Role::findOrCreate('app-owner', 'web'));
    $mine = makeApp('Pathology', $owner);
    $other = makeApp('Pharmacy');
    $user = User::factory()->create();

    $submit = app(SubmitChangeRequest::class);
    $a = $submit->handle($user, $mine, ChangeRequestType::Bug, 'Blank PDF', 'Printing gives an empty page.', Priority::High);
    $b = $submit->handle($user, $other, ChangeRequestType::Feature, 'Barcode labels', 'Please print barcode labels.', Priority::Normal);

    $this->actingAs($owner);
    Livewire::test(ListChangeRequests::class)->assertCanSeeTableRecords([$a])->assertCanNotSeeTableRecords([$b]);

    Livewire::test(ViewChangeRequest::class, ['record' => $a->getRouteKey()])
        ->callAction('update', ['status' => ChangeRequestStatus::Planned->value, 'comment' => 'Fix planned for next release.'])
        ->assertHasNoActionErrors();

    expect($a->fresh()->status)->toBe(ChangeRequestStatus::Planned);
});

it('lets the helpdesk create the account for an approved new staff request', function () {
    $helpdesk = User::factory()->create()->assignRole(Role::findOrCreate('helpdesk', 'web'));
    $request = NewStaffRequest::create([
        'full_name' => 'Ayesha Khan', 'department_id' => $this->department->id, 'designation' => 'Technologist',
        'application_ids' => [], 'status' => NewStaffStatus::Approved, 'requester_ip' => '10.1.1.1',
    ]);

    $this->actingAs($helpdesk);
    Livewire::test(ViewNewStaffRequest::class, ['record' => $request->getRouteKey()])
        ->assertActionVisible('complete')
        ->callAction('complete', ['username' => 'ayesha.khan', 'auth_source' => 'ad'])
        ->assertHasNoActionErrors();

    expect($request->fresh()->status)->toBe(NewStaffStatus::Completed)
        ->and(User::where('username', 'ayesha.khan')->value('department_id'))->toBe($this->department->id);
});

it('requires a temporary password for local new staff accounts', function () {
    $helpdesk = User::factory()->create()->assignRole(Role::findOrCreate('helpdesk', 'web'));
    $request = NewStaffRequest::create([
        'full_name' => 'Bilal Ahmed', 'department_id' => Department::create(['code' => 'PH', 'name' => 'Pharmacy'])->id,
        'application_ids' => [], 'status' => NewStaffStatus::Approved, 'requester_ip' => '10.1.1.1',
    ]);

    $this->actingAs($helpdesk);
    Livewire::test(ViewNewStaffRequest::class, ['record' => $request->getRouteKey()])
        ->callAction('complete', ['username' => 'bilal.ahmed', 'auth_source' => 'local'])
        ->assertHasActionErrors(['password' => 'required']);

    Livewire::test(ViewNewStaffRequest::class, ['record' => $request->getRouteKey()])
        ->callAction('complete', ['username' => 'bilal.ahmed', 'auth_source' => 'local', 'password' => 'Temporary-Pass-2026'])
        ->assertHasNoActionErrors();

    expect(User::where('username', 'bilal.ahmed')->sole()->auth_source->value)->toBe('local');
});
