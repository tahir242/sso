<?php

namespace App\Filament\Resources\Users\Pages;

use App\Domain\Audit\Enums\AuthEventType;
use App\Domain\Audit\Models\AuthEvent;
use App\Domain\Federation\Actions\SyncUserApplications;
use App\Domain\Identity\Enums\AuthSource;
use App\Filament\Resources\Users\Pages\Concerns\SyncsFromDirectory;
use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    use SyncsFromDirectory;

    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (AuthSource::of($data['auth_source'] ?? null) === AuthSource::Local) {
            $data['password_changed_at'] = now();
        } else {
            $data['password'] = null; // AD accounts never keep a local password
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        if ($this->record->auth_source === AuthSource::ActiveDirectory) {
            // The username was checked against AD by the form; take the authoritative details from AD.
            $this->syncFromDirectory($this->record, 'Active Directory account added');
            AuthEvent::record(AuthEventType::UserProvisioned, $this->record, meta: ['source' => 'active-directory', 'by' => auth()->user()?->username]);
        }

        app(SyncUserApplications::class)->handle($this->record, $this->data['application_ids'] ?? [], auth()->user());
    }
}
