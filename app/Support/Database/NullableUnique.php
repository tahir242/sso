<?php

namespace App\Support\Database;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Unique index on a nullable column that allows many NULLs on every driver.
 *
 * MySQL, PostgreSQL and SQLite already treat NULLs as distinct in a unique
 * index. SQL Server does not (only one NULL is allowed), so there we create a
 * filtered index instead. This is the single, deliberate driver-specific
 * statement in the schema.
 */
final class NullableUnique
{
    public static function create(string $table, string $column): void
    {
        $index = "{$table}_{$column}_unique";

        if (DB::connection()->getDriverName() === 'sqlsrv') {
            DB::statement("CREATE UNIQUE INDEX [{$index}] ON [{$table}] ([{$column}]) WHERE [{$column}] IS NOT NULL");

            return;
        }

        Schema::table($table, fn ($blueprint) => $blueprint->unique($column, $index));
    }

    public static function drop(string $table, string $column): void
    {
        Schema::table($table, fn ($blueprint) => $blueprint->dropIndex("{$table}_{$column}_unique"));
    }
}
