<?php

namespace App\Http\Controllers\Oidc;

use App\Domain\Federation\Models\Application;
use App\Domain\Federation\Oidc\Claims;
use App\Domain\Identity\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * OIDC UserInfo: current claims for the user behind an access token, limited
 * to the scopes that token was granted.
 */
class UserInfoController
{
    public function __invoke(Request $request, Claims $claims): JsonResponse
    {
        /** @var User $user */
        $user = $request->user('api');
        $token = $user->token();

        abort_unless($token && $token->can('openid') && $user->status->canSignIn(), 403);

        $application = Application::where('oauth_client_id', $token->client_id)->first();

        return response()->json([
            'sub' => (string) $user->ulid,
            ...$claims->for($user, $application, $token->scopes ?? []),
        ])->header('Cache-Control', 'no-store');
    }
}
