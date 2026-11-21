<?php

namespace App\Filament\Resources\Applications\RelationManagers;

use App\Domain\Federation\Actions\GrantApplicationAccess;
use App\Domain\Federation\Models\Application;
use App\Domain\Federation\Models\ApplicationAssignment;
use App\Domain\Identity\Models\User;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Who may open this application, and with which roles.
 */
class AssignmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'assignments';

    protected static ?string $title = 'Users';

    private function application(): Application
    {
        /** @var Application */
        return $this->getOwnerRecord();
    }

    private function roleSelect(): Select
    {
        return Select::make('roles')->label('Roles')->multiple()
            ->options(fn () => $this->application()->roles()->orderBy('name')->pluck('name', 'id'))
            ->helperText('Leave empty to give the default roles.');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            $this->roleSelect()->relationship('roles', 'name', fn (Builder $query) => $query->where('application_id', $this->application()->getKey())),
            DateTimePicker::make('expires_at')->label('Access ends')->native(false)->minDate(now())
                ->helperText('Optional, e.g. for temporary staff.'),
            Toggle::make('active')->inline(false),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user', 'roles', 'grantedBy']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('user.name')->label('User')->weight('medium')->searchable()
                    ->description(fn (ApplicationAssignment $record) => $record->user->username.($record->user->emp_id ? ' · '.$record->user->emp_id : '')),
                TextColumn::make('roles.name')->label('Roles')->badge(),
                TextColumn::make('expires_at')->label('Access ends')->date('j M Y')->placeholder('—')->sortable(),
                IconColumn::make('active')->boolean(),
                TextColumn::make('grantedBy.name')->visibleFrom('xl')->label('Granted by')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->visibleFrom('xl')->label('Since')->date('j M Y')->sortable()->toggleable(),
            ])
            ->filters([TernaryFilter::make('active')])
            ->headerActions([
                Action::make('grant')
                    ->label('Give access')
                    ->icon(Heroicon::OutlinedUserPlus)
                    ->schema([
                        Select::make('user_id')->label('User')->required()->searchable()
                            ->getSearchResultsUsing(fn (string $search) => User::query()
                                ->where(fn (Builder $q) => $q->where('name', 'like', "%{$search}%")->orWhere('username', 'like', "%{$search}%")->orWhere('emp_id', 'like', "%{$search}%"))
                                ->limit(25)->get()->mapWithKeys(fn (User $u) => [$u->id => "{$u->name} ({$u->username})"]))
                            ->getOptionLabelUsing(fn ($value) => User::find($value)?->name),
                        $this->roleSelect(),
                        DateTimePicker::make('expires_at')->label('Access ends')->native(false)->minDate(now()),
                    ])
                    ->action(function (array $data, GrantApplicationAccess $grant): void {
                        $grant->handle(
                            $this->application(),
                            User::findOrFail($data['user_id']),
                            array_map('intval', $data['roles'] ?? []),
                            $data['expires_at'] ? now()->parse($data['expires_at']) : null,
                            auth()->user(),
                        );

                        Notification::make()->title('Access granted')->success()->send();
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->label('Remove access')
                    ->modalDescription('The user can no longer open this application. Their current tokens for it are revoked.'),
            ]);
    }
}
