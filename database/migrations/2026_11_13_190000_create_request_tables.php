<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Staff requests to IT: change requests (suggestions for an application)
 * and "new staff account" requests confirmed by the head of department.
 * FKs to users are NO ACTION (users are never deleted; SQL Server rules).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('change_requests', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users');
            $table->string('type', 20);
            $table->string('title', 150);
            $table->text('description');
            $table->string('priority', 20)->default('normal');
            $table->string('status', 20)->default('submitted')->index();
            $table->text('owner_comment')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users');
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });

        Schema::create('new_staff_requests', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('full_name', 150);
            $table->string('emp_id', 30)->nullable();
            $table->string('email', 191)->nullable();
            $table->string('mobile', 30)->nullable();
            $table->foreignId('department_id')->constrained();
            $table->string('designation', 150)->nullable();
            $table->date('joining_date')->nullable();
            $table->json('application_ids')->nullable();
            $table->text('justification')->nullable();
            $table->string('requester_ip', 45)->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('hod_id')->nullable()->constrained('users');
            $table->text('hod_comment')->nullable();
            $table->timestamp('hod_decided_at')->nullable();
            $table->foreignId('created_user_id')->nullable()->constrained('users');
            $table->foreignId('completed_by')->nullable()->constrained('users');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('new_staff_requests');
        Schema::dropIfExists('change_requests');
    }
};
