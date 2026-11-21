<?php

namespace App\Http\Controllers\Oidc;

use App\Domain\Federation\Oidc\SigningKeys;
use Illuminate\Http\JsonResponse;

/**
 * Public signing key(s) used to verify ID tokens and logout tokens.
 */
class JwksController
{
    public function __invoke(SigningKeys $keys): JsonResponse
    {
        return response()->json(['keys' => [$keys->jwk()]])
            ->header('Cache-Control', 'public, max-age=3600');
    }
}
