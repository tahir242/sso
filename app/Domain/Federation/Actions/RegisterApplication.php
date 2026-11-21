<?php

namespace App\Domain\Federation\Actions;

use App\Domain\Federation\ApiScopes;
use App\Domain\Federation\Models\Application;
use App\Domain\Federation\Models\OAuthClient;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\ClientRepository;

/**
 * Registers an application as an OIDC client (confidential: authorization
 * code + refresh token, plus client credentials for the API) and gives it a
 * default "user" role.
 *
 * Returns the plain client secret — it is stored hashed and shown only once.
 */
final readonly class RegisterApplication
{
    public function __construct(private ClientRepository $clients) {}

    /**
     * @param  array<string, mixed>  $attributes  Application fields
     * @param  array<int, string>  $redirectUris
     * @return array{0: Application, 1: string}
     */
    public function handle(array $attributes, array $redirectUris): array
    {
        return DB::transaction(function () use ($attributes, $redirectUris) {
            /** @var OAuthClient $client */
            $client = $this->clients->createAuthorizationCodeGrantClient($attributes['name'], array_values($redirectUris), confidential: true);

            // Same credentials also call the server-to-server API.
            $client->forceFill(['grant_types' => ['authorization_code', 'refresh_token', 'client_credentials']])->save();

            $application = Application::create([
                'api_scopes' => ApiScopes::DEFAULTS,
                ...$attributes,
                'oauth_client_id' => $client->getKey(),
            ]);

            $application->roles()->create([
                'key' => 'user',
                'name' => 'User',
                'description' => 'Standard access',
                'is_default' => true,
            ]);

            return [$application, (string) $client->plainSecret];
        });
    }
}
