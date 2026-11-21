<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * The software portfolio and application dashboards were removed. Earlier migrations no longer
 * create them; this one removes what databases that
 * ran the old migrations still have. On a fresh database it does nothing.
 *
 * - Drops portfolio_entries, release_notes, application_dashboards(+_role)
 *   and applications.is_platform.
 * - Deletes the portfolio's media records and files (logos, screenshots).
 * - Points records of the kept request workflows (change requests, new
 *   staff) at their new classes in App\Domain\Requests.
 *
 * Databases that ran the old migrations must first rename their migration
 * records (see docs/archive/portfolio/README.md, "Upgrading a database").
 *
 * Audit history (activity_log) is kept as it is: admin changes are retained.
 */
return new class extends Migration
{
    private const string PORTFOLIO_ENTRY = 'App\\Domain\\Portfolio\\Models\\PortfolioEntry';

    /** Classes moved from App\Domain\Portfolio to App\Domain\Requests. */
    private const array MOVED = [
        'App\\Domain\\Portfolio\\Models\\ChangeRequest' => 'App\\Domain\\Requests\\Models\\ChangeRequest',
        'App\\Domain\\Portfolio\\Models\\NewStaffRequest' => 'App\\Domain\\Requests\\Models\\NewStaffRequest',
        'App\\Domain\\Portfolio\\Notifications\\PortfolioNotification' => 'App\\Domain\\Requests\\Notifications\\RequestNotification',
    ];

    public function up(): void
    {
        // Portfolio media: records and their files on the private media disk.
        $mediaIds = DB::table('media')->where('model_type', self::PORTFOLIO_ENTRY)->pluck('id');
        foreach ($mediaIds as $id) {
            Storage::disk('media')->deleteDirectory((string) $id);
        }
        DB::table('media')->whereIn('id', $mediaIds)->delete();

        Schema::dropIfExists('application_dashboard_role');
        Schema::dropIfExists('application_dashboards');
        Schema::dropIfExists('release_notes');
        Schema::dropIfExists('portfolio_entries');

        if (Schema::hasColumn('applications', 'is_platform')) {
            Schema::table('applications', function (Blueprint $table) {
                $table->dropColumn('is_platform');
            });
        }

        foreach (self::MOVED as $from => $to) {
            DB::table('media')->where('model_type', $from)->update(['model_type' => $to]);
            DB::table('activity_log')->where('subject_type', $from)->update(['subject_type' => $to]);
            DB::table('notifications')->where('type', $from)->update(['type' => $to]);
        }
    }

    public function down(): void
    {
        // One-way: the portfolio lives on in docs/archive/portfolio (code, schema, content).
    }
};
