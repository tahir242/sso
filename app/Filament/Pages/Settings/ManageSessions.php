<?php

namespace App\Filament\Pages\Settings;

use App\Domain\Settings\SessionSettings;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ManageSessions extends BaseSettingsPage
{
    protected static string $settings = SessionSettings::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?string $navigationLabel = 'Sessions & tokens';

    protected static ?string $slug = 'settings/sessions';

    protected static ?string $title = 'Sessions & tokens';

    protected static ?int $navigationSort = 12;

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Session length')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('idle_timeout_minutes')->label('Sign out after inactivity (minutes)')->numeric()->minValue(5)->maxValue(720)->required(),
                    TextInput::make('absolute_timeout_hours')->label('Maximum session length (hours)')->numeric()->minValue(1)->maxValue(24)->required(),
                ]),
            Section::make('Application tokens')
                ->description('Applications use short-lived access tokens and refresh them in the background.')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('access_token_minutes')->label('Access token lifetime (minutes)')->numeric()->minValue(5)->maxValue(60)->required(),
                    TextInput::make('refresh_token_hours')->label('Refresh token lifetime (hours)')->numeric()->minValue(1)->maxValue(24)->required()
                        ->helperText('Keep this at or below the maximum session length.'),
                ]),
        ]);
    }
}
