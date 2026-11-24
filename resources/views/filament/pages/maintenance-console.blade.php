<x-filament-panels::page>
    <div class="grid gap-6 xl:grid-cols-5">
        <div class="space-y-6 xl:col-span-3">
            @foreach (\App\Support\Operations\MaintenanceCommands::grouped() as $group => $commands)
                <x-filament::section :heading="$group" compact>
                    <ul class="-my-2 divide-y divide-gray-100 dark:divide-white/5">
                        @foreach ($commands as $key => $command)
                            <li class="flex items-start gap-4 py-3" wire:key="cmd-{{ $key }}">
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-baseline gap-x-2 gap-y-1">
                                        <span class="text-sm font-medium text-gray-950 dark:text-white">{{ $command['label'] }}</span>
                                        <code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-600 dark:bg-white/5 dark:text-gray-400">{{ trim($command['command'].' '.\App\Support\Operations\MaintenanceCommands::describeParameters($command['parameters'])) }}</code>
                                    </div>
                                    <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">{{ $command['description'] }}</p>
                                </div>
                                <div class="shrink-0">
                                    {{ ($this->runAction)(['command' => $key]) }}
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </x-filament::section>
            @endforeach
        </div>

        <div class="space-y-6 xl:col-span-2">
            <div class="xl:sticky xl:top-20 space-y-6">
                <x-filament::section heading="Output" compact>
                    @if ($result)
                        <div class="mb-3 flex flex-wrap items-center gap-2 text-sm">
                            <x-filament::badge :color="$result['exit_code'] === 0 ? 'success' : 'danger'">
                                {{ $result['exit_code'] === 0 ? 'Succeeded' : 'Failed (exit '.$result['exit_code'].')' }}
                            </x-filament::badge>
                            <span class="font-medium text-gray-950 dark:text-white">{{ $result['label'] }}</span>
                            <span class="text-gray-500 dark:text-gray-400">{{ number_format($result['duration_ms'] / 1000, 1) }} s · {{ $result['at'] }}</span>
                        </div>
                        <pre class="max-h-[28rem] overflow-auto whitespace-pre rounded-lg bg-gray-950 p-4 font-mono text-xs leading-relaxed text-gray-100"><span class="text-gray-500">$ php artisan {{ $result['command'] }}</span>
{{ $result['output'] }}</pre>
                    @else
                        <p class="text-sm text-gray-500 dark:text-gray-400">Run a command to see its output here. Commands run on the server as the web server account; every run is recorded in the activity log.</p>
                    @endif
                </x-filament::section>

                <x-filament::section heading="Recent runs" compact>
                    @php($runs = $this->history())
                    @if ($runs->isEmpty())
                        <p class="text-sm text-gray-500 dark:text-gray-400">No commands have been run from here yet.</p>
                    @else
                        <ul class="-my-2 divide-y divide-gray-100 text-sm dark:divide-white/5">
                            @foreach ($runs as $run)
                                <li class="py-2" wire:key="run-{{ $run->id }}">
                                    <button type="button" wire:click="showRun({{ $run->id }})" class="flex w-full items-center gap-3 text-start">
                                        <span @class([
                                            'h-2 w-2 shrink-0 rounded-full',
                                            'bg-success-500' => ($run->properties['exit_code'] ?? 1) === 0,
                                            'bg-danger-500' => ($run->properties['exit_code'] ?? 1) !== 0,
                                        ])></span>
                                        <code class="min-w-0 flex-1 truncate text-xs text-gray-700 dark:text-gray-300">{{ $run->properties['command'] ?? $run->description }}</code>
                                        <span class="shrink-0 text-xs text-gray-500 dark:text-gray-400" title="{{ $run->created_at }}">{{ $run->causer?->username ?? 'system' }} · {{ $run->created_at?->diffForHumans(short: true) }}</span>
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-filament::section>
            </div>
        </div>
    </div>
</x-filament-panels::page>
