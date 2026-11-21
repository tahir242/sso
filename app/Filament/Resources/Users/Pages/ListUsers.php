<?php

namespace App\Filament\Resources\Users\Pages;

use App\Domain\Federation\Actions\SyncUserApplications;
use App\Domain\Federation\Enums\AccessMode;
use App\Domain\Federation\Models\Application;
use App\Domain\Identity\Actions\ImportDirectoryUser;
use App\Domain\Identity\Directory\ActiveDirectory;
use App\Filament\Resources\Users\UserResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;
use LdapRecord\Models\ActiveDirectory\User as LdapUser;
use LogicException;
use Throwable;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->addFromActiveDirectoryAction(),
            CreateAction::make()->label('New local user'),
        ];
    }

    /**
     * Search Active Directory and create SSO accounts for the chosen people,
     * optionally giving them applications straight away. They sign in with
     * their Windows password; name, email, department etc. come from AD.
     */
    private function addFromActiveDirectoryAction(): Action
    {
        return Action::make('addFromActiveDirectory')
            ->label('Add from Active Directory')
            ->icon(Heroicon::OutlinedServerStack)
            ->visible(fn () => UserResource::canCreate())
            ->disabled(fn () => ! app(ActiveDirectory::class)->isEnabled())
            ->tooltip(fn () => app(ActiveDirectory::class)->isEnabled() ? null : 'Turn on Active Directory sign-in in Settings → Active Directory first.')
            ->modalHeading('Add people from Active Directory')
            ->modalDescription('Search by username, name, email or employee ID. People who already have an SSO account, or whose AD account is disabled, are shown but cannot be added.')
            ->modalSubmitActionLabel('Add to SSO')
            ->modalWidth('2xl')
            ->schema([
                Select::make('people')->label('People')->multiple()->required()->searchable()
                    ->searchDebounce(400)
                    ->searchPrompt('Type at least 2 characters of a username, name, email or employee ID')
                    ->noSearchResultsMessage('No one in Active Directory matches.')
                    ->getSearchResultsUsing(fn (string $search) => $this->directoryOptions($search))
                    ->getOptionLabelsUsing(fn (array $values) => collect($values)
                        ->mapWithKeys(fn (string $guid) => [$guid => ($u = app(ActiveDirectory::class)->findUserByGuid($guid)) ? self::label($u) : $guid])
                        ->all())
                    ->disableOptionWhen(fn (string $value) => Str::startsWith($value, ['exists:', 'disabled:'])),
                CheckboxList::make('applications')->label('Give access to (optional)')
                    ->options(fn () => Application::query()->active()
                        ->where('access_mode', AccessMode::Assigned)->orderBy('name')->pluck('name', 'id'))
                    ->columns(2)->searchable()->bulkToggleable()
                    ->helperText('Applications open to everyone need no selection. The application’s default roles are given.'),
            ])
            ->action(function (array $data, ImportDirectoryUser $import, SyncUserApplications $sync): void {
                $directory = app(ActiveDirectory::class);
                $added = [];
                $skipped = [];

                foreach ((array) $data['people'] as $guid) {
                    $ldapUser = Str::startsWith((string) $guid, ['exists:', 'disabled:']) ? null : $directory->findUserByGuid((string) $guid);

                    if (! $ldapUser || $ldapUser->isDisabled()) {
                        $skipped[] = 'one selection could not be added (not found, disabled in AD, or already in SSO)';

                        continue;
                    }

                    try {
                        $user = $import->handle($ldapUser, auth()->user());
                        $sync->handle($user, (array) ($data['applications'] ?? []), auth()->user());
                        $added[] = $user->name.($user->email ? '' : ' (no email in AD — asked at first sign-in)');
                    } catch (LogicException $e) {
                        $skipped[] = $e->getMessage();
                    }
                }

                Notification::make()
                    ->title($added ? count($added).' '.Str::plural('person', count($added)).' added' : 'Nobody was added')
                    ->body(collect([
                        $added ? 'Added: '.implode(', ', $added).'. They sign in with their Windows password.' : null,
                        $skipped ? 'Skipped: '.implode('; ', array_unique($skipped)).'.' : null,
                    ])->filter()->implode("\n"))
                    ->{$added ? 'success' : 'warning'}()
                    ->persistent()
                    ->send();
            });
    }

    /**
     * @return array<string, string> AD objectGUID => label. People already in the SSO, or
     *                               disabled in AD, get a prefixed key so they show but cannot be chosen.
     */
    private function directoryOptions(string $search): array
    {
        try {
            $people = app(ActiveDirectory::class)->search($search);
        } catch (Throwable $e) {
            Notification::make()->title('Active Directory search failed')->body(ActiveDirectory::explain($e->getMessage()))->danger()->send();

            return [];
        }

        $options = [];
        foreach ($people as $person) {
            $guid = (string) $person->getConvertedGuid();
            $existing = ImportDirectoryUser::existingAccount($person);
            $key = match (true) {
                $existing !== null => 'exists:'.$guid,
                $person->isDisabled() => 'disabled:'.$guid,
                default => $guid,
            };
            $options[$key] = self::label($person).($existing ? ' — already in SSO' : '');
        }

        return $options;
    }

    private static function label(LdapUser $person): string
    {
        $details = collect([
            $person->getFirstAttribute('samaccountname'),
            $person->getFirstAttribute('mail'),
            $person->getFirstAttribute('department'),
        ])->filter()->implode(' · ');

        return ImportDirectoryUser::displayName($person).($details ? " ({$details})" : '').($person->isDisabled() ? ' — disabled in AD' : '');
    }
}
