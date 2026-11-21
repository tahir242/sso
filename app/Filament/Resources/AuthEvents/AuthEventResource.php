<?php

namespace App\Filament\Resources\AuthEvents;

use App\Domain\Audit\Enums\AuthEventType;
use App\Domain\Audit\Models\AuthEvent;
use App\Filament\Resources\AuthEvents\Pages\ListAuthEvents;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Read-only security log: sign-ins, failures, lockouts, MFA and device events.
 */
class AuthEventResource extends Resource
{
    protected static ?string $model = AuthEvent::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFingerPrint;

    protected static string|UnitEnum|null $navigationGroup = 'Audit';

    protected static ?string $navigationLabel = 'Sign-in activity';

    protected static ?string $modelLabel = 'sign-in event';

    protected static ?string $slug = 'audit/sign-in-activity';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasAnyRole(['super-admin', 'auditor', 'helpdesk']);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('user'))
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime('j M Y, H:i:s')->sortable(),
                TextColumn::make('event')->badge(),
                TextColumn::make('user.name')->label('User')->description(fn (AuthEvent $record) => $record->identifier)->searchable(['identifier']),
                TextColumn::make('ip_address')->label('IP')->visibleFrom('md')->searchable()->copyable(),
                TextColumn::make('user_agent')->label('Browser')->limit(40)->tooltip(fn (AuthEvent $record) => $record->user_agent)->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('meta')->label('Details')->visibleFrom('xl')
                    ->state(fn (AuthEvent $record) => collect($record->meta ?? [])->map(fn ($v, $k) => $k.': '.(is_bool($v) ? ($v ? 'yes' : 'no') : (is_scalar($v) ? $v : json_encode($v))))->implode(', '))
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('event')->options(AuthEventType::class)->multiple(),
                Filter::make('date')
                    ->schema([DatePicker::make('from'), DatePicker::make('until')])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date))),
            ])
            ->poll('30s');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAuthEvents::route('/'),
        ];
    }
}
