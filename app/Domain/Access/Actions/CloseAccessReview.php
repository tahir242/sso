<?php

namespace App\Domain\Access\Actions;

use App\Domain\Access\Enums\ReviewDecision;
use App\Domain\Access\Models\AccessReviewCampaign;
use App\Domain\Identity\Models\User;

/**
 * Ends a review. Unreviewed access is removed when the review was set to
 * auto-revoke; otherwise it is marked "not reviewed in time" and kept.
 */
final readonly class CloseAccessReview
{
    public function __construct(private DecideReviewItem $decide) {}

    public function handle(AccessReviewCampaign $campaign, ?User $closedBy = null): void
    {
        if (! $campaign->isOpen()) {
            return;
        }

        $pending = $campaign->items()->where('decision', ReviewDecision::Pending)->with(['assignment', 'user'])->get();

        foreach ($pending as $item) {
            if ($campaign->auto_revoke) {
                $item->forceFill([
                    'decision' => ReviewDecision::Revoke,
                    'comment' => __('Not confirmed before the review deadline.'),
                    'decided_at' => now(),
                ])->save();
                $this->decide->revoke($item);
            } else {
                $item->forceFill(['decision' => ReviewDecision::Expired, 'decided_at' => now()])->save();
            }
        }

        $campaign->forceFill(['status' => AccessReviewCampaign::CLOSED, 'closed_at' => now()])->save();

        activity()->performedOn($campaign)->causedBy($closedBy)->log($closedBy ? 'review closed' : 'review closed automatically');
    }
}
