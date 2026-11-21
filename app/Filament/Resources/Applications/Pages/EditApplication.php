<?php

namespace App\Filament\Resources\Applications\Pages;

use App\Domain\Federation\Actions\RotateClientSecret;
use App\Domain\Federation\Models\Application;
use App\Filament\Resources\Applications\ApplicationResource;
use App\Filament\Resources\Applications\Pages\Concerns\ShowsClientSecret;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

/**
 * @property Application $record
 */
class EditApplication extends EditRecord
{
    use ShowsClientSecret;

    protected static string $resource = ApplicationResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['redirect_uris'] = $this->record->client?->redirect_uris ?? [];

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Application $record */
        $record->update(Arr::except($data, ['redirect_uris']));

        if (ApplicationResource::isSuperAdmin() && array_key_exists('redirect_uris', $data)) {
            $record->client->forceFill([
                'name' => $record->name,
                'redirect_uris' => array_values($data['redirect_uris'] ?? []),
            ])->save();
        }

        return $record;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('connect')
                ->label('Connect')
                ->icon(Heroicon::OutlinedLink)
                ->url(fn () => ApplicationResource::getUrl('connect', ['record' => $this->record])),
            Action::make('rotateSecret')
                ->label('New client secret')
                ->icon(Heroicon::OutlinedKey)
                ->color('gray')
                ->visible(fn () => ApplicationResource::isSuperAdmin())
                ->requiresConfirmation()
                ->modalDescription('The current secret stops working immediately. The application will fail to sign users in until its configuration is updated with the new secret.')
                ->action(fn (RotateClientSecret $rotate) => $this->notifyClientSecret($this->record, $rotate->handle($this->record), 'New client secret')),
        ];
    }
}
