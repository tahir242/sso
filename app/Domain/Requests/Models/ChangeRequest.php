<?php

namespace App\Domain\Requests\Models;

use App\Domain\Federation\Models\Application;
use App\Domain\Identity\Models\User;
use App\Domain\Requests\Enums\ChangeRequestStatus;
use App\Domain\Requests\Enums\ChangeRequestType;
use App\Domain\Requests\Enums\Priority;
use App\Support\Media\ViewableMedia;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * A user's suggestion or problem report for an application, handled by the
 * application owner.
 */
#[Fillable(['application_id', 'requested_by', 'type', 'title', 'description', 'priority', 'status', 'owner_comment', 'decided_by', 'decided_at'])]
class ChangeRequest extends Model implements HasMedia, ViewableMedia
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
            'type' => ChangeRequestType::class,
            'priority' => Priority::class,
            'status' => ChangeRequestStatus::class,
            'decided_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('attachments')
            ->acceptsMimeTypes(['image/png', 'image/jpeg', 'image/webp', 'application/pdf']);
    }

    public function canViewMedia(?User $user): bool
    {
        return $user !== null && (
            $user->getKey() === $this->requested_by
            || $user->getKey() === $this->application?->owner_user_id
            || $user->hasAnyRole(['super-admin', 'helpdesk'])
        );
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['status', 'owner_comment', 'priority'])->logOnlyDirty()->dontLogEmptyChanges();
    }
}
