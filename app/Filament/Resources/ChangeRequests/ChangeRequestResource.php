<?php

namespace App\Filament\Resources\ChangeRequests;

use App\Domain\Requests\Actions\MoveChangeRequestToHelpdesk;
use App\Domain\Requests\Actions\UpdateChangeRequest;
use App\Domain\Requests\Enums\ChangeRequestStatus;
use App\Domain\Requests\Enums\ChangeRequestType;
use App\Domain\Requests\Enums\Priority;
use App\Domain\Requests\Models\ChangeRequest;
use App\Filament\Resources\ChangeRequests\Pages\ListChangeRequests;
use App\Filament\Resources\ChangeRequests\Pages\ViewChangeRequest;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\SpatieMediaLibraryImageEntry;
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
 * Improvement suggestions staff send for applications (portal: My requests
 * → Suggestions). Problems belong to the helpdesk; one sent here can be moved
 * there with "Move to IT helpdesk". App owners see their
 * own applications'; super-admins and helpdesk see all.
 */
class ChangeRequestResource extends Resource
{
    protected static ?string $model = ChangeRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLightBulb;

    protected static string|UnitEnum|null $navigationGroup = 'Requests';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Change requests';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasAnyRole(['super-admin', 'app-owner', 'helpdesk']);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        return parent::getEloquentQuery()
            ->when(! $user?->hasAnyRole(['super-admin', 'helpdesk']), fn (Builder $q) => $q
                ->whereHas('application', fn (Builder $a) => $a->where('owner_user_id', $user?->getKey())));
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->where('status', ChangeRequestStatus::Submitted)->count();

        return $count ? (string) $count : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['application', 'requester']))
            ->columns([
                TextColumn::make('created_at')->label('Sent')->date('j M Y')->sortable(),
                TextColumn::make('application.name')->label('Application')->searchable(),
                TextColumn::make('title')->searchable()->weight('medium')->wrap()
                    ->description(fn (ChangeRequest $r) => $r->type->getLabel()),
                TextColumn::make('requester.name')->label('From'),
                TextColumn::make('priority')->badge()->sortable(),
                TextColumn::make('status')->badge(),
            ])
            ->filters([
                SelectFilter::make('status')->options(ChangeRequestStatus::class)->multiple(),
                SelectFilter::make('type')->options(ChangeRequestType::class),
                SelectFilter::make('priority')->options(Priority::class),
                SelectFilter::make('application')->relationship('application', 'name'),
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
                    TextEntry::make('application.name')->label('Application'),
                    TextEntry::make('type'),
                    TextEntry::make('priority')->badge(),
                    TextEntry::make('status')->badge(),
                    TextEntry::make('requester.name')->label('From')
                        ->helperText(fn (ChangeRequest $r) => collect([$r->requester?->username, $r->requester?->department?->name])->filter()->join(' · ')),
                    TextEntry::make('created_at')->label('Sent')->dateTime('j M Y, H:i'),
                    TextEntry::make('decider.name')->label('Last updated by')->placeholder('—'),
                    TextEntry::make('decided_at')->label('Updated')->dateTime('j M Y, H:i')->placeholder('—'),
                    TextEntry::make('title')->columnSpanFull()->weight('bold'),
                    TextEntry::make('description')->columnSpanFull()->prose(),
                    TextEntry::make('owner_comment')->label('Reply to requester')->columnSpanFull()->placeholder('—'),
                    SpatieMediaLibraryImageEntry::make('attachments')->collection('attachments')->columnSpanFull()
                        ->visible(fn (ChangeRequest $r) => $r->getMedia('attachments')->isNotEmpty()),
                ]),
        ]);
    }

    public static function updateAction(): Action
    {
        return Action::make('update')
            ->label('Update status')
            ->icon(Heroicon::OutlinedArrowPath)
            ->visible(fn (ChangeRequest $record) => UpdateChangeRequest::canManage($record, auth()->user()))
            ->fillForm(fn (ChangeRequest $record) => ['status' => $record->status, 'comment' => $record->owner_comment])
            ->schema([
                Select::make('status')->options(ChangeRequestStatus::class)->required(),
                Textarea::make('comment')->label('Reply to the requester')->rows(3)->maxLength(1000)
                    ->helperText('They see this under My requests and get a notification.'),
            ])
            ->action(function (ChangeRequest $record, array $data, UpdateChangeRequest $update): void {
                $update->handle($record, auth()->user(), ChangeRequestStatus::from($data['status'] instanceof ChangeRequestStatus ? $data['status']->value : $data['status']), $data['comment'] ?? null);
                Notification::make()->title('Requester notified')->success()->send();
            });
    }

    /**
     * A problem sent as a suggestion goes to the helpdesk as a ticket for the requester.
     */
    public static function moveToHelpdeskAction(): Action
    {
        return Action::make('moveToHelpdesk')
            ->label('Move to IT helpdesk')
            ->icon(Heroicon::OutlinedLifebuoy)
            ->color('gray')
            ->visible(fn (ChangeRequest $record) => MoveChangeRequestToHelpdesk::canMove($record, auth()->user()))
            ->requiresConfirmation()
            ->modalHeading('Move to the IT helpdesk?')
            ->modalDescription('Use this when the person reported a problem (something not working) rather than an improvement. A helpdesk ticket is raised for them with the same details and files, and this suggestion is closed with a note pointing to the ticket.')
            ->modalSubmitActionLabel('Move to helpdesk')
            ->action(function (ChangeRequest $record, MoveChangeRequestToHelpdesk $move): void {
                $ticket = $move->handle($record, auth()->user());
                Notification::make()->title("Ticket {$ticket->reference} raised")->body('The requester was told where to follow it.')->success()->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListChangeRequests::route('/'),
            'view' => ViewChangeRequest::route('/{record}'),
        ];
    }
}
