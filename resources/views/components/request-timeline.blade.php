@props(['request'])

@php
    use App\Domain\Access\Enums\ApprovalStepStatus;
@endphp

{{-- Approval chain of one access request, step by step. --}}
<ol {{ $attributes->class('space-y-3') }}>
    @foreach ($request->steps as $step)
        <li class="flex gap-3">
            <span @class([
                'mt-0.5 grid size-6 shrink-0 place-items-center rounded-full',
                'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' => $step->status === ApprovalStepStatus::Approved,
                'bg-primary-100 text-primary-700 dark:bg-primary-950 dark:text-primary-300' => $step->status === ApprovalStepStatus::Rejected,
                'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300' => $step->status === ApprovalStepStatus::Pending,
                'bg-zinc-100 text-zinc-400 dark:bg-zinc-800' => in_array($step->status, [ApprovalStepStatus::Waiting, ApprovalStepStatus::Skipped], true),
            ])>
                @switch($step->status)
                    @case(ApprovalStepStatus::Approved) <x-heroicon-m-check class="size-4" /> @break
                    @case(ApprovalStepStatus::Rejected) <x-heroicon-m-x-mark class="size-4" /> @break
                    @case(ApprovalStepStatus::Pending) <x-heroicon-m-clock class="size-4" /> @break
                    @default <x-heroicon-m-minus class="size-4" />
                @endswitch
            </span>
            <div class="min-w-0 text-sm">
                <p class="font-medium text-zinc-800 dark:text-zinc-200">
                    {{ $step->type->getLabel() }}
                    <span class="font-normal text-zinc-500">
                        · {{ $step->status->getLabel() }}
                        @if ($step->decider) by {{ $step->decider->name }} @elseif ($step->approver && $step->status === ApprovalStepStatus::Pending) — {{ $step->approver->name }} @endif
                        @if ($step->decided_at) · {{ $step->decided_at->format('j M, H:i') }} @endif
                        @if ($step->isOverdue()) · <span class="text-primary-700 dark:text-primary-400">overdue</span> @endif
                    </span>
                </p>
                @if ($step->comment)
                    <p class="mt-0.5 text-zinc-500 dark:text-zinc-400">“{{ $step->comment }}”</p>
                @endif
            </div>
        </li>
    @endforeach
</ol>
