<?php

namespace App\Support\Operations;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\Finder\Finder;
use ZipArchive;

/**
 * Backs up what a database backup alone does not cover, and the database.
 *
 * Archive (AES-256 encrypted zip):
 *   keys/oauth-private.key, keys/oauth-public.key  token signing keys
 *   env/.env                                       configuration and APP_KEY (decrypts stored secrets)
 *   files/...                                      uploads (storage/app/private), except temporary folders
 *   database/sso.sqlite                            SQLite only
 *   manifest.json                                  what is inside, with checksums
 *
 * SQL Server: BACKUP DATABASE ... WITH COPY_ONLY, CHECKSUM to the configured
 * folder on the database server, then RESTORE VERIFYONLY.
 */
final class Backup
{
    /** Folders under storage/app/private that are regenerated or temporary. */
    private const SKIP = ['kits', 'livewire-tmp', 'backups'];

    /**
     * @return array{archive: string, entries: int, database: ?string}
     */
    public function create(): array
    {
        $password = (string) config('backup.password');
        if (strlen($password) < 16) {
            throw new RuntimeException('Set SSO_BACKUP_PASSWORD (at least 16 characters) before backing up.');
        }

        $dir = rtrim((string) config('backup.path'), '/\\');
        if (! is_dir($dir) && ! @mkdir($dir, 0700, true)) {
            throw new RuntimeException("Cannot create the backup folder {$dir}.");
        }

        $stamp = now()->format('Ymd-His');
        $archive = $dir.DIRECTORY_SEPARATOR."sso-backup-{$stamp}.zip";
        $manifest = ['created_at' => now()->toIso8601String(), 'app_url' => config('app.url'), 'driver' => DB::getDriverName(), 'files' => []];

        $zip = new ZipArchive;
        if ($zip->open($archive, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
            throw new RuntimeException("Cannot write {$archive}.");
        }

        $add = function (string $source, string $name) use ($zip, $password, &$manifest): void {
            $zip->addFile($source, $name);
            $zip->setEncryptionName($name, ZipArchive::EM_AES_256, $password);
            $manifest['files'][$name] = hash_file('sha256', $source);
        };

        foreach (['oauth-private.key', 'oauth-public.key'] as $key) {
            is_file(storage_path($key)) ? $add(storage_path($key), "keys/{$key}") : throw new RuntimeException("Missing storage/{$key}.");
        }

        if (is_file(base_path('.env'))) {
            $add(base_path('.env'), 'env/.env');
        }

        $private = storage_path('app/private');
        if (is_dir($private)) {
            $files = Finder::create()->files()->ignoreDotFiles(false)->in($private)->exclude(self::SKIP);
            foreach ($files as $file) {
                $add($file->getRealPath(), 'files/'.str_replace('\\', '/', $file->getRelativePathname()));
            }
        }

        $database = null;
        if (DB::getDriverName() === 'sqlite' && is_file((string) DB::connection()->getDatabaseName())) {
            $copy = $dir.DIRECTORY_SEPARATOR."db-{$stamp}.sqlite";
            DB::statement('VACUUM INTO '.DB::getPdo()->quote($copy));
            $add($copy, 'database/sso.sqlite');
        } elseif (DB::getDriverName() === 'sqlsrv' && filled(config('backup.sqlserver_path'))) {
            $database = $this->backupSqlServer($stamp);
        }

        $zip->addFromString('manifest.json', json_encode($manifest + ['sqlserver_backup' => $database], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $zip->setEncryptionName('manifest.json', ZipArchive::EM_AES_256, $password);
        $entries = $zip->numFiles;
        $zip->close();

        if (isset($copy)) {
            @unlink($copy);
        }

        $this->prune($dir);

        return ['archive' => $archive, 'entries' => $entries, 'database' => $database];
    }

    /**
     * Restore drill: opens an archive with the password, checks every file
     * against the manifest, and checks the signing key pair still matches.
     *
     * @return array{entries: int, created_at: string, problems: array<int, string>}
     */
    public function verify(string $archive): array
    {
        $zip = new ZipArchive;
        if ($zip->open($archive, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException("Cannot open {$archive}.");
        }
        $zip->setPassword((string) config('backup.password'));

        $manifest = json_decode((string) $zip->getFromName('manifest.json'), true);
        if (! is_array($manifest)) {
            throw new RuntimeException('Cannot read the manifest: wrong SSO_BACKUP_PASSWORD, or not an SSO backup.');
        }

        $problems = [];
        foreach ($manifest['files'] as $name => $sha) {
            $content = $zip->getFromName($name);
            if ($content === false || hash('sha256', $content) !== $sha) {
                $problems[] = "{$name} is missing or damaged";
            }
        }

        $private = openssl_pkey_get_private((string) $zip->getFromName('keys/oauth-private.key'));
        // Compare without whitespace: the stored file may have Windows line endings.
        $normalise = fn (string $pem) => preg_replace('/\s+/', '', $pem);
        $public = $normalise((string) $zip->getFromName('keys/oauth-public.key'));
        if (! $private || $normalise((string) (openssl_pkey_get_details($private)['key'] ?? '')) !== $public) {
            $problems[] = 'The signing key pair does not match';
        }

        $zip->close();

        if (($manifest['sqlserver_backup'] ?? null) && DB::getDriverName() === 'sqlsrv') {
            try {
                DB::statement('RESTORE VERIFYONLY FROM DISK = ? WITH CHECKSUM', [$manifest['sqlserver_backup']]);
            } catch (\Throwable $e) {
                $problems[] = 'SQL Server backup did not verify: '.$e->getMessage();
            }
        }

        return ['entries' => count($manifest['files']), 'created_at' => $manifest['created_at'], 'problems' => $problems];
    }

    private function backupSqlServer(string $stamp): string
    {
        $database = DB::connection()->getDatabaseName();
        $file = rtrim((string) config('backup.sqlserver_path'), '/\\')."\\{$database}-{$stamp}.bak";

        // COPY_ONLY: does not disturb the DBAs' own backup chain.
        DB::unprepared('BACKUP DATABASE '.DB::getQueryGrammar()->wrap($database).' TO DISK = N'.DB::getPdo()->quote($file).' WITH COPY_ONLY, CHECKSUM, INIT');
        DB::statement('RESTORE VERIFYONLY FROM DISK = ? WITH CHECKSUM', [$file]);

        return $file;
    }

    private function prune(string $dir): void
    {
        $archives = glob($dir.DIRECTORY_SEPARATOR.'sso-backup-*.zip') ?: [];
        rsort($archives);

        foreach (array_slice($archives, max(1, (int) config('backup.keep'))) as $old) {
            @unlink($old);
        }
    }
}
