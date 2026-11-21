<?php

namespace App\Domain\Helpdesk\Models;

use App\Domain\Federation\Models\Application;
use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * What a ticket is about, e.g. "Printing" or "Login problem". Categories
 * without an application are general IT categories offered everywhere.
 */
#[Fillable(['application_id', 'name', 'description', 'default_assignee_id', 'active', 'sort_order'])]
class TicketCategory extends Model
{
    use LogsActivity;

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function defaultAssignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'default_assignee_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'category_id');
    }

    /**
     * Active categories offered for this application (its own plus general ones).
     */
    public function scopeOfferedFor(Builder $query, ?int $applicationId): void
    {
        $query->where('active', true)
            ->where(fn (Builder $q) => $q->whereNull('application_id')
                ->when($applicationId, fn (Builder $q) => $q->orWhere('application_id', $applicationId)))
            ->orderByRaw('case when application_id is null then 1 else 0 end')
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }
}
