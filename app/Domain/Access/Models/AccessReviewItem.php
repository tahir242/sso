<?php

namespace App\Domain\Access\Models;

use App\Domain\Access\Enums\ReviewDecision;
use App\Domain\Federation\Models\Application;
use App\Domain\Federation\Models\ApplicationAssignment;
use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * "Should :user keep access to :application?" within one review.
 */
#[Fillable([
    'access_review_campaign_id', 'assignment_id', 'application_id', 'user_id', 'reviewer_id', 'roles_snapshot',
    'access_since', 'last_used_at', 'decision', 'decided_by', 'comment', 'decided_at',
])]
class AccessReviewItem extends Model
{
    protected function casts(): array
    {
        return [
            'roles_snapshot' => 'array',
            'access_since' => 'datetime',
            'last_used_at' => 'datetime',
            'decision' => ReviewDecision::class,
            'decided_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(AccessReviewCampaign::class, 'access_review_campaign_id');
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(ApplicationAssignment::class, 'assignment_id');
    }

    /**
     * Pending items in open reviews that the user should decide: their own
     * applications, or (for super-admins) applications without an owner.
     */
    public function scopeAwaiting(Builder $query, User $user): void
    {
        $query->where('decision', ReviewDecision::Pending)
            ->where('user_id', '!=', $user->getKey())
            ->whereHas('campaign', fn (Builder $q) => $q->open())
            ->where(function (Builder $q) use ($user) {
                $q->where('reviewer_id', $user->getKey());

                if ($user->hasRole('super-admin')) {
                    $q->orWhereNull('reviewer_id');
                }
            });
    }

    public function canBeDecidedBy(User $user): bool
    {
        return $this->decision === ReviewDecision::Pending
            && $this->campaign->isOpen()
            && $this->user_id !== $user->getKey()
            && ($this->reviewer_id === $user->getKey() || $user->hasRole('super-admin'));
    }
}
