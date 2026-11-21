<?php

namespace App\Console\Commands;

use App\Domain\Helpdesk\Actions\ProcessHelpdeskSla;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('helpdesk:sla')]
#[Description('Alert about overdue tickets and close resolved tickets nobody replied to')]
class ProcessHelpdesk extends Command
{
    public function handle(ProcessHelpdeskSla $sla): int
    {
        ['overdue' => $overdue, 'closed' => $closed] = $sla->handle();
        $this->components->info("{$overdue} overdue ticket(s) alerted, {$closed} resolved ticket(s) closed.");

        return self::SUCCESS;
    }
}
