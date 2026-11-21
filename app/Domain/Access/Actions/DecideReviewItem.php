<?php

namespace App\Domain\Access\Actions;

use App\Domain\Access\Enums\ReviewDecision;
use App\Domain\Access\Models\AccessReviewItem;
use App\Domain\Access\Notifications\AccessRemovedByReview;
use App\Domain\Identity\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * A reviewer confirms ("keep") or removes one person's access.
 * Removing deactivates the assignment, which also revokes the person's
 * tokens for that application.
 */
final class DecideReviewItem
{
    /**
     * @throws AuthorizationException
     */
    public function handle(AccessReviewItem $item, ?User $reviewer, bool $keep, ?string $comment = null): void
    {
        if ($reviewer && ! $item->canBeDecidedBy($reviewer)) {
            throw new AuthorizationException(__('You cannot review this access.'));
        }

        $item->forceFill([
            'decision' => $keep ? ReviewDecision::Keep : ReviewDecision::Revoke,
            'decided_by' => $reviewer?->getKey(),
            'comment' => trim((string) $comment) ?: null,
            'decided_at' => now(),
        ])->save();

        if (! $keep) {
            $this->revoke($item);
        }
    }

    public function revoke(AccessReviewItem $item): void
    {
        $assignment = $item->assignment;

        if ($assignment?->isCurrent()) {
            $assignment->update(['active' => false]);
            $item->user->notify(new AccessRemovedByReview($item));
        }
    }
}
