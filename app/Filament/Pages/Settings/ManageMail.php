<?php

namespace App\Filament\Pages\Settings;

use App\Domain\Settings\MailSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Mail;
use Throwable;

class ManageMail extends BaseSettingsPage
{
    protected static string $settings = MailSettings::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?string $navigationLabel = 'Email';

    protected static ?string $slug = 'settings/email';

    protected static ?string $title = 'Email';

    protected static ?int $navigationSort = 30;

    protected array $secretFields = ['password'];

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Delivery')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    Select::make('mailer')->options(['smtp' => 'SMTP server', 'log' => 'Don’t send — write to the log (development)'])->live()->required()->columnSpanFull(),
                    TextInput::make('host')->required(fn (Get $get) => $get('mailer') === 'smtp'),
                    TextInput::make('port')->numeric()->required(),
                    Select::make('scheme')->label('Encryption')->options(['smtp' => 'None / STARTTLS', 'smtps' => 'SSL/TLS'])->placeholder('Automatic'),
                    TextInput::make('username'),
                    TextInput::make('password')->password()->revealable()->placeholder('Leave empty to keep the current password')->autocomplete('new-password'),
                ]),
            Section::make('Sender')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('from_address')->email()->required(),
                    TextInput::make('from_name')->required(),
                ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('testEmail')
                ->label('Send test email')
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->color('gray')
                ->schema([TextInput::make('to')->label('Send to')->email()->required()->default(fn () => auth()->user()?->email)])
                ->action(function (array $data): void {
                    try {
                        Mail::raw("This is a test email from single sign-on.\n\nIf you received it, email delivery is working.", fn ($message) => $message
                            ->to($data['to'])
                            ->subject('single sign-on: test email'));

                        Notification::make()
                            ->title(config('mail.default') === 'log' ? 'Written to the log (delivery is set to “Don’t send”)' : 'Test email sent')
                            ->success()->send();
                    } catch (Throwable $e) {
                        Notification::make()->title('Email test failed')->body($e->getMessage())->danger()->persistent()->send();
                    }
                }),
        ];
    }
}
