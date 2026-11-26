<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SQL Server does not index foreign-key columns by itself (MySQL does), so
 * every lookup by these columns scanned the whole table. Indexes every
 * foreign-key column that was not already the first column of an index, plus
 * the columns the scheduled jobs and the dashboard filter on.
 */
return new class extends Migration
{
    /** @var array<string, list<string>> */
    private const array INDEXES = [
        'access_requests' => ['application_id', 'requested_by'],
        'access_review_campaigns' => ['application_id', 'created_by'],
        'access_review_items' => ['application_id', 'decided_by', 'reviewer_id', 'user_id'],
        'application_role_user' => ['application_role_id'],
        'application_user' => ['granted_by', 'user_id'],
        'applications' => ['owner_user_id'],
        'approval_steps' => ['approver_id', 'decided_by'],
        'change_requests' => ['application_id', 'decided_by', 'requested_by'],
        'client_sessions' => ['oauth_client_id', 'user_id', 'logged_out_at'],
        'new_staff_requests' => ['completed_by', 'created_user_id', 'department_id', 'hod_id'],
        'role_has_permissions' => ['role_id'],
        'ticket_categories' => ['application_id', 'default_assignee_id'],
        'ticket_comments' => ['user_id'],
        'tickets' => ['application_id', 'category_id'],
        'trusted_devices' => ['user_id'],
        'users' => ['department_id', 'location_id', 'last_login_at'],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $table => $columns) {
            Schema::table($table, function (Blueprint $blueprint) use ($columns) {
                foreach ($columns as $column) {
                    $blueprint->index($column);
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $table => $columns) {
            Schema::table($table, function (Blueprint $blueprint) use ($columns) {
                foreach ($columns as $column) {
                    $blueprint->dropIndex([$column]);
                }
            });
        }
    }
};
