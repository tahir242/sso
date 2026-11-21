<?php

namespace App\Filament\Pages\Settings;

use App\Domain\Settings\MfaSettings;
use BackedEnum;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\IpUtils;

class ManageTwoStepVerification extends BaseSettingsPage
{
    protected static string $settings = MfaSettings::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDevicePhoneMobile;

    protected static ?string $navigationLabel = 'Two-step verification';

    protected static ?string $slug = 'settings/two-step-verification';

    protected static ?string $title = 'Two-step verification';

    protected static ?int $navigationSort = 11;

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('When is a code required?')
                ->description('Users who have turned two-step verification on are always asked for a code (unless the computer is remembered).')
                ->columnSpanFull()
                ->schema([
                    Toggle::make('required_for_admins')->label('Always for administrators (super-admin, app owners, helpdesk, auditors)'),
                    Toggle::make('required_off_network')->label('For everyone signing in from outside the hospital network'),
                    TagsInput::make('trusted_networks')
                        ->label('Hospital network ranges (CIDR)')
                        ->placeholder('10.0.0.0/8')
                        ->nestedRecursiveRules([
                            fn () => function (string $attribute, mixed $value, \Closure $fail): void {
                                [$ip] = explode('/', (string) $value) + [null];
                                if (! filter_var($ip, FILTER_VALIDATE_IP) || ! IpUtils::checkIp($ip, (string) $value)) {
                                    $fail("“{$value}” is not a valid IP range.");
                                }
                            },
                        ]),
                ]),
            Section::make('Options')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('trusted_device_days')->label('Remember a computer for (days)')->numeric()->minValue(0)->maxValue(90)->required()
                        ->helperText('0 turns “Don’t ask again on this computer” off.'),
                    TextInput::make('issuer')->label('Name shown in authenticator apps')->required()->maxLength(40),
                ]),
        ]);
    }
}
