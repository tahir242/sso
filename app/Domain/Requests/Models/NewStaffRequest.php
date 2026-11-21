<?php

namespace App\Domain\Requests\Models;

use App\Domain\Federation\Models\Application;
use App\Domain\Identity\Models\Department;
use App\Domain\Identity\Models\User;
use App\Domain\Requests\Enums\NewStaffStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * "Please create an account for this new member of staff" — submitted from
 * by a signed-in member of staff, confirmed by the head of department,
 * completed by the IT helpdesk.
 */
#[Fillable([
    'full_name', 'emp_id', 'email', 'mobile', 'department_id', 'designation', 'joining_date', 'application_ids',
    'justification', 'requester_ip', 'status', 'hod_id', 'hod_comment', 'hod_decided_at', 'created_user_id',
    'completed_by', 'completed_at',
])]
class NewStaffRequest extends Model
{
    use HasUlids, LogsActivity;

    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    protected function casts(): array
    {
        return [
            'joining_date' => 'date',
            'application_ids' => 'array',
            'status' => NewStaffStatus::class,
            'hod_decided_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function hod(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hod_id');
    }

    public function createdUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_user_id');
    }

    /**
     * @return Collection<int, Application>
     */
    public function requestedApplications(): Collection
    {
        return Application::query()->whereKey($this->application_ids ?? [])->orderBy('name')->get();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['status', 'hod_comment', 'created_user_id'])->logOnlyDirty()->dontLogEmptyChanges();
    }
}
