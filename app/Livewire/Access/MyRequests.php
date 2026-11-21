<?php

namespace App\Livewire\Access;

use App\Domain\Access\Actions\CancelAccessRequest;
use App\Domain\Access\Models\AccessRequest;
use App\Domain\Requests\Models\ChangeRequest;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts::app')]
#[Title('My requests')]
class MyRequests extends Component
{
    /** access | changes */
    #[Url(except: 'access')]
    public string $tab = 'access';

    public function cancel(string $ulid, CancelAccessRequest $cancel): void
    {
        $cancel->handle(AccessRequest::where('ulid', $ulid)->firstOrFail(), auth()->user());
    }

    public function render(): View
    {
        return view('livewire.access.my-requests', [
            'requests' => $this->tab === 'access'
                ? AccessRequest::query()
                    ->where(fn ($q) => $q->where('user_id', auth()->id())->orWhere('requested_by', auth()->id()))
                    ->with(['application', 'user', 'steps.approver', 'steps.decider'])
                    ->latest()
                    ->limit(50)
                    ->get()
                : collect(),
            'changes' => $this->tab === 'changes'
                ? ChangeRequest::query()->where('requested_by', auth()->id())->with(['application', 'media'])->latest()->limit(50)->get()
                : collect(),
        ]);
    }
}
