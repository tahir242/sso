<?php

namespace App\Filament\Forms\Components;

use App\Domain\Federation\Enums\AccessMode;
use App\Domain\Federation\Models\Application;
use App\Domain\Identity\Auth\RecentAccounts;
use Filament\Forms\Components\Field;

/**
 * Scrollable, searchable application cards with checkboxes, for giving a
 * user access on the user form. State: list of application ids.
 */
class ApplicationPicker extends Field
{
    protected string $view = 'filament.forms.application-picker';

    protected function setUp(): void
    {
        parent::setUp();

        $this->default([]);
        $this->afterStateHydrated(fn (ApplicationPicker $component, $state) => $component->state(array_values(array_map('intval', (array) $state))));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getApplications(): array
    {
        return Application::query()->active()
            ->with(['owner:id,name', 'roles' => fn ($q) => $q->where('is_default', true)])
            ->withCount(['assignments' => fn ($q) => $q->current()])
            ->orderBy('name')
            ->get()
            ->map(fn (Application $app) => [
                'id' => $app->id,
                'name' => $app->name,
                'initials' => RecentAccounts::initials($app->name),
                'description' => (string) $app->description,
                'owner' => $app->owner?->name,
                'users' => $app->assignments_count,
                'mfa' => $app->requires_mfa,
                'everyone' => $app->access_mode === AccessMode::Everyone,
                'roles' => $app->roles->pluck('name')->all(),
            ])
            ->all();
    }
}
