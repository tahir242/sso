<?php

use App\Domain\Access\Notifications\ApprovalNeeded;
use App\Domain\Identity\Models\User;
use App\Domain\Notifications\BaseNotification;
use App\Domain\Notifications\Channel;
use App\Domain\Notifications\Channels\WhatsAppChannel;
use App\Domain\Notifications\GreenApi;
use App\Domain\Notifications\NotificationCategory;
use App\Domain\Notifications\TestNotification;
use App\Domain\Settings\NotificationSettings;
use App\Filament\Pages\Settings\ManageNotifications;
use App\Livewire\Account\Notifications;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use NotificationChannels\WebPush\WebPushChannel;
use Spatie\Permission\Models\Role;

function enableWhatsApp(): void
{
    $settings = app(NotificationSettings::class);
    $settings->fill([
        'whatsapp_enabled' => true,
        'green_api_url' => 'https://api.green-api.test',
        'green_api_instance_id' => '1101000001',
        'green_api_token' => 'secret-token',
    ])->save();
}

function enableWebPush(): void
{
    $settings = app(NotificationSettings::class);
    $settings->fill(['webpush_enabled' => true, 'vapid_public_key' => 'pub', 'vapid_private_key' => 'priv'])->save();
}

it('normalises Pakistani mobile numbers for WhatsApp', function (string $input, ?string $chatId) {
    expect(app(GreenApi::class)->chatId($input))->toBe($chatId);
})->with([
    ['0300-1234567', '923001234567@c.us'],
    ['+92 300 1234567', '923001234567@c.us'],
    ['0092 300 1234567', '923001234567@c.us'],
    ['12345', null],
    ['', null],
]);

it('only uses channels the user can actually receive', function () {
    $user = User::factory()->create(['email' => 'a@example.test', 'mobile' => null]);

    expect($user->notificationChannels(NotificationCategory::Access))->toBe([Channel::Mail]);

    enableWhatsApp();
    $user->update(['mobile' => '03001234567']);
    expect($user->fresh()->notificationChannels(NotificationCategory::Security))->toBe([Channel::Mail, Channel::WhatsApp]);

    enableWebPush();
    $user->updatePushSubscription('https://push.example/abc', 'key', 'auth');
    expect($user->fresh()->notificationChannels(NotificationCategory::Access))->toBe([Channel::Mail, Channel::WebPush]);
});

it('follows the user’s choices but never silences own-account security alerts', function () {
    $user = User::factory()->create(['email' => 'a@example.test', 'notification_preferences' => ['access' => [], 'security' => []]]);

    expect($user->notificationChannels(NotificationCategory::Access))->toBe([])
        ->and($user->notificationChannels(NotificationCategory::Security))->toBe([Channel::Mail]);
});

it('delivers through WhatsApp via Green-API', function () {
    Http::fake(['api.green-api.test/*' => Http::response(['idMessage' => 'ABC123'])]);
    enableWhatsApp();
    $user = User::factory()->create(['mobile' => '0300 1234567', 'email' => null]);

    $notification = new class extends BaseNotification
    {
        public function category(): NotificationCategory
        {
            return NotificationCategory::Security;
        }

        public function title(User $n): string
        {
            return 'Hello';
        }

        public function body(User $n): string
        {
            return 'World';
        }

        public function url(User $n): string
        {
            return 'https://sso.test/x';
        }
    };

    expect($notification->via($user))->toContain(WhatsAppChannel::class);

    $user->notifyNow($notification);

    Http::assertSent(fn ($request) => $request->url() === 'https://api.green-api.test/waInstance1101000001/sendMessage/secret-token'
        && $request['chatId'] === '923001234567@c.us'
        && str_contains($request['message'], '*Hello*'));
});

it('builds browser push messages with a link', function () {
    $user = User::factory()->create();
    $message = (new TestNotification)->toWebPush($user)->toArray();

    expect($message['title'])->toContain('Test notification')
        ->and($message['data']['url'])->toBe(route('account.notifications'));
});

it('renders branded approval emails', function () {
    $html = (string) (new TestNotification)->toMail(User::factory()->create())->render();

    expect($html)->toContain(config('app.name'))->toContain('#b50000');
});

it('saves preferences from the account page', function () {
    $user = User::factory()->create(['email' => 'a@example.test']);
    $this->actingAs($user);

    Livewire::test(Notifications::class)
        ->set('preferences.security', [])
        ->call('save')
        ->assertHasErrors('preferences.security')
        ->set('preferences.access', [])
        ->set('preferences.security', ['mail'])
        ->call('save')
        ->assertHasNoErrors();

    expect($user->fresh()->notification_preferences)->toBe(['access' => [], 'security' => ['mail'], 'helpdesk' => ['mail', 'webpush']]);
});

it('stores and removes this browser’s push subscription', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson(route('account.push.store'), [
        'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc',
        'keys' => ['p256dh' => 'key', 'auth' => 'auth'],
        'contentEncoding' => 'aes128gcm',
    ])->assertOk();

    expect($user->pushSubscriptions()->count())->toBe(1);

    $this->actingAs($user)->deleteJson(route('account.push.destroy'), ['endpoint' => 'https://fcm.googleapis.com/fcm/send/abc'])->assertOk();
    expect($user->pushSubscriptions()->count())->toBe(0);
});

it('includes web push in approval notifications when the approver subscribed', function () {
    enableWebPush();
    $approver = User::factory()->create(['email' => null]);
    $approver->updatePushSubscription('https://push.example/abc', 'key', 'auth');

    $notification = (new ReflectionClass(ApprovalNeeded::class))->newInstanceWithoutConstructor();

    expect($notification->via($approver))->toBe(['database', WebPushChannel::class]);
});

it('lets super-admins generate browser keys and test WhatsApp from settings', function () {
    Http::fake([
        'api.green-api.test/waInstance1101000001/getStateInstance/*' => Http::response(['stateInstance' => 'authorized']),
        'api.green-api.test/waInstance1101000001/checkWhatsapp/*' => Http::response(['existsWhatsapp' => true]),
        'api.green-api.test/waInstance1101000001/sendMessage/*' => Http::response(['idMessage' => 'X1']),
    ]);
    enableWhatsApp();
    $admin = tap(User::factory()->create(), fn (User $u) => $u->assignRole(Role::findOrCreate('super-admin', 'web')));
    $this->actingAs($admin);

    Livewire::test(ManageNotifications::class)
        ->assertOk()
        ->callAction('generateVapid')
        ->callAction('checkWhatsApp')
        ->assertNotified('WhatsApp is connected')
        ->callAction('testWhatsApp', ['mobile' => '03001234567'])
        ->assertNotified('Test message sent');

    $settings = app(NotificationSettings::class)->refresh();
    expect($settings->vapid_public_key)->not->toBeNull()
        ->and($settings->vapid_private_key)->not->toBeNull();
    Http::assertSentCount(4);
    Http::assertSent(fn ($request) => str_contains($request->url(), '/checkWhatsapp/') && $request['chatId'] === '923001234567@c.us');
});

it('explains Green-API failures without leaking the API token', function () {
    enableWhatsApp();
    $api = app(GreenApi::class);

    Http::fake(['api.green-api.test/*' => Http::sequence()
        ->push(['message' => 'Validation failed. Details: chatId is invalid secret-token'], 400)
        ->push('Unauthorized', 401)
        ->pushFailedConnection('cURL error 60: SSL certificate problem for https://api.green-api.test/waInstance1/sendMessage/secret-token')]);
    expect(fn () => $api->send('03001234567', 'Hi'))
        ->toThrow(RuntimeException::class, 'Green-API returned HTTP 400: Validation failed. Details: chatId is invalid ***');

    expect(fn () => $api->send('03001234567', 'Hi'))->toThrow(RuntimeException::class, 'rejected the instance ID or API token');

    try {
        $api->send('03001234567', 'Hi');
        $this->fail('Expected an exception');
    } catch (RuntimeException $e) {
        expect($e->getMessage())->toContain('curl.cainfo')->not->toContain('secret-token');
    }
});

it('reports the WhatsApp instance state and stops tests when not linked', function () {
    enableWhatsApp();
    Http::fake(['api.green-api.test/*' => Http::response(['stateInstance' => 'notAuthorized'])]);

    expect(app(GreenApi::class)->state())->toMatchArray(['ok' => false, 'state' => 'notAuthorized']);

    $this->actingAs(tap(User::factory()->create(), fn (User $u) => $u->assignRole(Role::findOrCreate('super-admin', 'web'))));
    Livewire::test(ManageNotifications::class)
        ->callAction('testWhatsApp', ['mobile' => '03001234567'])
        ->assertNotified('WhatsApp is not ready (notAuthorized)');

    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/sendMessage/'));
});

it('keeps WhatsApp messages within the 20,000 character limit', function () {
    enableWhatsApp();
    Http::fake(['api.green-api.test/*' => Http::response(['idMessage' => 'M1'])]);

    app(GreenApi::class)->send('03001234567', str_repeat('a', 25000));

    Http::assertSent(fn ($request) => mb_strlen($request['message']) <= GreenApi::MAX_MESSAGE_LENGTH);
});
