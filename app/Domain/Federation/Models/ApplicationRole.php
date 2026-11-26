<?php

namespace App\Domain\Federation\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A coarse role inside one application (e.g. hr:clerk). Sent to the app in
 * the `roles` claim; the app maps it to its own fine-grained permissions.
 */
#[Fillable(['application_id', 'key', 'name', 'description', 'is_default'])]
class ApplicationRole extends Model
{
    use LogsActivity;

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    protected static function booted(): void
    {
        // The pivot FK is NO ACTION (SQL Server cascade-path rules).
        static::deleting(function (self $role): void {
            DB::table('application_role_user')->where('application_role_id', $role->getKey())->delete();
        });
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /** Assignments (user access) that include this role. */
    public function assignments(): BelongsToMany
    {
        return $this->belongsToMany(ApplicationAssignment::class, 'application_role_user', 'application_role_id', 'application_user_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }
}
