<?php

namespace App\Filament\Resources\TicketCategories;

use App\Domain\Helpdesk\Models\TicketCategory;
use App\Domain\Helpdesk\Support\Helpdesk;
use App\Filament\Resources\TicketCategories\Pages\ManageTicketCategories;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class TicketCategoryResource extends Resource
{
    protected static ?string $model = TicketCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|UnitEnum|null $navigationGroup = 'Helpdesk';

    protected static ?string $navigationLabel = 'Categories';

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasAnyRole(Helpdesk::AGENT_ROLES);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(100),
            Select::make('application_id')->label('Application')->relationship('application', 'name')
                ->placeholder('General IT (offered for every application)')->searchable()->preload(),
            TextInput::make('description')->maxLength(255)->columnSpanFull(),
            Select::make('default_assignee_id')->label('Assign new tickets to')
                ->options(fn () => Helpdesk::assignableFor(null)->pluck('name', 'id'))
                ->placeholder('Nobody (helpdesk queue)')->searchable()
                ->helperText('The application owner can also be chosen by assigning on the ticket itself.'),
            TextInput::make('sort_order')->numeric()->default(0)->minValue(0),
            Toggle::make('active')->default(true)->inline(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')->searchable()->weight('medium')->description(fn (TicketCategory $record) => $record->description),
                TextColumn::make('application.name')->label('Application')->placeholder('General IT'),
                TextColumn::make('defaultAssignee.name')->label('Assign to')->placeholder('Helpdesk queue'),
                TextColumn::make('tickets_count')->counts('tickets')->label('Tickets'),
                IconColumn::make('active')->boolean(),
            ])
            ->filters([SelectFilter::make('application')->relationship('application', 'name')])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageTicketCategories::route('/')];
    }
}
