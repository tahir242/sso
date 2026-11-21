<?php

namespace App\Filament\Pages\Settings;

use App\Domain\Identity\Directory\ActiveDirectory;
use App\Domain\Settings\LdapSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ManageActiveDirectory extends BaseSettingsPage
{
    protected static string $settings = LdapSettings::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedServerStack;

    protected static ?string $navigationLabel = 'Active Directory';

    protected static ?string $slug = 'settings/active-directory';

    protected static ?string $title = 'Active Directory';

    protected static ?int $navigationSort = 20;

    protected array $secretFields = ['bind_password'];

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Connection')
                ->description('Staff sign in with their Windows password. The SSO verifies it against Active Directory over LDAPS.')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    Toggle::make('enabled')->label('Allow Active Directory sign-in')->columnSpanFull(),
                    TagsInput::make('hosts')
                        ->label('Domain controllers')
                        ->placeholder('dc01.corp.local')
                        ->helperText('Host names or IPs, tried in order. Press Enter after each.')
                        ->columnSpanFull(),
                    Radio::make('encryption')
                        ->options(['ssl' => 'LDAPS (port 636) — recommended', 'tls' => 'StartTLS (port 389)', 'none' => 'None — development only'])
                        ->live()
                        ->afterStateUpdated(fn (?string $state, Set $set) => $set('port', $state === 'ssl' ? 636 : 389))
                        ->required(),
                    TextInput::make('port')->numeric()->minValue(1)->maxValue(65535)->required(),
                    TextInput::make('base_dn')->label('Base DN')->placeholder('DC=corp,DC=local')->required(fn (Get $get) => $get('enabled'))->columnSpanFull(),
                    TextInput::make('timeout')->label('Timeout (seconds)')->numeric()->minValue(1)->maxValue(30)->required(),
                    Toggle::make('verify_certificate')
                        ->label('Verify the domain controller certificate')
                        ->helperText('Keep on. Install the internal CA certificate on the web server instead of turning this off.')
                        ->inline(false),
                ]),

            Section::make('Service account')
                ->description('A read-only domain account used to look users up. It never needs admin rights, but it is required: Active Directory does not allow anonymous searches.')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('bind_username')->label('Username')->placeholder('svc-sso@example.com')
                        ->helperText('As user@domain (UPN) or DOMAIN\\user.')
                        ->required(fn (Get $get) => (bool) $get('enabled')),
                    TextInput::make('bind_password')->label('Password')->password()->revealable()
                        ->placeholder(fn () => filled(app(LdapSettings::class)->bind_password) ? 'Leave empty to keep the current password' : 'Required')
                        ->required(fn (Get $get) => (bool) $get('enabled') && blank(app(LdapSettings::class)->bind_password))
                        ->autocomplete('new-password'),
                ]),

            Section::make('Users')
                ->columns(1)
                ->columnSpanFull()
                ->schema([
                    Toggle::make('auto_create_users')
                        ->label('Create an SSO account automatically on first sign-in')
                        ->helperText('New accounts have no applications until access is granted.'),
                    TextInput::make('user_filter')
                        ->label('Only allow users matching this LDAP filter (optional)')
                        ->placeholder('(memberOf=CN=SSO Users,OU=Groups,DC=corp,DC=local)'),
                ]),
        ]);
    }

    protected function afterSave(): void
    {
        app(ActiveDirectory::class)->reset();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('test')
                ->label('Test saved connection')
                ->icon(Heroicon::OutlinedSignal)
                ->color('gray')
                ->action(function (ActiveDirectory $directory): void {
                    $result = $directory->test();

                    Notification::make()
                        ->title($result['ok'] ? 'Connection works' : 'Connection failed')
                        ->body($result['message'])
                        ->{$result['ok'] ? 'success' : 'danger'}()
                        ->persistent(! $result['ok'])
                        ->send();
                }),
        ];
    }
}
