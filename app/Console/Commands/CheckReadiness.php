<?php

namespace App\Console\Commands;

use App\Support\Operations\ReadinessCheck;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('sso:check {--http : Also request the live site and check what it sends}')]
#[Description('Go-live readiness: configuration, database, keys, background jobs, integrations')]
class CheckReadiness extends Command
{
    public function handle(ReadinessCheck $check): int
    {
        $results = $check->run($this->option('http'));

        $this->table(['', 'Area', 'Check'], array_map(fn (array $r) => [
            match ($r[0]) {
                'pass' => '<fg=green>✔</>',
                'warn' => '<fg=yellow>!</>',
                default => '<fg=red>✘</>',
            },
            $r[1],
            $r[2],
        ], $results));

        $counts = array_count_values(array_column($results, 0)) + ['pass' => 0, 'warn' => 0, 'fail' => 0];
        $this->line("  {$counts['pass']} passed, {$counts['warn']} warnings, {$counts['fail']} failed");

        return $counts['fail'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
