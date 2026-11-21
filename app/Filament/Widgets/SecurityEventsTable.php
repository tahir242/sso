<?php

namespace App\Filament\Widgets;

use App\Domain\Audit\Enums\AuthEventType;
use App\Domain\Audit\Models\AuthEvent;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Latest security-relevant sign-in events (failures, lockouts, 2-step changes).
 */
class SecurityEventsTable extends TableWidget
{
    protected static ?int $sort = 6;

    protected static ?string $heading = 'Recent security events';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->hasAnyRole(['super-admin', 'auditor', 'helpdesk']);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => AuthEvent::query()->with('user:id,name,username')->whereIn('event', [
                AuthEventType::LoginFailed, AuthEventType::LoginLocked, AuthEventType::MfaFailed,
                AuthEventType::MfaDisabled, AuthEventType::MfaReset, AuthEventType::MfaRecoveryCodeUsed,
                AuthEventType::PasswordChanged,
            ])->latest('created_at'))
            ->defaultPaginationPageOption(5)
            ->paginated([5, 10, 25])
            ->columns([
                TextColumn::make('created_at')->label('When')->since()->dateTimeTooltip(),
                TextColumn::make('event')->badge()->color(fn (AuthEventType $state) => match ($state) {
                    AuthEventType::LoginLocked, AuthEventType::MfaReset => 'danger',
                    AuthEventType::LoginFailed, AuthEventType::MfaFailed => 'warning',
                    default => 'gray',
                }),
                TextColumn::make('user.name')->label('Account')->placeholder('Unknown')->description(fn (AuthEvent $record) => $record->identifier),
                TextColumn::make('ip_address')->label('IP address'),
            ]);
    }
}
