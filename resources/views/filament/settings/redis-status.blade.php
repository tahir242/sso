@php
    $labels = ['database' => 'Database', 'redis' => 'Redis', 'file' => 'Files'];
    $uptime = isset($probe['uptime_seconds']) ? \Carbon\CarbonInterval::seconds($probe['uptime_seconds'])->cascade()->forHumans(['short' => true, 'parts' => 2]) : null;
@endphp

<div class="space-y-3">
    @if ($fallback)
        <div class="rounded-lg border border-danger-300 bg-danger-50 px-4 py-3 text-sm text-danger-800 dark:border-danger-700 dark:bg-danger-950 dark:text-danger-200">
            <strong>Redis is unreachable.</strong> The SSO is running on the database for the cache and queue, and retries Redis every {{ \App\Support\Redis\RedisRuntime::RETRY_SECONDS }} seconds. Check that the Redis service is running.
        </div>
    @endif

    <div class="grid gap-3 sm:grid-cols-3">
        @foreach (['cache' => 'Cache', 'queue' => 'Queued jobs', 'session' => 'Sessions'] as $key => $label)
            <div class="rounded-lg border border-gray-200 px-4 py-3 dark:border-white/10">
                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $label }} — in use now</div>
                <div class="mt-1 text-sm font-semibold text-gray-950 dark:text-white">{{ $labels[$effective[$key] ?? ''] ?? ($effective[$key] ?? '—') }}</div>
            </div>
        @endforeach
    </div>

    @if ($probe)
        @if ($probe['ok'])
            <div class="rounded-lg border border-gray-200 px-4 py-3 text-sm dark:border-white/10">
                <div class="flex flex-wrap items-center gap-x-6 gap-y-1">
                    <span class="inline-flex items-center gap-1.5 font-semibold text-success-700 dark:text-success-400">
                        <span class="h-2 w-2 rounded-full bg-success-500"></span> Connected
                    </span>
                    <span>Redis <strong>{{ $probe['version'] }}</strong></span>
                    <span>Round trip <strong>{{ $probe['latency_ms'] }} ms</strong></span>
                    <span>Client <strong>{{ $probe['client'] }}</strong></span>
                    <span>Memory <strong>{{ $probe['used_memory'] }}</strong>@if ($probe['maxmemory']) of {{ \Illuminate\Support\Number::fileSize($probe['maxmemory']) }}@endif</span>
                    @if ($uptime)<span>Up <strong>{{ $uptime }}</strong></span>@endif
                    <span>Clients <strong>{{ $probe['connected_clients'] }}</strong></span>
                    @foreach ($probe['keys'] as $db => $count)
                        <span>db{{ $db }} <strong>{{ number_format($count) }}</strong> keys</span>
                    @endforeach
                </div>

                @if ($probe['warnings'])
                    <div class="mt-3 border-t border-gray-200 pt-3 dark:border-white/10">
                        <div class="mb-1 text-xs font-semibold uppercase tracking-wide text-warning-700 dark:text-warning-400">Redis server configuration (redis.conf)</div>
                        <ul class="list-disc space-y-1 ps-5 text-gray-700 dark:text-gray-300">
                            @foreach ($probe['warnings'] as $warning)
                                <li>{{ $warning }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        @else
            <div class="rounded-lg border border-danger-300 bg-danger-50 px-4 py-3 text-sm text-danger-800 dark:border-danger-700 dark:bg-danger-950 dark:text-danger-200">
                <strong>Cannot reach Redis</strong> ({{ $probe['client'] }}): {{ $probe['error'] }}
            </div>
        @endif
    @endif
</div>
