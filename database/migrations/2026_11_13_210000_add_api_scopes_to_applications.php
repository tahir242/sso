<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Which server-to-server API scopes each application may use. Existing
 * applications keep the one scope that existed before (users.read).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->json('api_scopes')->nullable();
        });

        DB::table('applications')->update(['api_scopes' => json_encode(['users.read'])]);
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn('api_scopes');
        });
    }
};
