<?php

namespace App\Domain\Notifications;

use App\Domain\Settings\NotificationSettings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Green-API client (WhatsApp), following https://green-api.com/en/docs/api/:
 *
 * - every method is {apiUrl}/waInstance{idInstance}/{method}/{apiTokenInstance};
 *   apiUrl is the host shown for the instance in the Green-API console
 *   (e.g. https://7103.api.greenapi.com; https://api.greenapi.com is the
 *   universal host during migrations);
 * - sendMessage: POST {chatId, message}, message up to 20,000 characters,
 *   personal chats are "<digits>@c.us", success returns idMessage;
 * - getStateInstance: GET, stateInstance = authorized | notAuthorized |
 *   blocked | sleepMode | starting | suspended (yellowCard is the old name);
 * - checkWhatsapp: POST {chatId}, returns existsWhatsapp.
 *
 * Green-API drives a WhatsApp account through WhatsApp Web, so it is used
 * for notifications only — never for sign-in codes. The API token is part
 * of every URL, so it is removed from any error text.
 */
final readonly class GreenApi
{
    public const int MAX_MESSAGE_LENGTH = 20000;

    private const array STATES = [
        'authorized' => 'Connected to WhatsApp.',
        'notAuthorized' => 'The instance is not linked to WhatsApp. Scan the QR code in the Green-API console.',
        'blocked' => 'WhatsApp has banned this number.',
        'sleepMode' => 'The phone is offline (sleep mode). It reconnects within about 5 minutes after the phone is back online.',
        'starting' => 'The instance is starting. Try again in a few minutes; reboot it in the console if this lasts longer than 5 minutes.',
        'suspended' => 'WhatsApp has temporarily restricted this number.',
        'yellowCard' => 'WhatsApp has temporarily restricted this number.',
    ];

    public function __construct(private NotificationSettings $settings) {}

    public function ready(): bool
    {
        return $this->settings->whatsappReady();
    }

    /**
     * @return array{state: string, ok: bool, message: string}
     *
     * @throws RuntimeException
     */
    public function state(): array
    {
        $state = (string) $this->call('get', 'getStateInstance')->json('stateInstance');

        return [
            'state' => $state,
            'ok' => $state === 'authorized',
            'message' => self::STATES[$state] ?? "Unknown instance state “{$state}”.",
        ];
    }

    /**
     * @throws RuntimeException
     */
    public function hasWhatsApp(string $mobile): bool
    {
        return (bool) $this->call('post', 'checkWhatsapp', ['chatId' => $this->requireChatId($mobile)])->json('existsWhatsapp');
    }

    /**
     * @return string the Green-API message id
     *
     * @throws RuntimeException when the message could not be handed to Green-API
     */
    public function send(string $mobile, string $message): string
    {
        $response = $this->call('post', 'sendMessage', [
            'chatId' => $this->requireChatId($mobile),
            'message' => Str::limit($message, self::MAX_MESSAGE_LENGTH - 1, '…'),
        ]);

        return (string) ($response->json('idMessage') ?: throw new RuntimeException('Green-API accepted the request but returned no message id.'));
    }

    /**
     * "0300-1234567" / "+92 300 1234567" -> "923001234567@c.us"
     */
    public function chatId(?string $mobile): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $mobile);

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        } elseif (str_starts_with($digits, '0')) {
            $digits = $this->settings->default_country_code.substr($digits, 1);
        }

        return strlen($digits) >= 10 && strlen($digits) <= 15 ? $digits.'@c.us' : null;
    }

    private function requireChatId(string $mobile): string
    {
        return $this->chatId($mobile) ?? throw new RuntimeException("“{$mobile}” is not a valid mobile number. Use the international format, e.g. +92 300 1234567.");
    }

    /**
     * @throws RuntimeException
     */
    private function call(string $method, string $endpoint, array $body = []): Response
    {
        if (! $this->settings->green_api_instance_id || ! $this->settings->green_api_token) {
            throw new RuntimeException('Enter the Green-API instance ID and API token first.');
        }

        $url = sprintf('%s/waInstance%s/%s/%s',
            rtrim($this->settings->green_api_url, '/'),
            $this->settings->green_api_instance_id,
            $endpoint,
            $this->settings->green_api_token,
        );

        try {
            $request = Http::timeout(20)->acceptJson();
            $response = $method === 'get' ? $request->get($url) : $request->asJson()->post($url, $body);
        } catch (ConnectionException $e) {
            $reason = str_contains($e->getMessage(), 'cURL error 60')
                ? 'the server could not verify Green-API’s SSL certificate (PHP has no CA bundle: set curl.cainfo and openssl.cafile in php.ini)'
                : $this->redact($e->getMessage());

            throw new RuntimeException("Green-API could not be reached: {$reason}", 0, $e);
        }

        if ($response->successful()) {
            return $response;
        }

        $detail = trim((string) ($response->json('message') ?? $response->json('error') ?? Str::limit(strip_tags($response->body()), 200)));

        throw new RuntimeException(match ($response->status()) {
            401 => 'Green-API rejected the instance ID or API token (HTTP 401). Copy both again from the Green-API console.',
            403 => 'Green-API refused access to this instance (HTTP 403). Check that the instance is active and paid.',
            404 => 'Green-API did not recognise the address (HTTP 404). Check the API URL shown for your instance in the console.',
            default => "Green-API returned HTTP {$response->status()}".($detail !== '' ? ': '.$this->redact($detail) : '.'),
        });
    }

    private function redact(string $text): string
    {
        $token = (string) $this->settings->green_api_token;

        return $token !== '' ? str_replace($token, '***', $text) : $text;
    }
}
