<?php

namespace App\Filament\Resources\Occasions;

use App\Domain\Engagement\Enums\OccasionCalendar;
use App\Domain\Engagement\Enums\OccasionTheme;
use App\Domain\Engagement\Models\Occasion;
use App\Domain\Settings\GeneralSettings;
use App\Filament\Resources\Occasions\Pages\CreateOccasion;
use App\Filament\Resources\Occasions\Pages\EditOccasion;
use App\Filament\Resources\Occasions\Pages\ListOccasions;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class OccasionResource extends Resource
{
    protected static ?string $model = Occasion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Login screen';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'title';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasRole('super-admin');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Greeting')
                ->description('Shown on the login screen while the occasion is active.')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('title')->required()->maxLength(120),
                    Select::make('theme')->options(OccasionTheme::class)->default(OccasionTheme::General)->required(),
                    Textarea::make('message')->required()->rows(3)->columnSpanFull(),
                    Textarea::make('message_ur')->label('Message (Urdu, optional)')->rows(2)->columnSpanFull()
                        ->extraInputAttributes(['dir' => 'rtl', 'lang' => 'ur']),
                ]),

            Section::make('When')
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    Select::make('calendar')
                        ->options(OccasionCalendar::class)
                        ->default(OccasionCalendar::Gregorian)
                        ->live()
                        ->required()
                        ->columnSpanFull(),
                    Select::make('month')
                        ->options(fn (Get $get) => self::calendarOf($get('calendar'))->months())
                        ->required(),
                    TextInput::make('day')
                        ->numeric()->minValue(1)
                        ->maxValue(fn (Get $get) => self::calendarOf($get('calendar'))->maxDay())
                        ->required(),
                    TextInput::make('duration_days')
                        ->label('Shown for (days)')
                        ->numeric()->minValue(1)->maxValue(30)->default(1)->required(),
                ]),

            Section::make('Moon-sighting correction')
                ->description('Optional. Set the actual dates for this year if they differ from the calculated Hijri date. Applies only to the year of the start date.')
                ->columns(2)
                ->columnSpanFull()
                ->collapsed(fn (?Occasion $record) => ! $record?->override_starts_on)
                ->visible(fn (Get $get) => self::calendarOf($get('calendar')) === OccasionCalendar::Hijri)
                ->schema([
                    DatePicker::make('override_starts_on')->label('Starts on'),
                    DatePicker::make('override_ends_on')->label('Ends on')->afterOrEqual('override_starts_on'),
                ]),

            Section::make('Display')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('priority')
                        ->numeric()->default(0)->required()
                        ->helperText('When two occasions overlap, the higher priority is shown.'),
                    Toggle::make('active')->default(true)->inline(false),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('priority', 'desc')
            ->columns([
                TextColumn::make('title')->searchable()->weight('medium'),
                TextColumn::make('calendar')->badge(),
                TextColumn::make('date')
                    ->label('Date')
                    ->state(fn (Occasion $record) => $record->day.' '.$record->calendar->months()[$record->month]),
                TextColumn::make('next')
                    ->label('Next on')
                    ->state(fn (Occasion $record) => $record->override_starts_on?->isFuture()
                        ? $record->override_starts_on
                        : $record->nextStartDate(CarbonImmutable::today(), app(GeneralSettings::class)->hijri_offset_days))
                    ->date('D, j M Y'),
                TextColumn::make('duration_days')->label('Days')->numeric(),
                TextColumn::make('theme')->badge()->toggleable(),
                TextColumn::make('priority')->numeric()->sortable()->toggleable(),
                IconColumn::make('active')->boolean(),
            ])
            ->filters([
                SelectFilter::make('calendar')->options(OccasionCalendar::class),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOccasions::route('/'),
            'create' => CreateOccasion::route('/create'),
            'edit' => EditOccasion::route('/{record}/edit'),
        ];
    }

    private static function calendarOf(mixed $value): OccasionCalendar
    {
        return $value instanceof OccasionCalendar ? $value : (OccasionCalendar::tryFrom((string) $value) ?? OccasionCalendar::Gregorian);
    }
}
