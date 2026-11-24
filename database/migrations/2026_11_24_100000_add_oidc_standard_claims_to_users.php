<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stores the OpenID Connect standard claims as real profile fields
 * (OpenID Connect Core 1.0, section 5.1) instead of deriving them from the
 * full name at sign-in time:
 *  - profile: given_name, middle_name, family_name, nickname
 *    (name, gender and birthdate already exist as name, gender, dob)
 *  - phone:   phone_number already exists as mobile
 *  - address: street_address, locality, region, postal_code, country
 *
 * emp_id and emp_initial are not touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('given_name', 100)->nullable();
            $table->string('middle_name', 100)->nullable();
            $table->string('family_name', 100)->nullable();
            $table->string('nickname', 100)->nullable();
            $table->string('street_address', 255)->nullable();
            $table->string('locality', 100)->nullable();
            $table->string('region', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('country', 100)->nullable();
        });

        // One-time starting point for existing accounts: first word is the
        // given name, the rest the family name (what was sent until now).
        // Administrators can correct each person on the user form.
        DB::table('users')->select(['id', 'name'])->orderBy('id')->chunkById(500, function ($users): void {
            foreach ($users as $user) {
                $parts = preg_split('/\s+/', trim((string) $user->name), 2) ?: [];

                DB::table('users')->where('id', $user->id)->update([
                    'given_name' => mb_substr(($parts[0] ?? '') ?: '', 0, 100) ?: null,
                    'family_name' => mb_substr(($parts[1] ?? '') ?: '', 0, 100) ?: null,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'given_name', 'middle_name', 'family_name', 'nickname',
                'street_address', 'locality', 'region', 'postal_code', 'country',
            ]);
        });
    }
};
