<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Built-in helpdesk: per-application categories, tickets with SLA due
 * dates, and a conversation thread (public replies, internal notes and
 * status events). FKs to users and applications are NO ACTION (users are
 * never deleted; SQL Server rejects multiple cascade paths).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_categories', function (Blueprint $table) {
            $table->id();
            // null = general IT category, offered for every application
            $table->foreignId('application_id')->nullable()->constrained();
            $table->string('name', 100);
            $table->string('description', 255)->nullable();
            $table->foreignId('default_assignee_id')->nullable()->constrained('users');
            $table->boolean('active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('application_id')->nullable()->constrained();
            $table->foreignId('category_id')->nullable()->constrained('ticket_categories');
            $table->foreignId('requester_id')->constrained('users');
            $table->foreignId('assignee_id')->nullable()->constrained('users');
            $table->string('subject', 150);
            $table->text('description');
            $table->string('priority', 20)->default('normal');
            $table->string('status', 20)->default('open')->index();
            $table->timestamp('response_due_at')->nullable();
            $table->timestamp('due_at')->nullable()->index();
            $table->timestamp('first_responded_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('breach_notified_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();

            $table->index(['requester_id', 'status']);
            $table->index(['assignee_id', 'status']);
        });

        Schema::create('ticket_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            // reply = seen by the requester; note = staff only; event = status/assignment change
            $table->string('kind', 10)->default('reply');
            $table->text('body');
            $table->timestamps();

            $table->index(['ticket_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_comments');
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('ticket_categories');
    }
};
