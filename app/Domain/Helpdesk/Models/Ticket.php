<?php

namespace App\Domain\Helpdesk\Models;

use App\Domain\Federation\Models\Application;
use App\Domain\Helpdesk\Enums\CommentKind;
use App\Domain\Helpdesk\Enums\TicketPriority;
use App\Domain\Helpdesk\Enums\TicketStatus;
use App\Domain\Helpdesk\Support\Helpdesk;
use App\Domain\Identity\Models\User;
use App\Support\Media\ViewableMedia;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * A support ticket. Attachments are stored on the ticket; each file records
 * the comment it came with (custom property comment_id, null = original).
 */
#[Fillable([
    'application_id', 'category_id', 'requester_id', 'assignee_id', 'subject', 'description', 'priority', 'status',
    'response_due_at', 'due_at', 'first_responded_at', 'resolved_at', 'closed_at', 'breach_notified_at', 'last_activity_at',
])]
class Ticket extends Model implements HasMedia, ViewableMedia
{
    use HasUlids, InteractsWithMedia, LogsActivity;

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
            'priority' => TicketPriority::class,
            'status' => TicketStatus::class,
            'response_due_at' => 'datetime',
            'due_at' => 'datetime',
            'first_responded_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
            'breach_notified_at' => 'datetime',
            'last_activity_at' => 'datetime',
        ];
    }

    /** Human reference, e.g. HD-00042. */
    protected function reference(): Attribute
    {
        return Attribute::get(fn () => 'HD-'.str_pad((string) $this->getKey(), 5, '0', STR_PAD_LEFT));
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TicketCategory::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TicketComment::class)->orderBy('created_at')->orderBy('id');
    }

    public function publicComments(): HasMany
    {
        return $this->comments()->where('kind', '!=', CommentKind::Note);
    }

    public function scopeActive(Builder $query): void
    {
        $query->whereIn('status', TicketStatus::active());
    }

    public function scopeOverdue(Builder $query): void
    {
        $query->active()->where('due_at', '<', now());
    }

    /**
     * Tickets this person handles: everything for helpdesk staff, otherwise
     * their applications' tickets and anything assigned to them.
     */
    public function scopeHandledBy(Builder $query, User $user): void
    {
        if ($user->hasAnyRole(Helpdesk::AGENT_ROLES)) {
            return;
        }

        $query->where(fn (Builder $q) => $q
            ->where('assignee_id', $user->getKey())
            ->orWhereHas('application', fn (Builder $a) => $a->where('owner_user_id', $user->getKey())));
    }

    public function isOverdue(): bool
    {
        return $this->status->isActive() && $this->due_at?->isPast();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('attachments')
            ->acceptsMimeTypes(['image/png', 'image/jpeg', 'image/webp', 'application/pdf']);
    }

    public function canViewMedia(?User $user): bool
    {
        return $user !== null && Helpdesk::canView($this, $user);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['status', 'priority', 'assignee_id', 'category_id'])->logOnlyDirty()->dontLogEmptyChanges();
    }
}
