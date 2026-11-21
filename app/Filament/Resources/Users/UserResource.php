<?php

namespace App\Filament\Resources\Users;

use App\Domain\Identity\Actions\DisableTwoFactor;
use App\Domain\Identity\Directory\DirectoryLookup;
use App\Domain\Identity\Enums\AuthSource;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\Department;
use App\Domain\Identity\Models\Location;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Support\EmployeeInitials;
use App\Domain\Settings\SecuritySettings;
use App\Filament\Forms\Components\ApplicationPicker;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use UnitEnum;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Identity';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    /** Super-admins manage users; helpdesk can help users (MFA reset, unlock). */
    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasAnyRole(['super-admin', 'helpdesk']);
    }

    public static function canCreate(): bool
    {
        return (bool) auth()->user()?->hasRole('super-admin');
    }

    public static function canEdit($record): bool
    {
        return (bool) auth()->user()?->hasRole('super-admin');
    }

    public static function canDelete($record): bool
    {
        return false; // Accounts are disabled, never deleted (audit trail).
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['username', 'emp_id', 'name', 'email'];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identity')
                ->description('Choose how the person signs in first. For an Active Directory account, type the Windows username and the details are filled in from AD.')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    ToggleButtons::make('auth_source')->label('Password source')->options(AuthSource::class)
                        ->default(AuthSource::Local)->required()->inline()->live()->columnSpanFull()
                        ->afterStateUpdated(fn (Get $get, Set $set) => self::fillFromDirectory($get, $set))
                        ->helperText(fn (Get $get, string $operation, ?User $record) => self::isLocal($get('auth_source'))
                            ? 'local account with its own password (consultants, vendors, people without a Windows account).'
                            : ($operation === 'edit' && $record?->auth_source === AuthSource::Local
                                ? 'Saving switches this person to their Windows password; their details are then updated from Active Directory and the local password is removed.'
                                : 'Signs in with the Windows password; name, email, department and job title come from Active Directory.')),
                    TextInput::make('username')->required()->maxLength(100)->unique(ignoreRecord: true)
                        ->validationMessages(['unique' => 'This username already belongs to another SSO account.'])
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Get $get, Set $set) => self::fillFromDirectory($get, $set))
                        ->helperText(fn (Get $get, ?User $record) => self::usernameHint($get('username'), $get('auth_source'), $record))
                        ->rules([fn (Get $get, ?User $record) => function (string $attribute, $value, \Closure $fail) use ($get, $record): void {
                            if ($message = self::usernameProblem((string) $value, $get('auth_source'), $record)) {
                                $fail($message);
                            }
                        }]),
                    TextInput::make('emp_id')->label('Employee ID')->maxLength(30)->unique(ignoreRecord: true)
                        ->validationMessages(['unique' => 'This Employee ID already belongs to another SSO account.']),
                    TextInput::make('emp_initial')->label('Employee initials')->maxLength(EmployeeInitials::MAX)
                        ->placeholder(fn (Get $get, ?User $record) => filled($get('name')) ? EmployeeInitials::generate((string) $get('name'), $record?->getKey()) : 'Generated from the name')
                        ->helperText('Leave empty to generate them from the name (unique, e.g. Jane Ann Doe → JAD).')
                        ->dehydrateStateUsing(fn (?string $state) => filled($state) ? mb_strtoupper(trim($state)) : null)
                        ->rules([fn (?User $record) => function (string $attribute, $value, \Closure $fail) use ($record): void {
                            $value = mb_strtoupper(trim((string) $value));
                            // Only check initials that change (some existing accounts already share initials).
                            if ($value !== '' && $value !== mb_strtoupper((string) $record?->emp_initial) && EmployeeInitials::taken($value, $record?->getKey())) {
                                $fail('These initials already belong to another person. Leave the field empty to generate unique initials.');
                            }
                        }]),
                    TextInput::make('name')->required()->maxLength(150)->live(onBlur: true),
                    TextInput::make('email')->label('Email address')->email()->required()->maxLength(191)
                        ->unique(ignoreRecord: true)
                        ->validationMessages(['unique' => 'This email address already belongs to another SSO account.'])
                        ->rule('email:rfc')
                        ->dehydrateStateUsing(fn (?string $state) => $state ? mb_strtolower(trim($state)) : $state)
                        ->helperText('Required: used for password recovery and security notifications.'),
                    TextInput::make('mobile')->tel()->maxLength(30),
                    TextInput::make('designation')->maxLength(150),
                    Select::make('department_id')->relationship('department', 'name')->searchable()->preload(),
                    Select::make('location_id')->label('Location')
                        ->options(fn () => Location::paths())
                        ->searchable()
                        ->helperText('Where the user sits (room or workstation). Manage places in Identity → Locations.'),
                ]),
            Section::make('Applications')
                ->description('Applications this user can open. Applications open to everyone are always available and need no selection.')
                ->icon(Heroicon::OutlinedSquares2x2)
                ->columnSpanFull()
                ->collapsible()
                ->schema([
                    ApplicationPicker::make('application_ids')->hiddenLabel()->dehydrated(false),
                ]),
            Section::make('Sign-in')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    Select::make('status')->options(UserStatus::class)->default(UserStatus::Active)->required(),
                    TextInput::make('password')
                        ->label(fn (string $operation) => $operation === 'create' ? 'Password' : 'Set a new password')
                        ->password()->revealable()
                        ->autocomplete('new-password')
                        ->visible(fn (Get $get) => self::isLocal($get('auth_source')))
                        ->required(fn (string $operation, Get $get) => $operation === 'create' && self::isLocal($get('auth_source')))
                        ->rule(fn () => Password::min(app(SecuritySettings::class)->password_min_length)->letters()->numbers())
                        ->dehydrated(fn (?string $state) => filled($state))
                        ->helperText(fn (string $operation) => $operation === 'edit' ? 'Leave empty to keep the current password.' : null),
                    Select::make('roles')->label('Admin console roles')->relationship('roles', 'name')->multiple()->preload()
                        ->helperText('Leave empty for normal users. These roles are for this SSO console only, not for client applications.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            // Fits the screen: identity details are stacked under the name,
            // lower-priority columns appear only on wider screens, and row
            // actions sit in one menu. Phones get stacked cards.
            ->columns([
                TextColumn::make('name')->label('User')->sortable()->weight('medium')->wrap()
                    ->searchable(['name', 'username', 'emp_id', 'email'])
                    ->description(fn (User $record) => collect([$record->username, $record->emp_id ? 'Emp. '.$record->emp_id : null, $record->designation])->filter()->implode(' · ')),
                TextColumn::make('email')->toggleable()->visibleFrom('xl')->limit(30)->tooltip(fn (?string $state) => $state)
                    ->placeholder('Missing')->icon(fn (?string $state) => $state ? null : Heroicon::OutlinedExclamationTriangle)
                    ->iconColor('warning'),
                TextColumn::make('status')->badge()
                    ->description(fn (User $record) => $record->auth_source === AuthSource::ActiveDirectory ? 'Windows (AD)' : 'Local')
                    ->color(fn (UserStatus $state) => match ($state) {
                        UserStatus::Active => 'success',
                        UserStatus::Locked => 'warning',
                        UserStatus::Disabled => 'danger',
                    }),
                IconColumn::make('two_factor_confirmed_at')->label('2-step')->boolean()->alignCenter()
                    ->state(fn (User $record) => $record->hasTwoFactorEnabled()),
                TextColumn::make('roles.name')->label('Console roles')->badge()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('last_login_at')->label('Last sign-in')->since()->sortable()->visibleFrom('lg')
                    ->dateTimeTooltip(),
            ])
            ->stackedOnMobile()
            ->filters([
                SelectFilter::make('status')->options(UserStatus::class),
                SelectFilter::make('auth_source')->label('Source')->options(AuthSource::class),
                TernaryFilter::make('email')->label('Email address')
                    ->trueLabel('Has an email address')->falseLabel('Missing email address')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('email')->where('email', '!=', ''),
                        false: fn (Builder $query) => $query->where(fn (Builder $q) => $q->whereNull('email')->orWhere('email', '')),
                    ),
                SelectFilter::make('location_id')->label('Location')->options(fn () => Location::paths())->searchable(),
                TernaryFilter::make('two_factor')->label('Two-step verification')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('two_factor_confirmed_at'),
                        false: fn (Builder $query) => $query->whereNull('two_factor_confirmed_at'),
                    ),
            ])
            ->recordActions([
                EditAction::make()->iconButton()->tooltip('Edit'),
                ActionGroup::make([
                    Action::make('resetMfa')
                        ->label('Reset 2-step')
                        ->icon(Heroicon::OutlinedDevicePhoneMobile)
                        ->color('warning')
                        ->visible(fn (User $record) => $record->hasTwoFactorEnabled())
                        ->requiresConfirmation()
                        ->modalDescription('Removes the user’s authenticator, recovery codes and remembered computers. Verify the person’s identity first (e.g. in person or by a call-back).')
                        ->action(function (User $record, DisableTwoFactor $disable): void {
                            $disable->handle($record, resetBy: auth()->user());
                            Notification::make()->title('Two-step verification reset')->success()->send();
                        }),
                    Action::make('unlock')
                        ->label('Clear lockout')
                        ->icon(Heroicon::OutlinedLockOpen)
                        ->color('gray')
                        ->action(function (User $record): void {
                            foreach (array_filter([$record->username, $record->emp_id]) as $identifier) {
                                RateLimiter::clear('login:account:'.Str::lower($identifier));
                            }
                            Notification::make()->title('Sign-in lockout cleared')->success()->send();
                        }),
                ])->tooltip('More actions'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }

    private static function isLocal(mixed $value): bool
    {
        return AuthSource::of($value) === AuthSource::Local;
    }

    /**
     * Active Directory account chosen and username typed: fill the form
     * with the person's AD details (shown before saving; saving takes the
     * authoritative values from AD again).
     */
    private static function fillFromDirectory(Get $get, Set $set): void
    {
        if (self::isLocal($get('auth_source'))) {
            return;
        }

        $ad = app(DirectoryLookup::class)->username($get('username'));

        if ($ad['status'] !== 'found') {
            return;
        }

        $set('name', $ad['name']);
        foreach (['email' => $ad['email'], 'emp_id' => $ad['emp_id'], 'designation' => $ad['title'], 'mobile' => $ad['mobile']] as $field => $value) {
            if (filled($value)) {
                $set($field, $value);
            }
        }

        if (filled($ad['department']) && ($department = Department::query()->where('name', $ad['department'])->value('id'))) {
            $set('department_id', $department);
        }
    }

    private static function usernameHint(?string $username, mixed $source, ?User $record): string|HtmlString
    {
        if (blank($username)) {
            return 'For Active Directory accounts: the Windows logon name, e.g. ahmed.khan.';
        }

        $ad = app(DirectoryLookup::class)->username($username);

        return match ($ad['status']) {
            'off' => self::isLocal($source) ? 'Active Directory is not switched on, so it is not checked.' : 'Turn on Active Directory sign-in (Settings → Active Directory) to use AD accounts.',
            'error' => 'Could not check Active Directory: '.$ad['error'],
            'missing' => self::isLocal($source) ? 'Not used in Active Directory.' : 'No Active Directory account has this username.',
            'found' => new HtmlString(e('Active Directory: '.collect([$ad['name'], $ad['email'], $ad['department']])->filter()->implode(' · '))
                .($ad['disabled'] ? ' — <strong>disabled in AD</strong>' : '')
                .(($ad['linked_to'] && $ad['linked_to']['id'] !== $record?->getKey()) ? ' — already linked to SSO account '.e($ad['linked_to']['username']) : '')),
        };
    }

    /**
     * Server-side rules that keep local and Active Directory accounts apart.
     */
    private static function usernameProblem(string $username, mixed $source, ?User $record): ?string
    {
        $ad = app(DirectoryLookup::class)->username($username);
        $local = self::isLocal($source);

        if (! $local && $ad['status'] === 'off') {
            return 'Active Directory sign-in is switched off (Settings → Active Directory), so this cannot be an AD account yet.';
        }

        if (! $local && $ad['status'] === 'error') {
            return 'Active Directory could not be checked: '.$ad['error'];
        }

        if (! $local && $ad['status'] === 'missing') {
            return 'No Active Directory account has this username. Check the Windows logon name, or use a local account.';
        }

        if (! $local && $ad['status'] === 'found' && $ad['linked_to'] && $ad['linked_to']['id'] !== $record?->getKey()) {
            return "This Active Directory account is already linked to SSO account {$ad['linked_to']['username']}.";
        }

        if ($local && $ad['status'] === 'found') {
            return "“{$username}” is the Windows account of {$ad['name']}. Choose Active Directory as the password source, or use a different username for a local account.";
        }

        return null;
    }
}
