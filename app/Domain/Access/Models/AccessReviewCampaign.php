<?php

namespace App\Domain\Access\Models;

use App\Domain\Access\Enums\ReviewDecision;
use App\Domain\Federation\Models\Application;
use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * One access review: a snapshot of who has access, for owners to confirm.
 */
#[Fillable(['name', 'application_id', 'due_at', 'auto_revoke', 'scheduled', 'status', 'created_by', 'closed_at'])]
class AccessReviewCampaign extends Model
{
    use HasUlids, LogsActivity;

    public const string OPEN = 'open';

    public const string CLOSED = 'closed';

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
            'due_at' => 'datetime',
            'closed_at' => 'datetime',
            'auto_revoke' => 'boolean',
            'scheduled' => 'boolean',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(AccessReviewItem::class);
    }

    public function scopeOpen(Builder $query): void
    {
        $query->where('status', self::OPEN);
    }

    public function isOpen(): bool
    {
        return $this->status === self::OPEN;
    }

    /**
     * @return array{total: int, done: int, kept: int, removed: int, percent: int}
     */
    public function progress(): array
    {
        $counts = $this->items()->toBase()->selectRaw('decision, count(*) as total')->groupBy('decision')->pluck('total', 'decision');
        $total = (int) $counts->sum();
        $done = $total - (int) ($counts[ReviewDecision::Pending->value] ?? 0);

        return [
            'total' => $total,
            'done' => $done,
            'kept' => (int) ($counts[ReviewDecision::Keep->value] ?? 0),
            'removed' => (int) ($counts[ReviewDecision::Revoke->value] ?? 0),
            'percent' => $total ? (int) floor($done * 100 / $total) : 100,
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['name', 'status', 'due_at', 'closed_at'])->logOnlyDirty()->dontLogEmptyChanges();
    }
}
