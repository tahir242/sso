<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Federation\Actions\EndClientSessions;
use App\Domain\Federation\TokenRevoker;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Ends every SSO session, application session and token of a user — used
 * when an account is disabled or locked.
 */
final readonly class SignOutEverywhere
{
    public function __construct(
        private EndClientSessions $clientSessions,
        private TokenRevoker $tokens,
    ) {}

    public function handle(User $user): void
    {
        DB::table('sessions')->where('user_id', $user->getKey())->delete();

        $this->clientSessions->forUser($user->getKey());
        $this->tokens->forUser($user->getKey());
    }
}
