<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * - Location dictionary (city > building > floor > room > workstation) and
 *   each user's location.
 * - Who may open an application: everyone, or assigned users only.
 *
 * Self-references and second paths to a table are NO ACTION (SQL Server
 * rejects multiple cascade paths); the models clean up instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('locations');
            $table->string('type', 20)->index();
            $table->string('name', 120);
            $table->string('code', 30)->nullable();
            $table->string('description', 255)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['parent_id', 'sort_order']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('location_id')->nullable()->after('department_id')->constrained('locations');
        });

        Schema::table('applications', function (Blueprint $table) {
            // everyone = any active account may sign in; assigned = per-user access
            $table->string('access_mode', 20)->default('assigned');
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn('access_mode');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('location_id');
        });

        Schema::dropIfExists('locations');
    }
};
