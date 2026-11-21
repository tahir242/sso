<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The browser fingerprint (hash of the long-lived device cookie) gets its own
 * indexed column. Looking it up inside the JSON `meta` column needs JSON
 * functions that SQL Server 2014 doesn't have, and can't use an index.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auth_events', function (Blueprint $table) {
            $table->string('device_hash', 64)->nullable();
            $table->index(['user_id', 'device_hash']);
        });

        // Backfill in PHP: portable across databases.
        DB::table('auth_events')->whereNotNull('meta')->orderBy('id')->chunkById(500, function ($events) {
            foreach ($events as $event) {
                $device = json_decode((string) $event->meta, true)['device'] ?? null;

                if (is_string($device) && strlen($device) === 64) {
                    DB::table('auth_events')->where('id', $event->id)->update(['device_hash' => $device]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('auth_events', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'device_hash']);
            $table->dropColumn('device_hash');
        });
    }
};
