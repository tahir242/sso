<?php

namespace App\Domain\Access\Actions;

use App\Domain\Access\Enums\ReviewDecision;
use App\Domain\Access\Models\AccessReviewCampaign;
use App\Domain\Access\Models\AccessReviewItem;
use App\Domain\Access\Notifications\AccessReviewReminder;
use App\Domain\Federation\Models\Application;
use App\Domain\Identity\Models\User;
use App\Domain\Settings\AccessSettings;
use Illuminate\Support\Facades\Cache;

/**
 * Daily housekeeping for access reviews:
 *  1. close reviews past their deadline,
 *  2. remind reviewers in the last three days (once a day),
 *  3. start the next scheduled review when it is due.
 */
final readonly class RunAccessReviewSchedule
{
    public function __construct(
        private CloseAccessReview $close,
        private StartAccessReview $start,
        private AccessSettings $settings,
    ) {}

    /**
     * @return array{closed: int, reminded: int, started: bool}
     */
    public function handle(): array
    {
        $closed = 0;
        foreach (AccessReviewCampaign::open()->where('due_at', '<', now())->get() as $campaign) {
            $this->close->handle($campaign);
            $closed++;
        }

        return ['closed' => $closed, 'reminded' => $this->remind(), 'started' => $this->startScheduled()];
    }

    private function remind(): int
    {
        $reminded = 0;

        $rows = AccessReviewItem::query()
            ->where('decision', ReviewDecision::Pending)
            ->whereNotNull('reviewer_id')
            ->whereHas('campaign', fn ($q) => $q->open()->where('due_at', '<=', now()->addDays(3)))
            ->toBase()
            ->selectRaw('access_review_campaign_id, reviewer_id, count(*) as total')
            ->groupBy('access_review_campaign_id', 'reviewer_id')
            ->get();

        foreach ($rows as $row) {
            if (! Cache::add("review-reminder:{$row->access_review_campaign_id}:{$row->reviewer_id}:".now()->toDateString(), true, now()->endOfDay())) {
                continue;
            }

            User::find($row->reviewer_id)?->notify(new AccessReviewReminder(AccessReviewCampaign::find($row->access_review_campaign_id), (int) $row->total));
            $reminded++;
        }

        return $reminded;
    }

    private function startScheduled(): bool
    {
        if ($this->settings->review_interval_months <= 0) {
            return false;
        }

        // The first scheduled review is one interval after the first application was registered.
        $since = AccessReviewCampaign::query()->where('scheduled', true)->max('created_at')
            ?? Application::query()->min('created_at');

        if (! $since || now()->parse($since)->addMonths($this->settings->review_interval_months)->isFuture()) {
            return false;
        }

        $this->start->handle(
            __('Access review :period', ['period' => now()->format('F Y')]),
            null,
            now()->addDays($this->settings->review_duration_days)->endOfDay()->startOfSecond(), // see AdvanceAccessRequest
            $this->settings->review_auto_revoke,
            scheduled: true,
        );

        return true;
    }
}
