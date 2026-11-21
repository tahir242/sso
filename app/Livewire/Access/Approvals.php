<?php

namespace App\Livewire\Access;

use App\Domain\Access\Actions\DecideApprovalStep;
use App\Domain\Access\Models\ApprovalStep;
use App\Domain\Identity\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Inbox for approvers (heads of department, application owners, IT security).
 */
#[Layout('layouts::app')]
#[Title('Approvals')]
class Approvals extends Component
{
    /** @var array<int, string> step id => comment */
    public array $comments = [];

    public function approve(int $stepId, DecideApprovalStep $decide): void
    {
        $this->decide($stepId, true, $decide);
    }

    public function reject(int $stepId, DecideApprovalStep $decide): void
    {
        $this->decide($stepId, false, $decide);
    }

    private function decide(int $stepId, bool $approve, DecideApprovalStep $decide): void
    {
        $step = ApprovalStep::with('request')->findOrFail($stepId);
        $this->resetErrorBag();

        try {
            $decide->handle($step, $this->user(), $approve, $this->comments[$stepId] ?? null);
        } catch (ValidationException $e) {
            $this->addError("comments.{$stepId}", $e->validator->errors()->first());

            return;
        }

        unset($this->comments[$stepId]);
        session()->flash('status', $approve ? __('Approved.') : __('Rejected. The requester has been told why.'));
    }

    public function render(): View
    {
        $user = $this->user();

        return view('livewire.access.approvals', [
            'waiting' => ApprovalStep::query()->awaiting($user)
                ->with(['request.application', 'request.user.department', 'request.requester', 'request.steps.approver', 'request.steps.decider'])
                ->orderBy('due_at')
                ->get(),
            'recent' => ApprovalStep::query()->where('decided_by', $user->getKey())
                ->with(['request.application', 'request.user'])
                ->latest('decided_at')->limit(10)->get(),
        ]);
    }

    private function user(): User
    {
        /** @var User */
        return auth()->user();
    }
}
