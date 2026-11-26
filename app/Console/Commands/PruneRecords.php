<?php

namespace App\Console\Commands;

use App\Support\Operations\Retention;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('sso:prune')]
#[Description('Delete old application sessions, sign-in events, change history, read notifications, expired remembered computers and old failed jobs')]
class PruneRecords extends Command
{
    public function handle(Retention $retention): int
    {
        foreach ($retention->prune() as $table => $deleted) {
            $this->components->twoColumnDetail($table, number_format($deleted).' deleted');
        }

        return self::SUCCESS;
    }
}
