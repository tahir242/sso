<?php

namespace SsoClient\Http;

use SsoClient\SsoException;

final class CurlTransport implements Transport
{
    public function __construct(
        private readonly bool $verifyTls = true,
        private readonly int $timeout = 10,
    ) {}

    public function request(string $method, string $url, array $headers = [], ?array $form = null): array
    {
        $handle = curl_init($url);

        $headerLines = ['Accept: application/json'];
        foreach ($headers as $name => $value) {
            $headerLines[] = "{$name}: {$value}";
        }

        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => min(5, $this->timeout),
            CURLOPT_SSL_VERIFYPEER => $this->verifyTls,
            CURLOPT_SSL_VERIFYHOST => $this->verifyTls ? 2 : 0,
            CURLOPT_FOLLOWLOCATION => false,
        ]);

        if ($form !== null) {
            curl_setopt($handle, CURLOPT_POSTFIELDS, http_build_query($form));
        }

        $body = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        curl_close($handle);

        if ($body === false) {
            throw new SsoException("Could not reach the SSO server ({$error}).");
        }

        return ['status' => $status, 'body' => (string) $body];
    }
}
