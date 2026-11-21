<?php

namespace App\Domain\Access\Models;

use App\Domain\Access\Enums\AccessRequestStatus;
use App\Domain\Access\Enums\ApprovalStepStatus;
use App\Domain\Federation\Models\Application;
use App\Domain\Federation\Models\ApplicationRole;
use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * "Please give user X access to application Y" and its approval chain.
 */
#[Fillable(['application_id', 'user_id', 'requested_by', 'role_ids', 'justification', 'access_until', 'status', 'decided_at'])]
class AccessRequest extends Model
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
            'role_ids' => 'array',
            'access_until' => 'date',
            'status' => AccessRequestStatus::class,
            'decided_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /** The person who will get access. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(ApprovalStep::class)->orderBy('sequence');
    }

    public function scopePending(Builder $query): void
    {
        $query->where('status', AccessRequestStatus::Pending);
    }

    public function currentStep(): ?ApprovalStep
    {
        return $this->steps->firstWhere('status', ApprovalStepStatus::Pending);
    }

    /**
     * @return Collection<int, ApplicationRole>
     */
    public function requestedRoles(): Collection
    {
        return ApplicationRole::query()
            ->where('application_id', $this->application_id)
            ->whereKey($this->role_ids ?? [])
            ->orderBy('name')
            ->get();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['status', 'decided_at'])->logOnlyDirty()->dontLogEmptyChanges();
    }
}
