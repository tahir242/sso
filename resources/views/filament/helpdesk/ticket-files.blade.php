@if ($items->isNotEmpty())
    <ul class="mt-3 flex flex-wrap gap-2 text-sm">
        @foreach ($items as $file)
            <li>
                <a href="{{ $file->getUrl() }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-primary-600 ring-1 ring-gray-950/10 hover:bg-gray-50 dark:text-primary-400 dark:ring-white/10 dark:hover:bg-white/5">
                    <x-filament::icon icon="heroicon-o-paper-clip" class="size-4" /> {{ $file->name }}
                </a>
            </li>
        @endforeach
    </ul>
@endif
