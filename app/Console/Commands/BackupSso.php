<?php

namespace App\Console\Commands;

use App\Support\Operations\Backup;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('sso:backup {--verify= : Check an existing archive instead (restore drill)}')]
#[Description('Encrypted backup of signing keys, configuration and uploads (and the database on SQL Server)')]
class BackupSso extends Command
{
    public function handle(Backup $backup): int
    {
        try {
            if ($archive = $this->option('verify')) {
                $result = $backup->verify($archive);
                $this->components->twoColumnDetail('Archive from', $result['created_at']);
                $this->components->twoColumnDetail('Files checked', (string) $result['entries']);

                foreach ($result['problems'] as $problem) {
                    $this->components->error($problem);
                }

                $result['problems'] === []
                    ? $this->components->info('Backup is complete and readable.')
                    : $this->components->error('Backup has problems.');

                return $result['problems'] === [] ? self::SUCCESS : self::FAILURE;
            }

            $result = $backup->create();
        } catch (Throwable $e) {
            $this->components->error($e->getMessage());
            report($e);

            return self::FAILURE;
        }

        $this->components->twoColumnDetail('Archive', $result['archive']);
        $this->components->twoColumnDetail('Files', (string) $result['entries']);
        $this->components->twoColumnDetail('SQL Server backup', $result['database'] ?? 'not made here');
        $this->components->info('Backup finished.');

        return self::SUCCESS;
    }
}
