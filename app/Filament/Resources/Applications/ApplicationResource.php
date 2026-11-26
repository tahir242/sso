<?php

namespace App\Filament\Resources\Applications;

use App\Domain\Federation\ApiScopes;
use App\Domain\Federation\Enums\AccessMode;
use App\Domain\Federation\Models\Application;
use App\Domain\Federation\Oidc\Issuer;
use App\Domain\Identity\Models\User;
use App\Filament\Resources\Applications\Pages\ConnectApplication;
use App\Filament\Resources\Applications\Pages\CreateApplication;
use App\Filament\Resources\Applications\Pages\EditApplication;
use App\Filament\Resources\Applications\Pages\ListApplications;
use App\Filament\Resources\Applications\RelationManagers\AssignmentsRelationManager;
use App\Filament\Resources\Applications\RelationManagers\RolesRelationManager;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use UnitEnum;

class ApplicationResource extends Resource
{
    protected static ?string $model = Application::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string|UnitEnum|null $navigationGroup = 'Identity';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasAnyRole(['super-admin', 'app-owner']);
    }

    public static function canCreate(): bool
    {
        return self::isSuperAdmin();
    }

    public static function canDelete($record): bool
    {
        return false; // Deactivate instead: keeps the audit trail and stops sign-ins.
    }

    /** App owners only see the applications they own. */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->when(! self::isSuperAdmin(), fn (Builder $query) => $query->where('owner_user_id', auth()->id()));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Application')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('name')->required()->maxLength(120)->live(onBlur: true)
                        ->afterStateUpdated(fn (string $operation, ?string $state, Set $set) => $operation === 'create' ? $set('slug', Str::slug((string) $state)) : null),
                    TextInput::make('slug')->required()->maxLength(60)->alphaDash()->unique(ignoreRecord: true)
                        ->disabled(fn (string $operation) => $operation === 'edit')->dehydrated(fn (string $operation) => $operation === 'create'),
                    Textarea::make('description')->rows(2)->maxLength(500)->columnSpanFull(),
                    TextInput::make('base_url')->label('Home page URL')->url()->required()->maxLength(500)
                        ->helperText('Where “My applications” sends users. The application then signs them in through SSO.'),
                    Select::make('owner_user_id')->label('Application owner')->relationship('owner', 'name')
                        ->searchable(['name', 'username', 'emp_id'])
                        ->getOptionLabelFromRecordUsing(fn (User $record) => "{$record->name} ({$record->username})")
                        ->disabled(! self::isSuperAdmin()),
                    Toggle::make('active')->default(true)->inline(false)
                        ->helperText('Inactive applications cannot sign anyone in.')
                        ->disabled(! self::isSuperAdmin()),
                ]),

            Section::make('Who can open it')
                ->description('Controls sign-in to the application.')
                ->icon(Heroicon::OutlinedKey)
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    Radio::make('access_mode')->hiddenLabel()->options(AccessMode::class)
                        ->default(AccessMode::Assigned)->required()->live()
                        ->disabled(! self::isSuperAdmin())
                        ->columnSpanFull(),
                    Toggle::make('approval_policy.skip_hod')->label('Skip the head of department step')
                        ->helperText('Access requests: for applications every department uses the same way. The owner always approves.')
                        ->visible(fn (Get $get) => self::accessModeOf($get('access_mode')) !== AccessMode::Everyone),
                    Toggle::make('approval_policy.require_it_security')->label('Also require IT security approval')
                        ->helperText('Access requests: for sensitive applications (e.g. finance, patient data exports).')
                        ->visible(fn (Get $get) => self::accessModeOf($get('access_mode')) !== AccessMode::Everyone),
                ]),

            Section::make('Single sign-on (OpenID Connect)')
                ->description('Values the application’s developers configure on their side.')
                ->columns(1)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('issuer')->label('Issuer / discovery')
                        ->state(fn () => Issuer::url().'/.well-known/openid-configuration')
                        ->copyable(),
                    TextEntry::make('oauth_client_id')->label('Client ID')->copyable()
                        ->visible(fn (?Application $record) => $record !== null),
                    TagsInput::make('redirect_uris')->label('Redirect URIs')->required()
                        ->placeholder('https://hr.example.com/sso/callback')
                        ->helperText('Exact URLs the SSO may send sign-in results to. Press Enter after each.')
                        ->nestedRecursiveRules(['url', 'max:500'])
                        ->disabled(! self::isSuperAdmin())
                        ->dehydrated(),
                    TagsInput::make('post_logout_redirect_uris')->label('Post-logout redirect URIs')
                        ->placeholder('https://hr.example.com/')
                        ->nestedRecursiveRules(['url', 'max:500'])
                        ->disabled(! self::isSuperAdmin()),
                    TextInput::make('backchannel_logout_uri')->label('Back-channel logout URI')->url()->maxLength(500)
                        ->placeholder('https://hr.example.com/sso/backchannel-logout')
                        ->helperText('Called server-to-server when a user signs out of SSO, so the application can end its own session.')
                        ->disabled(! self::isSuperAdmin()),
                    Toggle::make('require_pkce')->label('Require PKCE')->default(true)
                        ->helperText('Keep on. Turn off only for a server-side application that keeps its client secret and whose software cannot send PKCE (for example Moodle or Koha). The secret then protects the sign-in code instead.')
                        ->disabled(! self::isSuperAdmin()),
                    CheckboxList::make('api_scopes')->label('Server-to-server API')
                        ->options(ApiScopes::all())
                        ->default(ApiScopes::DEFAULTS)
                        ->helperText(fn () => new HtmlString('What this application may do with its client ID and secret (client credentials). Always limited to its own users. <a href="'.url('/docs/api').'" target="_blank" class="underline">API documentation</a>'))
                        ->disabled(! self::isSuperAdmin()),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->searchable()->sortable()->weight('medium')->wrap()
                    ->description(fn (Application $record) => Str::limit((string) parse_url($record->base_url, PHP_URL_HOST).parse_url($record->base_url, PHP_URL_PATH), 40)),
                TextColumn::make('access_mode')->label('Who can open it')->badge()
                    ->description(fn (Application $record) => $record->assignments_count.' '.Str::plural('user', $record->assignments_count))
                    ->color(fn (AccessMode $state) => $state === AccessMode::Everyone ? 'info' : 'gray'),
                TextColumn::make('assignments_count')->counts('assignments')->label('Users')->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('owner.name')->label('Owner')->toggleable()->visibleFrom('xl'),
                TextColumn::make('roles_count')->counts('roles')->label('Roles')->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('active')->boolean()->alignCenter(),
            ])
            ->stackedOnMobile()
            ->filters([
                TernaryFilter::make('active'),
            ])
            ->recordActions([
                EditAction::make()->iconButton()->tooltip('Edit'),
                ActionGroup::make([
                    Action::make('connect')->label('Connect (developer values)')->icon(Heroicon::OutlinedLink)
                        ->url(fn (Application $record) => self::getUrl('connect', ['record' => $record])),
                ])->tooltip('More actions'),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            AssignmentsRelationManager::class,
            RolesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListApplications::route('/'),
            'create' => CreateApplication::route('/create'),
            'edit' => EditApplication::route('/{record}/edit'),
            'connect' => ConnectApplication::route('/{record}/connect'),
        ];
    }

    private static function accessModeOf(mixed $state): ?AccessMode
    {
        return $state instanceof AccessMode ? $state : AccessMode::tryFrom((string) $state);
    }

    public static function isSuperAdmin(): bool
    {
        return (bool) auth()->user()?->hasRole('super-admin');
    }
}
