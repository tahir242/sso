<?php

namespace App\Filament\Resources\Tickets;

use App\Domain\Helpdesk\Actions\ChangeTicketStatus;
use App\Domain\Helpdesk\Actions\ReplyToTicket;
use App\Domain\Helpdesk\Actions\UpdateTicketDetails;
use App\Domain\Helpdesk\Enums\TicketPriority;
use App\Domain\Helpdesk\Enums\TicketStatus;
use App\Domain\Helpdesk\Models\Ticket;
use App\Domain\Helpdesk\Models\TicketCategory;
use App\Domain\Helpdesk\Support\Helpdesk;
use App\Domain\Identity\Models\User;
use App\Filament\Resources\Tickets\Pages\ListTickets;
use App\Filament\Resources\Tickets\Pages\ViewTicket;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
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
 * The helpdesk queue. Helpdesk staff see every ticket; application owners
 * see their applications' tickets and anything assigned to them.
 */
class TicketResource extends Resource
{
    protected static ?string $model = Ticket::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLifebuoy;

    protected static string|UnitEnum|null $navigationGroup = 'Helpdesk';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'subject';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasAnyRole([...Helpdesk::AGENT_ROLES, 'app-owner']);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->handledBy(auth()->user());
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->where('status', TicketStatus::Open)->count();

        return $count ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return static::getEloquentQuery()->overdue()->exists() ? 'danger' : 'warning';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('last_activity_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['application', 'category', 'requester', 'assignee']))
            ->columns([
                TextColumn::make('id')->label('Ref')->formatStateUsing(fn (Ticket $record) => $record->reference)
                    ->searchable()->sortable()->fontFamily('mono')->size('sm'),
                TextColumn::make('subject')->searchable()->weight('medium')->wrap()->limit(80)
                    ->description(fn (Ticket $record) => $record->requester->name.' · '.($record->application?->name ?? 'General IT')
                        .($record->category ? ' · '.$record->category->name : '')),
                TextColumn::make('priority')->badge()->sortable(),
                TextColumn::make('status')->badge()->formatStateUsing(fn (TicketStatus $state) => $state->staffLabel()),
                TextColumn::make('assignee.name')->visibleFrom('md')->label('Assignee')->placeholder('Unassigned'),
                TextColumn::make('due_at')->visibleFrom('lg')->label('Due')->dateTime('j M, H:i')->sortable()
                    ->color(fn (Ticket $record) => $record->isOverdue() ? 'danger' : null)
                    ->icon(fn (Ticket $record) => $record->isOverdue() ? Heroicon::OutlinedExclamationTriangle : null),
                TextColumn::make('last_activity_at')->visibleFrom('xl')->label('Updated')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('priority')->options(TicketPriority::class)->multiple(),
                SelectFilter::make('application')->relationship('application', 'name'),
                SelectFilter::make('category')->relationship('category', 'name'),
                SelectFilter::make('assignee')->relationship('assignee', 'name'),
            ])
            ->recordActions([ViewAction::make()])
            ->recordUrl(fn (Ticket $record) => static::getUrl('view', ['record' => $record]));
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make()
                    ->columnSpan(2)
                    ->schema([
                        TextEntry::make('subject')->hiddenLabel()->weight('bold')->size('lg'),
                        ViewEntry::make('thread')->hiddenLabel()->view('filament.helpdesk.ticket-thread'),
                    ]),
                Section::make()
                    ->columnSpan(1)
                    ->schema([
                        TextEntry::make('reference')->label('Ticket')->fontFamily('mono'),
                        TextEntry::make('status')->badge()->formatStateUsing(fn (TicketStatus $state) => $state->staffLabel()),
                        TextEntry::make('priority')->badge(),
                        TextEntry::make('requester.name')->label('Requester')
                            ->helperText(fn (Ticket $record) => collect([$record->requester->designation, $record->requester->department?->name, $record->requester->mobile])->filter()->join(' · ')),
                        TextEntry::make('application.name')->label('Application')->placeholder('General IT'),
                        TextEntry::make('category.name')->label('Category')->placeholder('—'),
                        TextEntry::make('assignee.name')->label('Assignee')->placeholder('Unassigned'),
                        TextEntry::make('created_at')->label('Raised')->dateTime('j M Y, H:i'),
                        TextEntry::make('first_responded_at')->label('First reply')->dateTime('j M Y, H:i')
                            ->placeholder(fn (Ticket $record) => 'Due '.$record->response_due_at?->format('j M, H:i'))
                            ->color(fn (Ticket $record) => ! $record->first_responded_at && $record->response_due_at?->isPast() ? 'danger' : null),
                        TextEntry::make('due_at')->label('Resolve by')->dateTime('j M Y, H:i')
                            ->color(fn (Ticket $record) => $record->isOverdue() ? 'danger' : null),
                        TextEntry::make('resolved_at')->label('Resolved')->dateTime('j M Y, H:i')->placeholder('—'),
                    ]),
            ]);
    }

    public static function replyAction(): Action
    {
        return Action::make('reply')
            ->label('Reply')
            ->icon(Heroicon::OutlinedChatBubbleLeft)
            ->visible(fn (Ticket $record) => $record->status !== TicketStatus::Closed)
            ->modalWidth('2xl')
            ->fillForm(fn (Ticket $record) => ['status' => $record->status])
            ->schema([
                Textarea::make('body')->label('Message')->required()->rows(6)->maxLength(5000),
                Toggle::make('internal')->label('Internal note (staff only)')
                    ->helperText('Notes are never shown to the requester.'),
                Select::make('status')->label('Then set status to')->options(TicketStatus::class)->required(),
                FileUpload::make('attachments')->multiple()->maxFiles(3)->maxSize(5120)->storeFiles(false)
                    ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp', 'application/pdf']),
            ])
            ->action(function (Ticket $record, array $data, ReplyToTicket $reply): void {
                $reply->handle($record, auth()->user(), $data['body'], (bool) $data['internal'], self::status($data['status']), array_values($data['attachments'] ?? []));
                Notification::make()->title($data['internal'] ? 'Note added' : 'Reply sent')->success()->send();
            });
    }

    public static function takeAction(): Action
    {
        return Action::make('take')
            ->label('Assign to me')
            ->icon(Heroicon::OutlinedHandRaised)
            ->color('gray')
            ->visible(fn (Ticket $record) => $record->assignee_id === null && $record->status->isActive())
            ->action(function (Ticket $record, UpdateTicketDetails $update): void {
                $update->handle($record, auth()->user(), auth()->user(), $record->priority, $record->category);
                Notification::make()->title('Assigned to you')->success()->send();
            });
    }

    public static function detailsAction(): Action
    {
        return Action::make('details')
            ->label('Assign / priority')
            ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
            ->color('gray')
            ->fillForm(fn (Ticket $record) => [
                'assignee_id' => $record->assignee_id,
                'priority' => $record->priority,
                'category_id' => $record->category_id,
            ])
            ->schema(fn (Ticket $record) => [
                Select::make('assignee_id')->label('Assignee')->placeholder('Unassigned')
                    ->options(Helpdesk::assignableFor($record)->pluck('name', 'id'))->searchable(),
                Select::make('priority')->options(TicketPriority::class)->required()
                    ->helperText('Changing it recalculates the resolve-by date from when the ticket was raised.'),
                Select::make('category_id')->label('Category')->placeholder('None')
                    ->options(TicketCategory::query()->offeredFor($record->application_id)->pluck('name', 'id')),
            ])
            ->action(function (Ticket $record, array $data, UpdateTicketDetails $update): void {
                $update->handle(
                    $record,
                    auth()->user(),
                    $data['assignee_id'] ? User::find($data['assignee_id']) : null,
                    $data['priority'] instanceof TicketPriority ? $data['priority'] : TicketPriority::from($data['priority']),
                    $data['category_id'] ? TicketCategory::find($data['category_id']) : null,
                );
                Notification::make()->title('Ticket updated')->success()->send();
            });
    }

    public static function statusAction(): Action
    {
        return Action::make('status')
            ->label('Change status')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('gray')
            ->fillForm(fn (Ticket $record) => ['status' => $record->status])
            ->schema([Select::make('status')->options(TicketStatus::class)->required()])
            ->action(function (Ticket $record, array $data, ChangeTicketStatus $change): void {
                $change->handle($record, auth()->user(), self::status($data['status']));
                Notification::make()->title('Status updated')->success()->send();
            });
    }

    private static function status(TicketStatus|string $value): TicketStatus
    {
        return $value instanceof TicketStatus ? $value : TicketStatus::from($value);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTickets::route('/'),
            'view' => ViewTicket::route('/{record}'),
        ];
    }
}
