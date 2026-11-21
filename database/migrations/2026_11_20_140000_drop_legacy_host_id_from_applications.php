<?php

use App\Support\Database\NullableUnique;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The one-time import from the old SSO is finished and its code removed.
 * Databases that ran it still have applications.legacy_host_id (it linked an
 * application to the old SSO's Host row); this drops it. On a fresh database
 * the column never existed and this does nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('applications', 'legacy_host_id')) {
            return;
        }

        NullableUnique::drop('applications', 'legacy_host_id');

        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn('legacy_host_id');
        });
    }

    public function down(): void
    {
        // Not restored: nothing reads it any more.
    }
};
