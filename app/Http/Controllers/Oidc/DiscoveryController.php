<?php

namespace App\Http\Controllers\Oidc;

use App\Domain\Federation\Oidc\Claims;
use App\Domain\Federation\Oidc\Issuer;
use Illuminate\Http\JsonResponse;

/**
 * /.well-known/openid-configuration — lets standard OIDC libraries (PHP,
 * ASP.NET, Spring) configure themselves from the issuer URL alone.
 */
class DiscoveryController
{
    public function __invoke(): JsonResponse
    {
        $issuer = Issuer::url();

        return response()->json([
            'issuer' => $issuer,
            'authorization_endpoint' => $issuer.'/oauth/authorize',
            'token_endpoint' => $issuer.'/oauth/token',
            'userinfo_endpoint' => $issuer.'/oauth/userinfo',
            'jwks_uri' => $issuer.'/oauth/jwks',
            'end_session_endpoint' => $issuer.'/oauth/logout',
            'scopes_supported' => array_keys(Claims::SCOPES),
            'response_types_supported' => ['code'],
            'response_modes_supported' => ['query'],
            'grant_types_supported' => ['authorization_code', 'refresh_token', 'client_credentials'],
            'subject_types_supported' => ['public'],
            'id_token_signing_alg_values_supported' => ['RS256'],
            'token_endpoint_auth_methods_supported' => ['client_secret_basic', 'client_secret_post'],
            'code_challenge_methods_supported' => ['S256'],
            'prompt_values_supported' => ['none', 'login'],
            'acr_values_supported' => [Issuer::ACR_PASSWORD, Issuer::ACR_MFA],
            'claims_supported' => [
                'sub', 'iss', 'aud', 'exp', 'iat', 'auth_time', 'nonce', 'sid', 'amr', 'acr',
                'name', 'given_name', 'family_name', 'preferred_username', 'updated_at', 'email', 'email_verified',
                'user_id', 'emp_id', 'emp_initial', 'department', 'designation', 'roles', 'applications',
            ],
            'claims_parameter_supported' => false,
            'request_parameter_supported' => false,
            'backchannel_logout_supported' => true,
            'backchannel_logout_session_supported' => true,
        ])->header('Cache-Control', 'public, max-age=3600');
    }
}
