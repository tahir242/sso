<x-filament-panels::page>
    @php
        $pct = fn (?int $v) => $v === null ? '—' : $v.'%';
        $hrs = fn (?float $v) => $v === null ? '—' : ($v < 48 ? $v.' h' : round($v / 24, 1).' days');
        $tone = fn (?int $v) => match (true) {
            $v === null => 'text-gray-950 dark:text-white',
            $v >= 90 => 'text-emerald-600 dark:text-emerald-400',
            $v >= 70 => 'text-amber-600 dark:text-amber-400',
            default => 'text-primary-600 dark:text-primary-400',
        };
        $stats = [
            ['Raised', $report['raised'], 'Tickets raised in the period', null],
            ['Resolved', $report['resolved'], 'Of those, resolved so far', null],
            ['Open now', $report['open_now'], 'All open tickets today', null],
            ['Overdue now', $report['overdue_now'], 'Open and past resolve-by', $report['overdue_now'] ? 'text-primary-600 dark:text-primary-400' : null],
            ['First reply on time', $pct($report['response_on_time']), 'Average '.$hrs($report['avg_response_hours']), $tone($report['response_on_time'])],
            ['Resolved on time', $pct($report['resolve_on_time']), 'Median '.$hrs($report['median_resolve_hours']), $tone($report['resolve_on_time'])],
        ];
    @endphp

    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-gray-500 dark:text-gray-400">Tickets raised in the last {{ $days }} days. “On time” compares against the targets in Settings → Helpdesk.</p>
        <x-filament::tabs>
            @foreach ([7 => '7 days', 30 => '30 days', 90 => '90 days', 365 => '12 months'] as $value => $label)
                <x-filament::tabs.item :active="$days === $value" wire:click="$set('days', {{ $value }})">{{ $label }}</x-filament::tabs.item>
            @endforeach
        </x-filament::tabs>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($stats as [$label, $value, $hint, $class])
            <x-filament::section compact>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $label }}</p>
                <p @class(['mt-1 text-3xl font-semibold tracking-tight tabular-nums', $class ?? 'text-gray-950 dark:text-white'])>{{ $value }}</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $hint }}</p>
            </x-filament::section>
        @endforeach
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        @foreach (['by_application' => 'By application', 'by_category' => 'By category', 'by_assignee' => 'By assignee'] as $key => $heading)
            <x-filament::section :heading="$heading" compact>
                @if ($report[$key]->isEmpty())
                    <p class="text-sm text-gray-500">No tickets in this period.</p>
                @else
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs uppercase tracking-wide text-gray-500">
                                <th class="py-2 pe-2 font-medium">Name</th>
                                <th class="py-2 px-2 text-end font-medium">Raised</th>
                                <th class="py-2 px-2 text-end font-medium">Open</th>
                                <th class="py-2 ps-2 text-end font-medium">On time</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            @foreach ($report[$key] as $row)
                                <tr>
                                    <td class="py-2 pe-2">{{ $row['name'] }}</td>
                                    <td class="py-2 px-2 text-end tabular-nums">{{ $row['raised'] }}</td>
                                    <td class="py-2 px-2 text-end tabular-nums">{{ $row['open'] }}</td>
                                    <td @class(['py-2 ps-2 text-end tabular-nums font-medium', $tone($row['on_time'])])>{{ $pct($row['on_time']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </x-filament::section>
        @endforeach
    </div>
</x-filament-panels::page>
