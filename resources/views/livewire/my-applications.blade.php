<div>
    {{-- Welcome banner --}}
    <section class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-primary-700 via-primary-800 to-primary-950 p-6 text-white shadow-lg sm:p-8">
        <div class="blob absolute -right-20 -top-24 size-72 rounded-full bg-primary-400/30 blur-3xl" aria-hidden="true"></div>
        <div class="absolute inset-0 bg-dots text-white/[0.07]" aria-hidden="true"></div>
        <div class="relative flex flex-col gap-6 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-4">
                <x-avatar :initials="\App\Domain\Identity\Auth\RecentAccounts::initials($user->name ?: $user->username)" :src="\App\Domain\Identity\Support\ProfilePhoto::url($user)" color="from-white/30 to-white/10" size="size-14 text-lg" class="!ring-white/30 backdrop-blur" />
                <div>
                    <p class="text-sm font-medium text-primary-100">{{ now()->hour < 12 ? 'Good morning' : (now()->hour < 17 ? 'Good afternoon' : 'Good evening') }}</p>
                    <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">{{ $user->name }}</h1>
                    <p class="mt-0.5 text-sm text-primary-100/80">
                        {{ $user->username }}@if ($user->emp_id) · Employee ID {{ $user->emp_id }}@endif
                    </p>
                </div>
            </div>
            <dl class="flex gap-6 text-center">
                <div><dt class="text-xs uppercase tracking-wider text-primary-100/70">Applications</dt><dd class="text-2xl font-semibold">{{ $total }}</dd></div>
                <div><dt class="text-xs uppercase tracking-wider text-primary-100/70">Two-step</dt><dd class="text-2xl font-semibold">{{ $user->hasTwoFactorEnabled() ? 'On' : 'Off' }}</dd></div>
            </dl>
        </div>
    </section>

    <section class="mt-10" aria-labelledby="apps-heading">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <h2 id="apps-heading" class="text-lg font-semibold">My applications</h2>
            <div class="flex items-center gap-2">
                @if ($total > 6)
                    <div class="relative w-full sm:w-64">
                        <x-heroicon-o-magnifying-glass class="pointer-events-none absolute left-3 top-1/2 size-5 -translate-y-1/2 text-zinc-400" />
                        <input wire:model.live.debounce.200ms="search" type="search" placeholder="Search applications" aria-label="Search applications"
                               class="block w-full rounded-xl border-0 bg-white py-2 pl-10 pr-3 text-sm shadow-sm ring-1 ring-zinc-300 placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-primary-600 dark:bg-zinc-900 dark:ring-zinc-700">
                    </div>
                @endif
                <a href="{{ route('access.request') }}" class="inline-flex shrink-0 items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium text-primary-700 transition hover:bg-primary-50 dark:text-primary-400 dark:hover:bg-primary-950">
                    <x-heroicon-o-plus class="size-4" /> Request access
                </a>
            </div>
        </div>

        @if ($items->isEmpty())
            <div class="mt-4 rounded-2xl border border-dashed border-zinc-300 bg-white p-10 text-center dark:border-zinc-700 dark:bg-zinc-900">
                <x-heroicon-o-squares-2x2 class="mx-auto size-8 text-zinc-400" />
                @if ($total === 0)
                    <p class="mt-3 font-medium">No applications yet</p>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Request access to the applications you need for your work. Your head of department and the application owner approve it.</p>
                    <a href="{{ route('access.request') }}" class="mt-4 inline-flex items-center gap-2 rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700">Request access</a>
                @else
                    <p class="mt-3 font-medium">No applications match “{{ $search }}”</p>
                @endif
            </div>
        @else
            <ul class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($items as $item)
                    @php($application = $item->application)
                    <li wire:key="app-{{ $application->id }}" class="reveal" style="--reveal-delay: {{ min($loop->index, 8) * 40 }}ms">
                        <div class="group relative flex h-full flex-col rounded-2xl bg-white p-5 shadow-sm ring-1 ring-zinc-200 transition duration-300 hover:-translate-y-1 hover:shadow-xl hover:shadow-primary-900/5 hover:ring-primary-200 dark:bg-zinc-900 dark:ring-zinc-800 dark:hover:ring-primary-900">
                            <div class="flex items-start gap-4">
                                <x-app-icon :name="$application->name" size="size-11" />
                                <div class="min-w-0 flex-1">
                                    <p class="flex items-center gap-1.5 font-semibold text-zinc-900 dark:text-white">
                                        <a href="{{ $application->base_url }}" target="_blank" rel="noopener" class="truncate after:absolute after:inset-0 after:rounded-2xl focus-visible:outline-none">{{ $application->name }}</a>
                                        @if ($application->requires_mfa)
                                            <x-heroicon-o-shield-check class="size-4 shrink-0 text-primary-600 dark:text-primary-400" title="Requires two-step verification" />
                                        @endif
                                    </p>
                                    @if ($application->description)
                                        <p class="mt-1 line-clamp-2 text-sm text-zinc-500 dark:text-zinc-400">{{ $application->description }}</p>
                                    @endif
                                </div>
                                <x-heroicon-o-arrow-up-right class="size-4 shrink-0 text-zinc-400 transition group-hover:-translate-y-0.5 group-hover:translate-x-0.5 group-hover:text-primary-600" />
                            </div>
                            @if ($item->assignment?->expires_at)
                                <p class="mt-3 text-xs text-amber-700 dark:text-amber-400">Access ends {{ $item->assignment->expires_at->format('j M Y') }}</p>
                            @endif
                            <div class="mt-auto flex flex-wrap items-center gap-1.5 pt-4">
                                @if (! $item->assignment)
                                    <span class="rounded-full bg-sky-50 px-2 py-0.5 text-xs font-medium text-sky-700 ring-1 ring-sky-200 dark:bg-sky-950 dark:text-sky-300 dark:ring-sky-900">Open to everyone</span>
                                @endif
                                @foreach ($item->roles as $role)
                                    <span class="rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-medium text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">{{ $role->name }}</span>
                                @endforeach
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
