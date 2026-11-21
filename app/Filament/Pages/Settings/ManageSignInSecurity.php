<?php

namespace App\Filament\Pages\Settings;

use App\Domain\Settings\SecuritySettings;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ManageSignInSecurity extends BaseSettingsPage
{
    protected static string $settings = SecuritySettings::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLockClosed;

    protected static ?string $navigationLabel = 'Passwords & lockout';

    protected static ?string $slug = 'settings/passwords';

    protected static ?string $title = 'Passwords & lockout';

    protected static ?int $navigationSort = 10;

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Local account passwords')
                ->columnSpanFull()
                ->schema([
                    TextInput::make('password_min_length')->label('Minimum length')->numeric()->minValue(8)->maxValue(64)->required()
                        ->helperText('Applies to local (non-Windows) accounts. 12 or more is recommended.'),
                ]),
            Section::make('Lockout')
                ->description('Slows down password guessing.')
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('lockout_max_attempts')->label('Failed attempts per account')->numeric()->minValue(3)->maxValue(20)->required(),
                    TextInput::make('ip_max_attempts')->label('Failed attempts per IP address')->numeric()->minValue(5)->maxValue(500)->required(),
                    TextInput::make('lockout_decay_minutes')->label('Lockout duration (minutes)')->numeric()->minValue(1)->maxValue(1440)->required(),
                ]),
        ]);
    }
}
