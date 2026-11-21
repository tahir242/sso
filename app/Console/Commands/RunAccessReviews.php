<?php

namespace App\Console\Commands;

use App\Domain\Access\Actions\RunAccessReviewSchedule;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('access:reviews')]
#[Description('Close overdue access reviews, remind reviewers and start scheduled reviews')]
class RunAccessReviews extends Command
{
    public function handle(RunAccessReviewSchedule $schedule): int
    {
        $result = $schedule->handle();

        $this->components->info(sprintf(
            '%d review(s) closed, %d reminder(s) sent%s.',
            $result['closed'],
            $result['reminded'],
            $result['started'] ? ', scheduled review started' : '',
        ));

        return self::SUCCESS;
    }
}
