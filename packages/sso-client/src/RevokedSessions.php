<?php

namespace SsoClient;

/**
 * SSO sessions (`sid`) ended through back-channel logout. Works with any PHP
 * session storage: the application checks the list on each request instead
 * of having to find and delete the other user's session itself.
 */
final class RevokedSessions
{
    private const TTL = 86400; // longer than any SSO session

    public function __construct(private readonly Config $config) {}

    public function revoke(string $sid): void
    {
        @file_put_contents($this->path($sid), (string) (time() + self::TTL), LOCK_EX);
        $this->prune();
    }

    public function isRevoked(?string $sid): bool
    {
        if ($sid === null || $sid === '') {
            return false;
        }

        $file = $this->path($sid);

        return is_file($file) && (int) file_get_contents($file) > time();
    }

    private function path(string $sid): string
    {
        $dir = $this->config->cacheDir().DIRECTORY_SEPARATOR.'revoked';

        if (! is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }

        return $dir.DIRECTORY_SEPARATOR.hash('sha256', $sid);
    }

    private function prune(): void
    {
        if (random_int(1, 50) !== 1) {
            return;
        }

        foreach (glob(dirname($this->path('x')).DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
            if ((int) @file_get_contents($file) < time()) {
                @unlink($file);
            }
        }
    }
}
