<?php

namespace SsoClient\Http;

/**
 * Minimal HTTP abstraction (swappable in tests).
 */
interface Transport
{
    /**
     * @param  array<string, string>  $headers
     * @param  array<string, string>|null  $form  urlencoded body for POST
     * @return array{status: int, body: string}
     */
    public function request(string $method, string $url, array $headers = [], ?array $form = null): array;
}
