<?php

use App\Domain\Access\Actions\CancelAccessRequest;
use App\Domain\Access\Actions\DecideApprovalStep;
use App\Domain\Access\Actions\EscalateOverdueApprovals;
use App\Domain\Access\Actions\SubmitAccessRequest;
use App\Domain\Access\Enums\AccessRequestStatus;
use App\Domain\Access\Enums\ApprovalStepStatus;
use App\Domain\Access\Enums\ApprovalStepType;
use App\Domain\Access\Models\AccessRequest;
use App\Domain\Access\Notifications\AccessRequestDecided;
use App\Domain\Access\Notifications\ApprovalNeeded;
use App\Domain\Access\Notifications\ApprovalOverdue;
use App\Domain\Federation\Actions\GrantApplicationAccess;
use App\Domain\Federation\Actions\RegisterApplication;
use App\Domain\Federation\Models\Application;
use App\Domain\Identity\Models\Department;
use App\Domain\Identity\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Notification::fake();
    foreach (['super-admin', 'it-security'] as $role) {
        Role::findOrCreate($role, 'web');
    }

    $this->hod = User::factory()->create(['name' => 'Dr. HOD']);
    $this->owner = User::factory()->create(['name' => 'App Owner']);
    $this->department = Department::create(['code' => 'HR', 'name' => 'Human Resources', 'hod_user_id' => $this->hod->id]);
    $this->employee = User::factory()->create(['department_id' => $this->department->id]);

    [$this->application] = app(RegisterApplication::class)->handle([
        'slug' => 'hr', 'name' => 'HR System', 'base_url' => 'https://hr.example.test',
        'owner_user_id' => $this->owner->id, 'active' => true,
    ], ['https://hr.example.test/callback']);
    $this->clerk = $this->application->roles()->create(['key' => 'clerk', 'name' => 'Clerk']);
});

function submitRequest(User $user, Application $app, array $roles = [], ?string $until = null): AccessRequest
{
    return app(SubmitAccessRequest::class)->handle($user, $app, $user, $roles, 'I process leave applications.', $until ? now()->parse($until) : null);
}

function decide(AccessRequest $request, User $approver, bool $approve = true, ?string $comment = null): void
{
    app(DecideApprovalStep::class)->handle($request->fresh()->currentStep(), $approver, $approve, $comment);
}

it('runs HOD then owner approval and grants the requested roles', function () {
    $request = submitRequest($this->employee, $this->application, [$this->clerk->id], now()->addMonth()->toDateString());

    expect($request->steps()->pluck('type')->all())->toBe([ApprovalStepType::HeadOfDepartment, ApprovalStepType::ApplicationOwner])
        ->and($request->fresh()->currentStep()->approver_id)->toBe($this->hod->id);
    Notification::assertSentTo($this->hod, ApprovalNeeded::class);

    decide($request, $this->hod);
    expect($request->fresh()->currentStep()->approver_id)->toBe($this->owner->id);
    Notification::assertSentTo($this->owner, ApprovalNeeded::class);

    decide($request, $this->owner);

    $assignment = $this->application->assignmentFor($this->employee);
    expect($request->fresh()->status)->toBe(AccessRequestStatus::Approved)
        ->and($assignment->roleKeys())->toBe(['clerk'])
        ->and($assignment->expires_at->toDateString())->toBe(now()->addMonth()->toDateString())
        ->and($assignment->granted_by)->toBe($this->owner->id);
    Notification::assertSentTo($this->employee, AccessRequestDecided::class);
});

it('follows the application policy: skip HOD, add IT security', function () {
    $this->application->update(['approval_policy' => ['skip_hod' => true, 'require_it_security' => true]]);
    $security = tap(User::factory()->create(), fn (User $u) => $u->assignRole('it-security'));

    $request = submitRequest($this->employee, $this->application);

    expect($request->steps()->pluck('type')->all())->toBe([ApprovalStepType::ApplicationOwner, ApprovalStepType::ItSecurity]);

    decide($request, $this->owner);
    Notification::assertSentTo($security, ApprovalNeeded::class);

    decide($request, $security);
    expect($request->fresh()->status)->toBe(AccessRequestStatus::Approved);
});

it('routes to administrators when there is no head of department', function () {
    $admin = tap(User::factory()->create(), fn (User $u) => $u->assignRole('super-admin'));
    $loner = User::factory()->create(['department_id' => null]);

    $request = submitRequest($loner, $this->application);
    $step = $request->fresh()->currentStep();

    expect($step->approver_id)->toBeNull()
        ->and($step->canBeDecidedBy($admin))->toBeTrue()
        ->and($step->canBeDecidedBy($this->hod))->toBeFalse();
    Notification::assertSentTo($admin, ApprovalNeeded::class);
});

it('skips the HOD step when the person is the head of department', function () {
    $this->hod->update(['department_id' => $this->department->id]);
    $request = submitRequest($this->hod->fresh(), $this->application);

    expect($request->steps()->first()->status)->toBe(ApprovalStepStatus::Skipped)
        ->and($request->fresh()->currentStep()->type)->toBe(ApprovalStepType::ApplicationOwner);
});

it('requires a reason to reject and stops the chain', function () {
    $request = submitRequest($this->employee, $this->application);

    expect(fn () => decide($request, $this->hod, false))->toThrow(ValidationException::class);

    decide($request, $this->hod, false, 'Not part of your duties.');

    expect($request->fresh()->status)->toBe(AccessRequestStatus::Rejected)
        ->and($request->steps()->where('type', ApprovalStepType::ApplicationOwner)->value('status'))->toBe(ApprovalStepStatus::Skipped)
        ->and($this->application->assignmentFor($this->employee))->toBeNull();
    Notification::assertSentTo($this->employee, AccessRequestDecided::class);
});

it('only lets the right people decide', function () {
    $request = submitRequest($this->employee, $this->application);
    $admin = tap(User::factory()->create(), fn (User $u) => $u->assignRole('super-admin'));

    expect(fn () => decide($request, $this->owner))->toThrow(AuthorizationException::class)
        ->and(fn () => decide($request, $this->employee))->toThrow(AuthorizationException::class);

    decide($request, $admin); // super-admin may stand in for an absent HOD
    expect($request->fresh()->currentStep()->type)->toBe(ApprovalStepType::ApplicationOwner);
});

it('never lets people approve their own access', function () {
    $this->employee->assignRole('super-admin');
    $request = submitRequest($this->employee, $this->application);

    expect($request->fresh()->currentStep()->canBeDecidedBy($this->employee))->toBeFalse();
});

it('refuses duplicate requests and requests for existing access', function () {
    submitRequest($this->employee, $this->application);
    expect(fn () => submitRequest($this->employee, $this->application))->toThrow(ValidationException::class);

    $other = User::factory()->create();
    app(GrantApplicationAccess::class)->handle($this->application, $other);
    expect(fn () => submitRequest($other, $this->application))->toThrow(ValidationException::class);
});

it('lets the requester cancel a pending request', function () {
    $request = submitRequest($this->employee, $this->application);

    app(CancelAccessRequest::class)->handle($request, $this->employee);

    expect($request->fresh()->status)->toBe(AccessRequestStatus::Cancelled)
        ->and($request->fresh()->currentStep())->toBeNull();
    expect(fn () => app(CancelAccessRequest::class)->handle($request->fresh(), $this->hod))->toThrow(AuthorizationException::class);
});

it('escalates overdue approvals once', function () {
    $admin = tap(User::factory()->create(), fn (User $u) => $u->assignRole('super-admin'));
    submitRequest($this->employee, $this->application);

    $this->travel(49)->hours();

    expect(app(EscalateOverdueApprovals::class)->handle())->toBe(1)
        ->and(app(EscalateOverdueApprovals::class)->handle())->toBe(0);
    Notification::assertSentTo([$this->hod, $admin], ApprovalOverdue::class);
});
