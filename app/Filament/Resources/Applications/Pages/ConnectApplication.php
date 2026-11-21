<?php

namespace App\Filament\Resources\Applications\Pages;

use App\Domain\Federation\Integration\Connection;
use App\Domain\Federation\Integration\PhpKit;
use App\Domain\Federation\Models\Application;
use App\Filament\Resources\Applications\ApplicationResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Everything an application's developers need to connect it: its values,
 * a ready PHP kit, and guides for other stacks with those values filled in.
 */
class ConnectApplication extends Page
{
    use InteractsWithRecord;

    protected static string $resource = ApplicationResource::class;

    protected string $view = 'filament.applications.connect';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function getTitle(): string|Htmlable
    {
        return 'Connect '.$this->getRecord()->name;
    }

    public function getBreadcrumb(): string
    {
        return 'Connect';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        /** @var Application $application */
        $application = $this->getRecord();

        return [
            'connection' => Connection::for($application),
            'application' => $application,
            'usesGate' => collect($application->client?->redirect_uris ?? [])->contains(fn (string $uri) => str_contains($uri, 'sso=callback')),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('kit')
                ->label('Download PHP kit')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->action(function (PhpKit $kit): BinaryFileResponse {
                    $path = $kit->build($this->getRecord());

                    return response()->download($path, $this->getRecord()->slug.'-sso-kit.zip')->deleteFileAfterSend();
                }),
            Action::make('useGate')
                ->label('Register PHP gate URLs')
                ->icon(Heroicon::OutlinedLink)
                ->color('gray')
                ->visible(fn () => ApplicationResource::isSuperAdmin())
                ->modalDescription(fn () => 'Adds '.Connection::for($this->getRecord())->gatePage().'?sso=callback as a redirect URI, and sets the back-channel logout URI if none is set. Existing redirect URIs keep working.')
                ->requiresConfirmation()
                ->action(function (): void {
                    /** @var Application $application */
                    $application = $this->getRecord();
                    $gate = Connection::for($application)->gatePage();

                    $application->client->forceFill([
                        'redirect_uris' => array_values(array_unique([...($application->client->redirect_uris ?? []), $gate.'?sso=callback'])),
                    ])->save();

                    if (! $application->backchannel_logout_uri) {
                        $application->update(['backchannel_logout_uri' => $gate.'?sso=backchannel-logout']);
                    }

                    Notification::make()->title('PHP gate URLs registered')->success()->send();
                }),
            Action::make('edit')
                ->label('Edit settings')
                ->color('gray')
                ->url(fn () => ApplicationResource::getUrl('edit', ['record' => $this->getRecord()])),
        ];
    }
}
