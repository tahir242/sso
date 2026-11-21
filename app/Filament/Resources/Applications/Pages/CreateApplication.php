<?php

namespace App\Filament\Resources\Applications\Pages;

use App\Domain\Federation\Actions\RegisterApplication;
use App\Filament\Resources\Applications\ApplicationResource;
use App\Filament\Resources\Applications\Pages\Concerns\ShowsClientSecret;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class CreateApplication extends CreateRecord
{
    use ShowsClientSecret;

    protected static string $resource = ApplicationResource::class;

    protected static ?string $title = 'Register application';

    protected function handleRecordCreation(array $data): Model
    {
        [$application, $secret] = app(RegisterApplication::class)->handle(
            Arr::except($data, ['redirect_uris']),
            $data['redirect_uris'] ?? [],
        );

        $this->notifyClientSecret($application, $secret, 'Application registered');

        return $application;
    }

    protected function getCreatedNotification(): null
    {
        return null; // replaced by the client-secret notification
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
