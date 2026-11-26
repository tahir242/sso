<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The browser session behind each application session, so applications can
 * be signed out when that SSO session expires (idle or absolute timeout), not
 * only when the user clicks "Sign out".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_sessions', function (Blueprint $table) {
            $table->string('session_id', 100)->nullable()->after('sid');
        });
    }

    public function down(): void
    {
        Schema::table('client_sessions', function (Blueprint $table) {
            $table->dropColumn('session_id');
        });
    }
};
