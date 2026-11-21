<?php

namespace App\Filament\Resources\Users\Pages;

use App\Domain\Federation\Actions\SyncUserApplications;
use App\Domain\Identity\Directory\ActiveDirectory;
use App\Domain\Identity\Enums\AuthSource;
use App\Domain\Identity\Models\User;
use App\Filament\Resources\Users\Pages\Concerns\SyncsFromDirectory;
use App\Filament\Resources\Users\UserResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

/**
 * @property User $record
 */
class EditUser extends EditRecord
{
    use SyncsFromDirectory;

    protected static string $resource = UserResource::class;

    private ?AuthSource $sourceBeforeSave = null;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refreshFromDirectory')
                ->label('Refresh from Active Directory')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->visible(fn () => $this->record->auth_source === AuthSource::ActiveDirectory && app(ActiveDirectory::class)->isEnabled())
                ->requiresConfirmation()
                ->modalDescription('Replaces the name, email, employee ID, department, job title and mobile with the values in Active Directory. Initials, location, roles and applications are kept.')
                ->action(function (): void {
                    if ($this->syncFromDirectory($this->record)) {
                        $this->refreshFormData(['name', 'email', 'emp_id', 'emp_initial', 'designation', 'mobile', 'department_id']);
                    }
                }),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['application_ids'] = SyncUserApplications::currentIds($this->record);

        return $data;
    }

    protected function beforeSave(): void
    {
        $this->sourceBeforeSave = $this->record->getOriginal('auth_source') instanceof AuthSource
            ? $this->record->getOriginal('auth_source')
            : AuthSource::of($this->record->getOriginal('auth_source'));
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (filled($data['password'] ?? null)) {
            $data['password_changed_at'] = now();
            $data['legacy_md5'] = false;
        }

        // AD accounts never keep a local password.
        if (AuthSource::of($data['auth_source'] ?? null) === AuthSource::ActiveDirectory) {
            $data['password'] = null;
        }

        return $data;
    }

    protected function afterSave(): void
    {
        // Switched from a local account to Active Directory: take the person's details from AD.
        if ($this->sourceBeforeSave === AuthSource::Local && $this->record->auth_source === AuthSource::ActiveDirectory) {
            if ($this->syncFromDirectory($this->record, 'Switched to Active Directory — details updated from AD')) {
                $this->refreshFormData(['name', 'email', 'emp_id', 'emp_initial', 'designation', 'mobile', 'department_id']);
            }
        }

        $changes = app(SyncUserApplications::class)->handle($this->record, $this->data['application_ids'] ?? [], auth()->user());

        $lines = array_filter([
            $changes['added'] ? 'Access given: '.implode(', ', $changes['added']) : null,
            $changes['removed'] ? 'Access removed: '.implode(', ', $changes['removed']) : null,
        ]);

        if ($lines) {
            Notification::make()->title('Applications updated')->body(implode("\n", $lines))->success()->send();
        }
    }
}
