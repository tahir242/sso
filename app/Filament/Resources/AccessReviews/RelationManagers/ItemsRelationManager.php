<?php

namespace App\Filament\Resources\AccessReviews\RelationManagers;

use App\Domain\Access\Actions\DecideReviewItem;
use App\Domain\Access\Enums\ReviewDecision;
use App\Domain\Access\Models\AccessReviewItem;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'People reviewed';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['application', 'user', 'reviewer', 'decider', 'campaign']))
            ->defaultSort('application_id')
            ->columns([
                TextColumn::make('application.name')->label('Application')->sortable(),
                TextColumn::make('user.name')->label('Person')->searchable()
                    ->description(fn (AccessReviewItem $i) => $i->user->username.($i->user->emp_id ? ' · '.$i->user->emp_id : '')),
                TextColumn::make('roles_snapshot')->visibleFrom('lg')->label('Roles')->badge()->placeholder('default'),
                TextColumn::make('last_used_at')->visibleFrom('xl')->label('Last used')->since()->placeholder('never')->sortable(),
                TextColumn::make('reviewer.name')->label('Reviewer')->placeholder('Administrators'),
                TextColumn::make('decision')->badge()
                    ->description(fn (AccessReviewItem $i) => $i->comment),
                TextColumn::make('decider.name')->visibleFrom('xl')->label('Decided by')->placeholder('—')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('decision')->options(ReviewDecision::class),
                SelectFilter::make('application')->relationship('application', 'name'),
            ])
            ->recordActions([
                Action::make('keep')->label('Keep')->icon(Heroicon::OutlinedCheck)->color('success')
                    ->visible(fn (AccessReviewItem $i) => $i->canBeDecidedBy(auth()->user()))
                    ->action(fn (AccessReviewItem $i, DecideReviewItem $d) => $d->handle($i, auth()->user(), keep: true)),
                Action::make('remove')->label('Remove')->icon(Heroicon::OutlinedXMark)->color('danger')
                    ->visible(fn (AccessReviewItem $i) => $i->canBeDecidedBy(auth()->user()))
                    ->schema([TextInput::make('comment')->label('Reason (optional)')->maxLength(250)])
                    ->action(fn (AccessReviewItem $i, array $data, DecideReviewItem $d) => $d->handle($i, auth()->user(), keep: false, comment: $data['comment'] ?? null)),
            ]);
    }
}
