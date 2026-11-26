<?php

namespace App\Filament\Pages\Settings;

use App\Domain\Settings\MfaSettings;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

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
            Section::make('Options')
                ->description('Each user decides whether to turn two-step verification on (Account → Security). Users who have it on are asked for a code at sign-in, unless the computer is remembered.')
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
