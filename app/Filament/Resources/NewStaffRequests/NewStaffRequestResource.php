<?php

namespace App\Filament\Resources\NewStaffRequests;

use App\Domain\Identity\Enums\AuthSource;
use App\Domain\Requests\Actions\CompleteNewStaffRequest;
use App\Domain\Requests\Enums\NewStaffStatus;
use App\Domain\Requests\Models\NewStaffRequest;
use App\Domain\Settings\SecuritySettings;
use App\Filament\Resources\NewStaffRequests\Pages\ListNewStaffRequests;
use App\Filament\Resources\NewStaffRequests\Pages\ViewNewStaffRequest;
use App\Filament\Resources\Users\UserResource;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use UnitEnum;

/**
 * New staff account requests. Helpdesk creates the account once the head of
 * department has confirmed.
 */
class NewStaffRequestResource extends Resource
{
    protected static ?string $model = NewStaffRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserPlus;

    protected static string|UnitEnum|null $navigationGroup = 'Requests';

    protected static ?string $navigationLabel = 'New staff requests';

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasAnyRole(['super-admin', 'helpdesk']);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = NewStaffRequest::where('status', NewStaffStatus::Approved)->count();

        return $count ? (string) $count : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with(['department', 'hod']))
            ->columns([
                TextColumn::make('created_at')->label('Submitted')->date('j M Y')->sortable(),
                TextColumn::make('full_name')->searchable()->weight('medium')
                    ->description(fn (NewStaffRequest $r) => $r->designation),
                TextColumn::make('department.name')->label('Department'),
                TextColumn::make('joining_date')->label('Joining')->date('j M Y')->placeholder('—'),
                TextColumn::make('status')->badge(),
            ])
            ->filters([
                SelectFilter::make('status')->options(NewStaffStatus::class)->default(NewStaffStatus::Approved->value),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('full_name'),
                    TextEntry::make('emp_id')->label('Employee ID')->placeholder('—'),
                    TextEntry::make('status')->badge(),
                    TextEntry::make('department.name')->label('Department'),
                    TextEntry::make('designation')->placeholder('—'),
                    TextEntry::make('joining_date')->date('j M Y')->placeholder('—'),
                    TextEntry::make('email')->placeholder('—')->copyable(),
                    TextEntry::make('mobile')->placeholder('—'),
                    TextEntry::make('requester_ip')->label('Submitted from'),
                    TextEntry::make('applications')->label('Applications requested')->columnSpanFull()
                        ->state(fn (NewStaffRequest $r) => $r->requestedApplications()->pluck('name')->join(', ') ?: 'None'),
                    TextEntry::make('justification')->label('Note')->columnSpanFull()->placeholder('—'),
                    TextEntry::make('hod.name')->label('Confirmed by')->placeholder('—')
                        ->helperText(fn (NewStaffRequest $r) => $r->hod_decided_at?->format('j M Y, H:i')),
                    TextEntry::make('hod_comment')->label('HOD comment')->placeholder('—'),
                    TextEntry::make('createdUser.username')->label('Account created')->placeholder('—')
                        ->url(fn (NewStaffRequest $r) => $r->createdUser ? UserResource::getUrl('edit', ['record' => $r->createdUser]) : null),
                ]),
        ]);
    }

    public static function completeAction(): Action
    {
        return Action::make('complete')
            ->label('Create account')
            ->icon(Heroicon::OutlinedUserPlus)
            ->visible(fn (NewStaffRequest $record) => $record->status === NewStaffStatus::Approved)
            ->modalDescription('Creates the SSO account and files access requests for the applications asked for (they go straight to each application owner).')
            ->fillForm(fn (NewStaffRequest $record) => [
                'username' => Str::of($record->full_name)->lower()->ascii()->replaceMatches('/[^a-z\s]/', '')->squish()->replace(' ', '.')->limit(60, '')->toString(),
                'auth_source' => AuthSource::ActiveDirectory->value,
            ])
            ->schema([
                TextInput::make('username')->required()->maxLength(100)->regex('/^[A-Za-z0-9._-]+$/')->rule(Rule::unique('users', 'username'))
                    ->helperText('For Windows (AD) accounts this must equal their Windows logon name.'),
                Select::make('auth_source')->label('Password source')->options(AuthSource::class)->required()->live(),
                TextInput::make('password')->label('Temporary password')->password()->revealable()
                    ->visible(fn (Get $get) => in_array($get('auth_source'), [AuthSource::Local, AuthSource::Local->value], true))
                    ->required(fn (Get $get) => in_array($get('auth_source'), [AuthSource::Local, AuthSource::Local->value], true))
                    ->rule(fn () => Password::min(app(SecuritySettings::class)->password_min_length)->letters()->numbers())
                    ->helperText('Give it to the person in person; they should change it at first sign-in.'),
            ])
            ->action(function (NewStaffRequest $record, array $data, CompleteNewStaffRequest $complete): void {
                $user = $complete->handle($record, auth()->user(), $data);
                Notification::make()->title("Account {$user->username} created")->success()->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNewStaffRequests::route('/'),
            'view' => ViewNewStaffRequest::route('/{record}'),
        ];
    }
}
