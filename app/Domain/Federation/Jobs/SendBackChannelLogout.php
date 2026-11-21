<?php

namespace App\Domain\Federation\Jobs;

use App\Domain\Federation\Models\Application;
use App\Domain\Federation\Models\ClientSession;
use App\Domain\Federation\Oidc\Issuer;
use App\Domain\Federation\Oidc\SigningKeys;
use DateTimeImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * OpenID Connect Back-Channel Logout 1.0: tells an application, server to
 * server, that the user's SSO session (`sid`) has ended.
 */
class SendBackChannelLogout implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 300];

    public function __construct(public readonly int $clientSessionId) {}

    public function handle(SigningKeys $keys): void
    {
        $session = ClientSession::with('user')->find($this->clientSessionId);
        $application = $session ? Application::where('oauth_client_id', $session->oauth_client_id)->first() : null;

        if (! $session || ! $application?->backchannel_logout_uri) {
            return;
        }

        $now = new DateTimeImmutable;

        $token = $keys->jwt()->builder()
            ->withHeader('kid', $keys->kid())
            ->withHeader('typ', 'logout+jwt')
            ->issuedBy(Issuer::url())
            ->permittedFor($session->oauth_client_id)
            ->relatedTo((string) $session->user?->ulid)
            ->identifiedBy((string) Str::uuid())
            ->issuedAt($now)
            ->expiresAt($now->modify('+2 minutes'))
            ->withClaim('sid', $session->sid)
            ->withClaim('events', ['http://schemas.openid.net/event/backchannel-logout' => new \stdClass])
            ->getToken($keys->jwt()->signer(), $keys->jwt()->signingKey())
            ->toString();

        Http::asForm()
            ->timeout(5)
            ->withHeaders(['Cache-Control' => 'no-store'])
            ->post($application->backchannel_logout_uri, ['logout_token' => $token])
            ->throw();
    }
}
