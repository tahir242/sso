<?php

namespace SsoClient;

/**
 * What Gate wants sent instead of the page: a redirect, an error page, or
 * a bare status for the SSO's back-channel call.
 */
final class GateResponse
{
    /**
     * @param  array<string, string>  $headers
     */
    public function __construct(
        public readonly int $status,
        public readonly array $headers = [],
        public readonly string $body = '',
    ) {}

    public static function redirect(string $url): self
    {
        return new self(302, ['Location' => $url, 'Cache-Control' => 'no-store']);
    }

    public static function page(int $status, string $title, string $message, ?string $retry = null): self
    {
        $e = fn (string $s) => htmlspecialchars($s, ENT_QUOTES);

        return new self($status, ['Content-Type' => 'text/html; charset=utf-8'],
            '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>'.$e($title).'</title></head>'
            .'<body style="font-family:system-ui,sans-serif;max-width:32rem;margin:15vh auto;padding:0 1rem;color:#27272a">'
            .'<h1 style="font-size:1.4rem">'.$e($title).'</h1><p>'.$e($message).'</p>'
            .($retry ? '<p><a href="'.$e($retry).'" style="color:#b50000">Try again</a></p>' : '')
            .'</body></html>');
    }
}
