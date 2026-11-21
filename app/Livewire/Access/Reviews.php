<?php

namespace App\Livewire\Access;

use App\Domain\Access\Actions\DecideReviewItem;
use App\Domain\Access\Models\AccessReviewItem;
use App\Domain\Identity\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Access review inbox: "should these people keep access to your application?"
 */
#[Layout('layouts::app')]
#[Title('Access reviews')]
class Reviews extends Component
{
    /** @var array<int, string> item id => reason for removal */
    public array $reasons = [];

    public ?int $removing = null;

    public function keep(int $itemId, DecideReviewItem $decide): void
    {
        $decide->handle($this->item($itemId), $this->user(), keep: true);
    }

    public function startRemove(int $itemId): void
    {
        $this->removing = $itemId;
    }

    public function confirmRemove(int $itemId, DecideReviewItem $decide): void
    {
        $decide->handle($this->item($itemId), $this->user(), keep: false, comment: $this->reasons[$itemId] ?? null);
        $this->removing = null;
        unset($this->reasons[$itemId]);
    }

    /**
     * Confirm everyone still pending for one application in one review.
     */
    public function keepAll(int $campaignId, int $applicationId, DecideReviewItem $decide): void
    {
        AccessReviewItem::query()->awaiting($this->user())
            ->where('access_review_campaign_id', $campaignId)
            ->where('application_id', $applicationId)
            ->with('campaign')
            ->get()
            ->each(fn (AccessReviewItem $item) => $decide->handle($item, $this->user(), keep: true));

        session()->flash('status', __('Access confirmed for everyone remaining.'));
    }

    public function render(): View
    {
        $items = AccessReviewItem::query()->awaiting($this->user())
            ->with(['campaign', 'application', 'user.department'])
            ->orderBy('application_id')
            ->get();

        return view('livewire.access.reviews', [
            'groups' => $items->groupBy(fn (AccessReviewItem $i) => $i->access_review_campaign_id.'-'.$i->application_id),
            'decided' => AccessReviewItem::query()->where('decided_by', $this->user()->getKey())
                ->with(['application', 'user'])->latest('decided_at')->limit(10)->get(),
        ]);
    }

    private function item(int $id): AccessReviewItem
    {
        return AccessReviewItem::with(['campaign', 'assignment', 'user', 'application'])->findOrFail($id);
    }

    private function user(): User
    {
        /** @var User */
        return auth()->user();
    }
}
