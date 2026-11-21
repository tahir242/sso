<?php

/*
| Nightly backup (`php artisan sso:backup`, scheduled at 01:30).
|
| These live in .env rather than in the admin settings on purpose: the
| settings are inside the database being backed up, and the password must
| be available when that database is gone.
*/

return [
    // Folder for the encrypted archives. Prefer a network share or a disk other than the server's.
    'path' => env('SSO_BACKUP_PATH', storage_path('app/backups')),

    // Encrypts each archive (AES-256). Store it in the password safe too: without it a backup is useless.
    'password' => env('SSO_BACKUP_PASSWORD'),

    // How many archives to keep.
    'keep' => (int) env('SSO_BACKUP_KEEP', 14),

    // SQL Server only: folder, as seen by the SQL Server machine, where it writes
    // the database backup (BACKUP DATABASE ... TO DISK). Leave empty if the DBAs
    // already back the database up.
    'sqlserver_path' => env('SSO_BACKUP_SQLSERVER_PATH'),
];
