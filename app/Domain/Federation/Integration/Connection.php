<?php

namespace App\Domain\Federation\Integration;

use App\Domain\Federation\Models\Application;
use App\Domain\Federation\Oidc\Claims;
use App\Domain\Federation\Oidc\Issuer;
use Illuminate\Support\Str;

/**
 * Everything a developer needs to connect one application, with sensible
 * values when the application has not configured something yet. Used by
 * the admin "Connect" page, the integration guides and the PHP kit.
 */
final readonly class Connection
{
    /**
     * @param  array<int, string>  $redirectUris
     */
    public function __construct(
        public string $issuer,
        public string $clientId,
        public string $appName,
        public string $appUrl,
        public array $redirectUris,
        public string $postLogoutUri,
        public ?string $backchannelUri,
        public string $scopes,
    ) {}

    public static function for(Application $application): self
    {
        $appUrl = Str::finish($application->base_url, '/');

        return new self(
            issuer: Issuer::url(),
            clientId: (string) $application->oauth_client_id,
            appName: $application->name,
            appUrl: $appUrl,
            redirectUris: array_values($application->client?->redirect_uris ?? []),
            postLogoutUri: $application->post_logout_redirect_uris[0] ?? $appUrl,
            backchannelUri: $application->backchannel_logout_uri,
            scopes: implode(' ', Claims::CLIENT_SCOPES),
        );
    }

    /**
     * Placeholders for the guides when no application is chosen.
     */
    public static function example(): self
    {
        return new self(
            issuer: Issuer::url(),
            clientId: 'YOUR-CLIENT-ID',
            appName: 'Your application',
            appUrl: 'https://yourapp.example.com/',
            redirectUris: [],
            postLogoutUri: 'https://yourapp.example.com/',
            backchannelUri: null,
            scopes: implode(' ', Claims::CLIENT_SCOPES),
        );
    }

    /** The registered redirect URI, or the one the given stack would use by default. */
    public function redirectUri(string $defaultPath): string
    {
        return $this->redirectUris[0] ?? $this->appUrl.ltrim($defaultPath, '/');
    }

    /** Page that runs the one-line PHP gate. */
    public function gatePage(): string
    {
        foreach ($this->redirectUris as $uri) {
            if (str_contains($uri, 'sso=callback')) {
                return (string) strtok($uri, '?');
            }
        }

        return $this->appUrl.'index.php';
    }

    /**
     * Values substituted into the guides ({{issuer}}, {{client_id}}, ...).
     *
     * @return array<string, string>
     */
    public function placeholders(): array
    {
        return [
            '{{issuer}}' => $this->issuer,
            '{{discovery}}' => $this->issuer.'/.well-known/openid-configuration',
            '{{client_id}}' => $this->clientId,
            '{{app_name}}' => $this->appName,
            '{{app_url}}' => $this->appUrl,
            '{{post_logout_uri}}' => $this->postLogoutUri,
            '{{scopes}}' => $this->scopes,
            '{{gate_page}}' => $this->gatePage(),
            '{{php_redirect_uri}}' => $this->gatePage().'?sso=callback',
            '{{php_backchannel_uri}}' => $this->gatePage().'?sso=backchannel-logout',
            '{{dotnet_redirect_uri}}' => $this->appUrl.'signin-oidc',
            '{{dotnet_backchannel_uri}}' => $this->appUrl.'signout-backchannel',
            '{{java_redirect_uri}}' => $this->appUrl.'login/oauth2/code/sso',
            '{{java_backchannel_uri}}' => $this->appUrl.'logout/connect/back-channel/sso',
            '{{python_redirect_uri}}' => $this->appUrl.'auth/callback',
            '{{python_backchannel_uri}}' => $this->appUrl.'auth/backchannel-logout',
            '{{node_redirect_uri}}' => $this->appUrl.'callback',
            '{{node_backchannel_uri}}' => $this->appUrl.'backchannel-logout',
        ];
    }
}
