<?php

namespace App\Livewire\Requests;

use App\Domain\Federation\Enums\AccessMode;
use App\Domain\Federation\Models\Application;
use App\Domain\Identity\Models\Department;
use App\Domain\Requests\Actions\SubmitNewStaffRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * "A new colleague needs an account": a signed-in member of staff asks for
 * an account for someone who joined. The head of department confirms,
 * then the IT helpdesk creates the account.
 */
#[Layout('layouts::app')]
#[Title('New staff account')]
class NewStaffForm extends Component
{
    public string $full_name = '';

    public string $emp_id = '';

    public string $email = '';

    public string $mobile = '';

    public ?int $department_id = null;

    public string $designation = '';

    public ?string $joining_date = null;

    /** @var array<int, int|string> */
    public array $application_ids = [];

    public string $justification = '';

    #[Locked]
    public ?string $reference = null;

    public function mount(): void
    {
        $this->department_id = auth()->user()->department_id;
    }

    public function submit(SubmitNewStaffRequest $submit): void
    {
        $key = 'new-staff:'.auth()->id();

        if (RateLimiter::tooManyAttempts($key, 10)) {
            throw ValidationException::withMessages(['full_name' => __('You have sent many requests in the last hour. Please try again later or contact the IT helpdesk.')]);
        }

        $data = $this->validate([
            'full_name' => ['required', 'string', 'min:3', 'max:150'],
            'emp_id' => ['nullable', 'string', 'max:30', Rule::unique('users', 'emp_id')],
            'email' => ['required', 'email:rfc', 'max:191'],
            'mobile' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]{7,}$/'],
            'department_id' => ['required', Rule::exists('departments', 'id')->where('active', true)],
            'designation' => ['required', 'string', 'max:150'],
            'joining_date' => ['nullable', 'date', 'after:-1 year', 'before:+6 months'],
            'application_ids' => ['array', 'max:15'],
            'application_ids.*' => ['integer', Rule::exists('applications', 'id')->where('active', true)],
            'justification' => ['nullable', 'string', 'max:1000'],
        ], attributes: ['emp_id' => 'employee ID', 'department_id' => 'department', 'application_ids' => 'applications']);

        RateLimiter::hit($key, 3600);

        $request = $submit->handle($data, request()->ip());
        $this->reference = strtoupper(substr($request->ulid, -6));
    }

    public function render(): View
    {
        return view('livewire.requests.new-staff-form', [
            'departments' => Department::where('active', true)->orderBy('name')->get(['id', 'name']),
            // Applications open to everyone need no request.
            'applications' => Application::query()->active()->where('access_mode', AccessMode::Assigned)
                ->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
