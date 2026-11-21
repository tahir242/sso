<?php

namespace App\Filament\Resources\Applications\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

class RolesRelationManager extends RelationManager
{
    protected static string $relationship = 'roles';

    protected static ?string $title = 'Roles';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(120),
            TextInput::make('key')->required()->maxLength(60)->alphaDash()
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->where('application_id', $this->getOwnerRecord()->getKey()))
                ->helperText('Sent to the application in the `roles` claim, e.g. clerk, admin.'),
            TextInput::make('description')->maxLength(255)->columnSpanFull(),
            Toggle::make('is_default')->label('Give to new users by default'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->description('Coarse roles the application maps to its own permissions.')
            ->columns([
                TextColumn::make('name')->weight('medium')->description(fn ($record) => $record->description),
                TextColumn::make('key')->badge()->color('gray')->fontFamily('mono'),
                IconColumn::make('is_default')->label('Default')->boolean(),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->modalDescription('Users lose this role in the application from their next sign-in.'),
            ]);
    }
}
