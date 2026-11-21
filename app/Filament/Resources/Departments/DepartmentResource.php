<?php

namespace App\Filament\Resources\Departments;

use App\Domain\Identity\Models\Department;
use App\Domain\Identity\Models\User;
use App\Filament\Resources\Departments\Pages\ManageDepartments;
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
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Departments and their heads (first approvers of access requests).
 */
class DepartmentResource extends Resource
{
    protected static ?string $model = Department::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static string|UnitEnum|null $navigationGroup = 'Identity';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasAnyRole(['super-admin', 'helpdesk']);
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(150),
            TextInput::make('code')->required()->maxLength(30)->alphaDash()->unique(ignoreRecord: true),
            Select::make('hod_user_id')->label('Head of department')
                ->relationship('hod', 'name')
                ->searchable(['name', 'username', 'emp_id'])
                ->getOptionLabelFromRecordUsing(fn (User $record) => "{$record->name} ({$record->username})")
                ->helperText('Approves access requests from this department’s staff.'),
            Toggle::make('active')->default(true)->inline(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->searchable()->sortable()->weight('medium'),
                TextColumn::make('code')->badge()->color('gray'),
                TextColumn::make('hod.name')->label('Head of department')->placeholder('Not set'),
                TextColumn::make('users_count')->counts('users')->label('Staff')->sortable(),
                IconColumn::make('active')->boolean(),
            ])
            ->filters([TernaryFilter::make('active')])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageDepartments::route('/')];
    }
}
