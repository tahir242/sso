<?php

namespace App\Filament\Pages\Settings;

use App\Domain\Notifications\GreenApi;
use App\Domain\Settings\NotificationSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Minishlink\WebPush\VAPID;
use NotificationChannels\WebPush\PushSubscription;
use Throwable;

class ManageNotifications extends BaseSettingsPage
{
    protected static string $settings = NotificationSettings::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBellAlert;

    protected static ?string $navigationLabel = 'Notifications';

    protected static ?string $slug = 'settings/notifications';

    protected static ?string $title = 'Notifications';

    protected static ?int $navigationSort = 31;

    protected array $secretFields = ['green_api_token', 'vapid_private_key'];

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('WhatsApp (Green-API)')
                ->description('Alerts and approval reminders on WhatsApp. Green-API runs a WhatsApp account through WhatsApp Web, so it is never used for sign-in codes. Use a dedicated number.')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    Toggle::make('whatsapp_enabled')->label('Send WhatsApp messages')->columnSpanFull(),
                    TextInput::make('green_api_url')->label('API URL')->url()->required()->helperText('Shown in the Green-API console for your instance.'),
                    TextInput::make('green_api_instance_id')->label('Instance ID')->maxLength(30),
                    TextInput::make('green_api_token')->label('API token')->password()->revealable()
                        ->placeholder('Leave empty to keep the current token')->autocomplete('new-password'),
                    TextInput::make('default_country_code')->label('Default country code')->numeric()->required()->maxLength(4)
                        ->helperText('Added to local numbers such as 0300 1234567 (92 = Pakistan).'),
                ]),
            Section::make('Browser notifications')
                ->description('Push notifications in Chrome, Edge and Firefox, even when the SSO tab is closed. The SSO must be served over HTTPS (or localhost).')
                ->columns(1)
                ->columnSpanFull()
                ->schema([
                    Toggle::make('webpush_enabled')->label('Send browser notifications'),
                    TextEntry::make('vapid_public_key')->label('Public key')
                        ->state(fn () => app(NotificationSettings::class)->vapid_public_key ?? 'Not generated yet — use “Generate browser keys” above.')
                        ->copyable(),
                ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('checkWhatsApp')
                ->label('Check WhatsApp connection')
                ->icon(Heroicon::OutlinedSignal)
                ->color('gray')
                ->action(function (GreenApi $greenApi): void {
                    try {
                        $state = $greenApi->state();
                        Notification::make()
                            ->title($state['ok'] ? 'WhatsApp is connected' : 'WhatsApp is not ready ('.$state['state'].')')
                            ->body($state['message'])
                            ->{$state['ok'] ? 'success' : 'warning'}()
                            ->send();
                    } catch (Throwable $e) {
                        Notification::make()->title('Could not check WhatsApp')->body($e->getMessage())->danger()->persistent()->send();
                    }
                }),
            Action::make('testWhatsApp')
                ->label('Send test WhatsApp')
                ->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)
                ->color('gray')
                ->modalDescription('Checks the connection and that the number uses WhatsApp, then sends a short test message.')
                ->schema([TextInput::make('mobile')->label('Mobile number')->tel()->required()->placeholder('0300 1234567 or +92 300 1234567')])
                ->action(function (array $data, GreenApi $greenApi): void {
                    if (! $greenApi->ready()) {
                        Notification::make()->title('Turn WhatsApp on and save the instance ID and token first.')->warning()->send();

                        return;
                    }

                    try {
                        $state = $greenApi->state();
                        if (! $state['ok']) {
                            Notification::make()->title('WhatsApp is not ready ('.$state['state'].')')->body($state['message'])->warning()->persistent()->send();

                            return;
                        }

                        if (! $greenApi->hasWhatsApp($data['mobile'])) {
                            Notification::make()->title('This number does not use WhatsApp')->body('Check the number, including the country code.')->warning()->send();

                            return;
                        }

                        $id = $greenApi->send($data['mobile'], "*single sign-on*\nThis is a test message. WhatsApp notifications are working.");
                        Notification::make()->title('Test message sent')->body('Green-API message id '.$id)->success()->send();
                    } catch (Throwable $e) {
                        Notification::make()->title('WhatsApp test failed')->body($e->getMessage())->danger()->persistent()->send();
                    }
                }),
            Action::make('generateVapid')
                ->label('Generate browser keys')
                ->icon(Heroicon::OutlinedKey)
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('Creates a new key pair for browser notifications. Browsers that already subscribed must turn notifications on again.')
                ->action(function (NotificationSettings $settings): void {
                    $keys = VAPID::createVapidKeys();
                    $settings->vapid_public_key = $keys['publicKey'];
                    $settings->vapid_private_key = $keys['privateKey'];
                    $settings->save();

                    PushSubscription::query()->delete();

                    Notification::make()->title('New browser notification keys saved')->success()->send();
                    $this->fillForm();
                }),
        ];
    }
}
