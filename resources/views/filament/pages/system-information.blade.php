<x-filament-panels::page>
    @php
        $badge = ['ok' => 'success', 'pass' => 'success', 'warn' => 'warning', 'fail' => 'danger'];
        $word = ['ok' => 'OK', 'pass' => 'OK', 'warn' => 'Check', 'fail' => 'Problem'];
    @endphp

    {{-- Summary of the health checks --}}
    <div class="flex flex-wrap items-center gap-2 text-sm">
        <x-filament::badge color="success">{{ $counts['pass'] ?? 0 }} OK</x-filament::badge>
        <x-filament::badge color="warning">{{ $counts['warn'] ?? 0 }} to check</x-filament::badge>
        <x-filament::badge color="danger">{{ $counts['fail'] ?? 0 }} problems</x-filament::badge>
        <span class="text-gray-500 dark:text-gray-400">Read from the web server at {{ $checkedAt }} · the same checks as <code class="text-xs">php artisan sso:check</code></span>
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
        @foreach ($sections as $heading => $rows)
            <x-filament::section :heading="$heading" compact>
                <dl class="-my-2 divide-y divide-gray-100 dark:divide-white/5">
                    @foreach ($rows as $row)
                        <div class="grid gap-1 py-2.5 sm:grid-cols-3 sm:gap-4" wire:key="info-{{ $loop->parent->index }}-{{ $loop->index }}">
                            <dt class="text-sm text-gray-500 dark:text-gray-400">{{ $row['label'] }}</dt>
                            <dd class="min-w-0 text-sm sm:col-span-2">
                                <div class="flex items-start justify-between gap-3">
                                    <span class="break-words font-medium text-gray-950 dark:text-white">{{ $row['value'] }}</span>
                                    @isset($row['status'])
                                        <x-filament::badge :color="$badge[$row['status']]" size="sm" class="shrink-0">{{ $word[$row['status']] }}</x-filament::badge>
                                    @endisset
                                </div>
                                @isset($row['hint'])
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $row['hint'] }}</p>
                                @endisset
                            </dd>
                        </div>
                    @endforeach
                </dl>
            </x-filament::section>
        @endforeach
    </div>

    <x-filament::section heading="Health checks" description="Go-live checks: configuration, keys, background processing, integrations and administrators." compact>
        <div class="-my-2 divide-y divide-gray-100 dark:divide-white/5">
            @foreach ($checks as $area => $results)
                <div class="grid gap-2 py-3 sm:grid-cols-6 sm:gap-4" wire:key="check-{{ $loop->index }}">
                    <p class="text-sm font-medium text-gray-950 dark:text-white">{{ $area }}</p>
                    <ul class="space-y-1.5 sm:col-span-5">
                        @foreach ($results as $result)
                            <li class="flex items-start gap-2 text-sm">
                                <x-filament::badge :color="$badge[$result['status']]" size="sm" class="mt-0.5 shrink-0">{{ $word[$result['status']] }}</x-filament::badge>
                                <span class="text-gray-700 dark:text-gray-300">{{ $result['message'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-panels::page>
