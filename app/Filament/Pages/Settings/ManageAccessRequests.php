<?php

namespace App\Filament\Pages\Settings;

use App\Domain\Settings\AccessSettings;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ManageAccessRequests extends BaseSettingsPage
{
    protected static string $settings = AccessSettings::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static ?string $navigationLabel = 'Access requests & reviews';

    protected static ?string $slug = 'settings/access-requests';

    protected static ?string $title = 'Access requests & reviews';

    protected static ?int $navigationSort = 13;

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Approvals')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('approval_sla_hours')->label('Time to decide (hours)')->numeric()->minValue(1)->maxValue(720)->required()
                        ->helperText('After this, the approver is reminded and administrators are alerted.'),
                    TextInput::make('max_request_days')->label('Longest requestable access (days)')->numeric()->minValue(0)->maxValue(3650)->required()
                        ->helperText('0 = no limit. Applies only when an end date is given.'),
                ]),
            Section::make('Scheduled access reviews')
                ->description('Application owners confirm who should keep access. Reviews can also be started by hand under Audit → Access reviews.')
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('review_interval_months')->label('Review every (months)')->numeric()->minValue(0)->maxValue(24)->required()
                        ->helperText('0 = never automatically. 3 = quarterly.'),
                    TextInput::make('review_duration_days')->label('Days to complete')->numeric()->minValue(3)->maxValue(90)->required(),
                    Toggle::make('review_auto_revoke')->label('Remove unconfirmed access when a review closes')->inline(false),
                ]),
        ]);
    }
}
