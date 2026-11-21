<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Client applications, their roles and user assignments.
 *
 * SQL Server rejects multiple cascade paths, so only direct parent->child
 * cascades are used. Users are never deleted (they are disabled), so FKs to
 * users use NO ACTION.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 60)->unique();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->string('base_url', 500);
            $table->char('oauth_client_id', 36)->unique(); // see oauth_clients.id
            $table->foreign('oauth_client_id')->references('id')->on('oauth_clients');
            $table->json('post_logout_redirect_uris')->nullable();
            $table->string('backchannel_logout_uri', 500)->nullable();
            $table->boolean('requires_mfa')->default(false);
            $table->foreignId('owner_user_id')->nullable()->constrained('users');
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('application_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->string('key', 60);
            $table->string('name', 120);
            $table->string('description', 255)->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['application_id', 'key']);
        });

        Schema::create('application_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('granted_by')->nullable()->constrained('users');
            $table->timestamp('expires_at')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['application_id', 'user_id']);
        });

        Schema::create('application_role_user', function (Blueprint $table) {
            $table->foreignId('application_user_id')->constrained('application_user')->cascadeOnDelete();
            // NO ACTION: rows are removed by the application_user cascade above.
            $table->foreignId('application_role_id')->constrained('application_roles');

            $table->primary(['application_user_id', 'application_role_id']);
        });

        // One row per (SSO session, client): drives `sid`, `auth_time`, `amr`
        // in ID tokens and back-channel logout fan-out.
        Schema::create('client_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('sid', 64);
            $table->foreignId('user_id')->constrained('users');
            $table->char('oauth_client_id', 36); // see oauth_clients.id
            $table->foreign('oauth_client_id')->references('id')->on('oauth_clients')->cascadeOnDelete();
            $table->timestamp('auth_time');
            $table->json('amr');
            $table->timestamp('last_authorized_at');
            $table->timestamp('logged_out_at')->nullable();
            $table->timestamps();

            $table->unique(['sid', 'oauth_client_id']);
        });

        Schema::table('oauth_auth_codes', function (Blueprint $table) {
            $table->string('nonce', 255)->nullable();
            $table->unsignedBigInteger('client_session_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('oauth_auth_codes', function (Blueprint $table) {
            $table->dropColumn(['nonce', 'client_session_id']);
        });

        Schema::dropIfExists('client_sessions');
        Schema::dropIfExists('application_role_user');
        Schema::dropIfExists('application_user');
        Schema::dropIfExists('application_roles');
        Schema::dropIfExists('applications');
    }
};
