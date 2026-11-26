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
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * OpenID Connect Back-Channel Logout 1.0: tells an application, server to
 * server, that the user's SSO session (`sid`) has ended.
 */
class SendBackChannelLogout implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /** How long an application that did not answer is skipped. */
    public const int DOWN_SECONDS = 60;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 300];

    public function __construct(public readonly int $clientSessionId) {}

    /**
     * Retried with back-off for an hour (an application may be down for a
     * while), instead of a fixed number of tries.
     */
    public function retryUntil(): DateTimeImmutable
    {
        return new DateTimeImmutable('+1 hour');
    }

    public function handle(SigningKeys $keys): void
    {
        $session = ClientSession::with('user')->find($this->clientSessionId);
        $application = $session ? Application::where('oauth_client_id', $session->oauth_client_id)->first() : null;

        if (! $session || ! $application?->backchannel_logout_uri) {
            return;
        }

        // An application that just failed is not called again for a minute:
        // its logouts wait in the queue instead of holding up the worker (and
        // the emails and other applications' logouts behind them).
        $downKey = 'backchannel-down:'.$application->oauth_client_id;

        if (Cache::has($downKey)) {
            $this->release(self::DOWN_SECONDS);

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

        try {
            $response = Http::asForm()
                ->connectTimeout(2)
                ->timeout(5)
                ->withHeaders(['Cache-Control' => 'no-store'])
                ->post($application->backchannel_logout_uri, ['logout_token' => $token]);
        } catch (ConnectionException $e) {
            Cache::put($downKey, true, self::DOWN_SECONDS);

            throw $e;
        }

        if ($response->serverError()) {
            Cache::put($downKey, true, self::DOWN_SECONDS);
        }

        $response->throw();
    }
}
