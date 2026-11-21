<?php

namespace App\Livewire\Access;

use App\Domain\Identity\Models\User;
use App\Domain\Requests\Actions\DecideNewStaffRequest;
use App\Domain\Requests\Enums\NewStaffStatus;
use App\Domain\Requests\Models\NewStaffRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Heads of department confirm that new staff really are joining.
 */
#[Layout('layouts::app')]
#[Title('New staff')]
class NewStaffApprovals extends Component
{
    /** @var array<int, string> */
    public array $comments = [];

    public function decide(int $id, bool $approve, DecideNewStaffRequest $decide): void
    {
        $this->resetErrorBag();

        try {
            $decide->handle(NewStaffRequest::with('department')->findOrFail($id), $this->user(), $approve, $this->comments[$id] ?? null);
        } catch (ValidationException $e) {
            $this->addError("comments.{$id}", $e->validator->errors()->first());

            return;
        }

        session()->flash('status', $approve ? __('Confirmed. The IT helpdesk will create the account.') : __('Rejected.'));
    }

    public function render(): View
    {
        return view('livewire.access.new-staff-approvals', [
            'pending' => self::awaiting($this->user())->with(['department'])->latest()->get(),
        ]);
    }

    /**
     * Pending requests for departments this user heads (super-admins also see
     * departments without a head).
     */
    public static function awaiting(User $user): Builder
    {
        return NewStaffRequest::query()
            ->where('status', NewStaffStatus::Pending)
            ->whereHas('department', function (Builder $q) use ($user) {
                $q->where('hod_user_id', $user->getKey());

                if ($user->hasRole('super-admin')) {
                    $q->orWhereNull('hod_user_id');
                }
            });
    }

    private function user(): User
    {
        /** @var User */
        return auth()->user();
    }
}
