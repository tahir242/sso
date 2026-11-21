<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Access reviews (re-certification): application owners periodically confirm
 * that each user should still have access.
 *
 * Items keep a snapshot (user, application, roles) and reference the
 * assignment without a foreign key, so the evidence survives when access is
 * later removed. Only direct parent->child cascades (SQL Server rules).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_review_campaigns', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('name', 150);
            // null = every active application
            $table->foreignId('application_id')->nullable()->constrained();
            $table->timestamp('due_at');
            $table->boolean('auto_revoke')->default(false);
            $table->boolean('scheduled')->default(false);
            $table->string('status', 20)->default('open')->index();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('access_review_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('access_review_campaign_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('assignment_id')->index();
            $table->foreignId('application_id')->constrained();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('reviewer_id')->nullable()->constrained('users');
            $table->json('roles_snapshot')->nullable();
            $table->timestamp('access_since')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->string('decision', 20)->default('pending')->index();
            $table->foreignId('decided_by')->nullable()->constrained('users');
            $table->text('comment')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['access_review_campaign_id', 'decision']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_review_items');
        Schema::dropIfExists('access_review_campaigns');
    }
};
