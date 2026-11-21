<?php

namespace App\Filament\Resources\AccessRequests;

use App\Domain\Access\Actions\DecideApprovalStep;
use App\Domain\Access\Enums\AccessRequestStatus;
use App\Domain\Access\Models\AccessRequest;
use App\Filament\Resources\AccessRequests\Pages\ListAccessRequests;
use App\Filament\Resources\AccessRequests\Pages\ViewAccessRequest;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Oversight of access requests. App owners see their applications' requests;
 * super-admins see all and can step in on a stuck approval.
 */
class AccessRequestResource extends Resource
{
    protected static ?string $model = AccessRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static string|UnitEnum|null $navigationGroup = 'Requests';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Access requests';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasAnyRole(['super-admin', 'app-owner', 'helpdesk', 'auditor']);
    }

    public static function canCreate(): bool
    {
        return false; // People request access in the portal.
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->pending()->count();

        return $count ? (string) $count : null;
    }

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        return parent::getEloquentQuery()
            ->when(! $user?->hasAnyRole(['super-admin', 'helpdesk', 'auditor']), fn (Builder $query) => $query
                ->whereHas('application', fn (Builder $q) => $q->where('owner_user_id', $user?->getKey())));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['application', 'user', 'steps']))
            ->columns([
                TextColumn::make('created_at')->label('Requested')->dateTime('j M Y, H:i')->sortable(),
                TextColumn::make('user.name')->label('For')->searchable()->description(fn (AccessRequest $r) => $r->user->username),
                TextColumn::make('application.name')->label('Application')->searchable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('current')->label('Waiting on')
                    ->state(fn (AccessRequest $r) => ($step = $r->currentStep()) ? $step->type->getLabel().($step->approver ? ' — '.$step->approver->name : '') : null)
                    ->placeholder('—')
                    ->description(fn (AccessRequest $r) => $r->currentStep()?->isOverdue() ? 'Overdue' : null),
            ])
            ->filters([
                SelectFilter::make('status')->options(AccessRequestStatus::class)->default(AccessRequestStatus::Pending->value),
                SelectFilter::make('application')->relationship('application', 'name'),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Request')
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('user.name')->label('Access for'),
                    TextEntry::make('application.name')->label('Application'),
                    TextEntry::make('status')->badge(),
                    TextEntry::make('requester.name')->label('Requested by'),
                    TextEntry::make('created_at')->label('Requested')->dateTime('j M Y, H:i'),
                    TextEntry::make('access_until')->label('Until')->date('j M Y')->placeholder('Ongoing'),
                    TextEntry::make('roles')->label('Roles')->state(fn (AccessRequest $r) => $r->requestedRoles()->pluck('name')->join(', ') ?: 'Default roles'),
                    TextEntry::make('justification')->columnSpan(2),
                ]),
            Section::make('Approvals')
                ->columnSpanFull()
                ->schema([
                    RepeatableEntry::make('steps')->hiddenLabel()->columns(5)->schema([
                        TextEntry::make('type')->label('Step'),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('approver.name')->label('Approver')->placeholder('Any administrator'),
                        TextEntry::make('decider.name')->label('Decided by')->placeholder('—')
                            ->helperText(fn ($record) => $record->decided_at?->format('j M Y, H:i')),
                        TextEntry::make('comment')->placeholder('—'),
                    ]),
                ]),
        ]);
    }

    /**
     * Super-admin override on the current step (e.g. approver on leave).
     *
     * @return array<int, Action>
     */
    public static function decisionActions(): array
    {
        $decide = fn (bool $approve) => function (AccessRequest $record, array $data, DecideApprovalStep $action) use ($approve): void {
            $action->handle($record->currentStep(), auth()->user(), $approve, $data['comment'] ?? null);
            Notification::make()->title($approve ? 'Step approved' : 'Request rejected')->success()->send();
        };

        $visible = fn (AccessRequest $record) => $record->status->isOpen()
            && $record->currentStep()?->canBeDecidedBy(auth()->user());

        return [
            Action::make('approveStep')->label('Approve current step')->color('success')->icon(Heroicon::OutlinedCheck)
                ->visible($visible)
                ->schema([Textarea::make('comment')->label('Comment (optional)')])
                ->action($decide(true)),
            Action::make('rejectStep')->label('Reject')->color('danger')->icon(Heroicon::OutlinedXMark)
                ->visible($visible)
                ->schema([Textarea::make('comment')->label('Reason')->required()])
                ->action($decide(false)),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAccessRequests::route('/'),
            'view' => ViewAccessRequest::route('/{record}'),
        ];
    }
}
