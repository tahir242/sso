<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Self-service access requests with an approval chain
 * (HOD -> application owner -> optional IT security).
 *
 * FKs to users are NO ACTION (users are never deleted; SQL Server cascade rules).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            // {"skip_hod": bool, "require_it_security": bool}
            $table->json('approval_policy')->nullable();
        });

        Schema::create('access_requests', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('requested_by')->constrained('users');
            $table->json('role_ids')->nullable();
            $table->text('justification');
            $table->date('access_until')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'application_id', 'status']);
        });

        Schema::create('approval_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('access_request_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('sequence');
            $table->string('type', 20);
            // Named approver (HOD / owner); null = any user with the step's role.
            $table->foreignId('approver_id')->nullable()->constrained('users');
            $table->string('status', 20)->default('waiting')->index();
            $table->foreignId('decided_by')->nullable()->constrained('users');
            $table->text('comment')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('due_at')->nullable()->index();
            $table->timestamp('escalated_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->unique(['access_request_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_steps');
        Schema::dropIfExists('access_requests');

        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn('approval_policy');
        });
    }
};
