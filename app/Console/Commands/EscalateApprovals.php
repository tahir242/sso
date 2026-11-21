<?php

namespace App\Console\Commands;

use App\Domain\Access\Actions\EscalateOverdueApprovals;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('access:escalate')]
#[Description('Remind approvers and alert administrators about overdue access approvals')]
class EscalateApprovals extends Command
{
    public function handle(EscalateOverdueApprovals $escalate): int
    {
        $count = $escalate->handle();
        $this->components->info("{$count} overdue approval(s) escalated.");

        return self::SUCCESS;
    }
}
