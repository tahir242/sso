<?php

namespace App\Livewire\Access;

use App\Domain\Access\Actions\SubmitAccessRequest;
use App\Domain\Access\Models\AccessRequest;
use App\Domain\Federation\Models\Application;
use App\Domain\Identity\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts::app')]
#[Title('Request access')]
class RequestAccess extends Component
{
    #[Url(as: 'app')]
    public string $application = '';

    /** @var array<int, int|string> */
    public array $roles = [];

    public string $justification = '';

    public ?string $accessUntil = null;

    public function updatedApplication(): void
    {
        $this->reset('roles');
    }

    public function submit(SubmitAccessRequest $submit): void
    {
        $this->validate([
            'application' => ['required', 'string'],
            'roles' => ['array'],
            'roles.*' => ['integer'],
            'justification' => ['required', 'string', 'min:10', 'max:1000'],
            'accessUntil' => ['nullable', 'date', 'after:today'],
        ], attributes: ['accessUntil' => 'end date']);

        $application = $this->selectedApplication();
        abort_unless($application, 404);

        $submit->handle(
            $this->user(),
            $application,
            $this->user(),
            array_map('intval', $this->roles),
            $this->justification,
            $this->accessUntil ? Carbon::parse($this->accessUntil) : null,
        );

        session()->flash('status', __('Your request for :app has been sent for approval.', ['app' => $application->name]));
        $this->redirectRoute('access.requests');
    }

    public function render(): View
    {
        $user = $this->user();

        $pendingIds = AccessRequest::pending()->where('user_id', $user->getKey())->pluck('application_id');

        $available = Application::query()->active()
            ->whereDoesntHave('assignments', fn ($q) => $q->current()->where('user_id', $user->getKey()))
            ->whereNotIn('id', $pendingIds)
            ->orderBy('name')
            ->get();

        $selected = $this->selectedApplication();

        return view('livewire.access.request-access', [
            'available' => $available,
            'selected' => $available->contains($selected) ? $selected : null,
            'selectedRoles' => $available->contains($selected) ? $selected->roles()->orderBy('name')->get() : collect(),
            'alreadyHasAccess' => $selected && $selected->assignmentFor($user),
            'alreadyPending' => $selected && $pendingIds->contains($selected->id),
            'needsHod' => $selected && ! ($selected->approval_policy['skip_hod'] ?? false),
            'hod' => $user->department?->hod,
        ]);
    }

    private function selectedApplication(): ?Application
    {
        return $this->application !== '' ? Application::query()->active()->where('slug', $this->application)->first() : null;
    }

    private function user(): User
    {
        /** @var User */
        return auth()->user();
    }
}
