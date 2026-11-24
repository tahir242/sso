<?php

namespace App\Filament\Pages;

use App\Support\Operations\MaintenanceCommands;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;
use UnitEnum;

/**
 * Runs the predefined artisan commands in MaintenanceCommands. Super-admin
 * only; every run is written to the activity log (log "maintenance").
 */
class MaintenanceConsole extends Page
{
    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCommandLine;

    protected static ?string $navigationLabel = 'Maintenance commands';

    protected static ?string $title = 'Maintenance commands';

    protected static ?string $slug = 'settings/maintenance';

    protected static ?int $navigationSort = 41;

    protected string $view = 'filament.pages.maintenance-console';

    /** Result of the last command run on this page. */
    public ?array $result = null;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasRole('super-admin');
    }

    public function runAction(): Action
    {
        return Action::make('run')
            ->label('Run')
            ->icon(Heroicon::OutlinedPlay)
            ->size('sm')
            ->color(fn (array $arguments) => (MaintenanceCommands::find($arguments['command'] ?? null)['danger'] ?? false) ? 'danger' : 'gray')
            ->requiresConfirmation()
            ->modalHidden(fn (array $arguments) => ! isset(MaintenanceCommands::find($arguments['command'] ?? null)['confirm']))
            ->modalHeading(fn (array $arguments) => (MaintenanceCommands::find($arguments['command'] ?? null)['label'] ?? 'Run command').'?')
            ->modalDescription(fn (array $arguments) => MaintenanceCommands::find($arguments['command'] ?? null)['confirm'] ?? null)
            ->modalSubmitActionLabel('Run')
            ->action(function (array $arguments): void {
                if (! MaintenanceCommands::find($arguments['command'] ?? null)) {
                    return;
                }

                $this->result = MaintenanceCommands::run($arguments['command'], auth()->user());

                $ok = $this->result['exit_code'] === 0;
                Notification::make()
                    ->title($this->result['label'].($ok ? ' finished' : ' failed'))
                    ->body($this->result['command'].' · '.number_format($this->result['duration_ms'] / 1000, 1).' s')
                    ->{$ok ? 'success' : 'danger'}()
                    ->send();

                $this->dispatch('maintenance-ran');
            });
    }

    /**
     * @return Collection<int, Activity>
     */
    public function history(): Collection
    {
        return Activity::query()
            ->inLog(MaintenanceCommands::LOG)
            ->with('causer')
            ->latest('id')
            ->limit(15)
            ->get();
    }

    public function showRun(int $id): void
    {
        $activity = Activity::query()->inLog(MaintenanceCommands::LOG)->find($id);
        if (! $activity) {
            return;
        }

        $properties = $activity->properties;
        $definition = MaintenanceCommands::find($properties['key'] ?? null);

        $this->result = [
            'key' => $properties['key'] ?? '',
            'label' => $definition['label'] ?? ($properties['command'] ?? ''),
            'command' => $properties['command'] ?? '',
            'exit_code' => (int) ($properties['exit_code'] ?? 1),
            'output' => (string) ($properties['output'] ?? ''),
            'duration_ms' => (int) ($properties['duration_ms'] ?? 0),
            'at' => $activity->created_at?->toDateTimeString() ?? '',
        ];
    }
}
