@php
    $user = auth()->user();
    $tabs = [
        'approvals' => ['label' => 'Access requests', 'count' => \App\Domain\Access\Models\ApprovalStep::query()->awaiting($user)->count()],
        'approvals.reviews' => ['label' => 'Access reviews', 'count' => \App\Domain\Access\Models\AccessReviewItem::query()->awaiting($user)->count()],
    ];

    // Only heads of department (and super-admins) confirm new staff.
    if ($user->hasRole('super-admin') || \App\Domain\Identity\Models\Department::where('hod_user_id', $user->getKey())->exists()) {
        $tabs['approvals.new-staff'] = ['label' => 'New staff', 'count' => \App\Livewire\Access\NewStaffApprovals::awaiting($user)->count()];
    }
@endphp

{{-- Approver inbox tabs: access requests, access reviews, new staff. --}}
<nav {{ $attributes->class('flex gap-1 border-b border-zinc-200 dark:border-zinc-800') }} aria-label="Approvals">
    @foreach ($tabs as $route => $tab)
        <a href="{{ route($route) }}" @class([
            '-mb-px inline-flex items-center gap-2 border-b-2 px-3 py-2 text-sm font-medium transition',
            'border-primary-600 text-primary-700 dark:text-primary-400' => request()->routeIs($route),
            'border-transparent text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200' => ! request()->routeIs($route),
        ]) @if (request()->routeIs($route)) aria-current="page" @endif>
            {{ $tab['label'] }}
            @if ($tab['count'])
                <span class="rounded-full bg-primary-600 px-1.5 text-xs font-semibold text-white">{{ $tab['count'] }}</span>
            @endif
        </a>
    @endforeach
</nav>
