<?php

namespace App\Livewire;

use App\Domain\Federation\Models\Application;
use App\Domain\Federation\Models\ApplicationAssignment;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * App launcher: every application the signed-in user can open (assigned to
 * them, or open to everyone).
 */
#[Layout('layouts::app')]
#[Title('My applications')]
class MyApplications extends Component
{
    public string $search = '';

    public function render(): View
    {
        $user = auth()->user();

        $assignments = ApplicationAssignment::query()->current()->where('user_id', $user->getKey())
            ->with('roles')->get()->keyBy('application_id');

        $applications = Application::query()->openableBy($user)
            ->with(['roles' => fn ($q) => $q->where('is_default', true)])
            ->orderBy('name')
            ->get()
            ->map(fn (Application $application) => (object) [
                'application' => $application,
                'assignment' => $assignment = $assignments->get($application->id),
                'roles' => $assignment ? $assignment->roles : $application->roles,
            ]);

        $filtered = $this->search === ''
            ? $applications
            : $applications->filter(fn (object $item) => str_contains(
                mb_strtolower($item->application->name.' '.$item->application->description),
                mb_strtolower($this->search),
            ));

        return view('livewire.my-applications', [
            'user' => $user,
            'total' => $applications->count(),
            'items' => $filtered->values(),
        ]);
    }
}
