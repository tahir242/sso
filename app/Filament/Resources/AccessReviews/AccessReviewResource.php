<?php

namespace App\Filament\Resources\AccessReviews;

use App\Domain\Access\Models\AccessReviewCampaign;
use App\Filament\Resources\AccessReviews\Pages\CreateAccessReview;
use App\Filament\Resources\AccessReviews\Pages\ListAccessReviews;
use App\Filament\Resources\AccessReviews\Pages\ViewAccessReview;
use App\Filament\Resources\AccessReviews\RelationManagers\ItemsRelationManager;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Periodic access reviews (re-certification). Owners decide in the portal;
 * administrators start, monitor, close and export reviews here.
 */
class AccessReviewResource extends Resource
{
    protected static ?string $model = AccessReviewCampaign::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Audit';

    protected static ?string $navigationLabel = 'Access reviews';

    protected static ?string $modelLabel = 'access review';

    protected static ?string $slug = 'audit/access-reviews';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasAnyRole(['super-admin', 'auditor']);
    }

    public static function canCreate(): bool
    {
        return (bool) auth()->user()?->hasRole('super-admin');
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false; // Reviews are audit evidence.
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('name')->required()->maxLength(150)
                        ->default(fn () => 'Access review '.now()->format('F Y')),
                    Select::make('application_id')->label('Application')
                        ->relationship('application', 'name', fn ($query) => $query->where('active', true))
                        ->placeholder('All applications')
                        ->helperText('Leave empty to review every application.'),
                    DateTimePicker::make('due_at')->label('Reviewers must finish by')->native(false)->required()
                        ->default(now()->addDays(14)->endOfDay()->startOfSecond())->minDate(now()->addDay()),
                    Toggle::make('auto_revoke')->label('Remove access that is not confirmed by then')->inline(false)
                        ->helperText('Off: unconfirmed access is kept and reported as “not reviewed in time”.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->weight('medium')->searchable()
                    ->description(fn (AccessReviewCampaign $r) => $r->scheduled ? 'Scheduled' : 'Started by '.($r->creator?->name ?? 'system')),
                TextColumn::make('application.name')->label('Scope')->placeholder('All applications'),
                TextColumn::make('due_at')->label('Due')->date('j M Y')->sortable(),
                TextColumn::make('progress')->label('Progress')
                    ->state(fn (AccessReviewCampaign $r) => ($p = $r->progress())['done'].' / '.$p['total'].' ('.$p['percent'].'%)')
                    ->description(fn (AccessReviewCampaign $r) => ($p = $r->progress())['removed'] ? $p['removed'].' removed' : null),
                TextColumn::make('status')->badge()
                    ->color(fn (string $state) => $state === AccessReviewCampaign::OPEN ? 'warning' : 'gray')
                    ->formatStateUsing(fn (string $state) => ucfirst($state)),
            ])
            ->filters([
                SelectFilter::make('status')->options([AccessReviewCampaign::OPEN => 'Open', AccessReviewCampaign::CLOSED => 'Closed']),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(4)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('application.name')->label('Scope')->placeholder('All applications'),
                    TextEntry::make('due_at')->label('Due')->dateTime('j M Y, H:i'),
                    TextEntry::make('auto_revoke')->label('Unconfirmed access')
                        ->formatStateUsing(fn (bool $state) => $state ? 'Removed at close' : 'Kept, reported'),
                    TextEntry::make('status')->badge()->formatStateUsing(fn (string $state) => ucfirst($state)),
                    TextEntry::make('progress')->label('Reviewed')
                        ->state(fn (AccessReviewCampaign $r) => ($p = $r->progress())['done'].' of '.$p['total'].' ('.$p['percent'].'%)'),
                    TextEntry::make('kept')->label('Kept')->state(fn (AccessReviewCampaign $r) => $r->progress()['kept']),
                    TextEntry::make('removed')->label('Removed')->state(fn (AccessReviewCampaign $r) => $r->progress()['removed']),
                    TextEntry::make('closed_at')->label('Closed')->dateTime('j M Y, H:i')->placeholder('—'),
                ]),
        ]);
    }

    public static function getRelations(): array
    {
        return [ItemsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAccessReviews::route('/'),
            'create' => CreateAccessReview::route('/create'),
            'view' => ViewAccessReview::route('/{record}'),
        ];
    }
}
