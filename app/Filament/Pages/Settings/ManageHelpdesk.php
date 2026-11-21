<?php

namespace App\Filament\Pages\Settings;

use App\Domain\Settings\HelpdeskSettings;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ManageHelpdesk extends BaseSettingsPage
{
    protected static string $settings = HelpdeskSettings::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLifebuoy;

    protected static ?string $navigationLabel = 'Helpdesk';

    protected static ?string $slug = 'settings/helpdesk';

    protected static ?string $title = 'Helpdesk service levels';

    protected static ?int $navigationSort = 14;

    public function form(Schema $schema): Schema
    {
        $hours = fn (string $name, string $label) => TextInput::make($name)->label($label)->numeric()->minValue(1)->maxValue(2160)->required()->suffix('hours');

        return $schema->components([
            Section::make('Targets')
                ->description('Calendar hours from when a ticket is raised. Overdue tickets alert the assignee and the helpdesk once.')
                ->columns(5)
                ->columnSpanFull()
                ->schema([
                    $hours('first_response_hours', 'First reply'),
                    $hours('resolve_hours_urgent', 'Resolve: urgent'),
                    $hours('resolve_hours_high', 'Resolve: high'),
                    $hours('resolve_hours_normal', 'Resolve: normal'),
                    $hours('resolve_hours_low', 'Resolve: low'),
                ]),
            Section::make('Closing')
                ->columnSpanFull()
                ->schema([
                    TextInput::make('auto_close_days')->label('Close resolved tickets after')->numeric()->minValue(0)->maxValue(60)->required()->suffix('days')
                        ->helperText('If the requester doesn’t reply. 0 = only close by hand.'),
                ]),
        ]);
    }
}
