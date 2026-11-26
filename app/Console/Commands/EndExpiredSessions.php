<?php

namespace App\Console\Commands;

use App\Domain\Federation\Actions\EndClientSessions;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('sso:end-expired-sessions')]
#[Description('Sign users out of applications when their SSO session has expired (idle or absolute timeout)')]
class EndExpiredSessions extends Command
{
    public function handle(EndClientSessions $clientSessions): int
    {
        $count = $clientSessions->forExpiredSsoSessions();
        $this->components->info("{$count} application session(s) ended.");

        return self::SUCCESS;
    }
}
