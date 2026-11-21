@props(['files'])

@if ($files->isNotEmpty())
    <ul class="mt-3 flex flex-wrap gap-2 text-sm">
        @foreach ($files as $file)
            <li>
                <a href="{{ $file->getUrl() }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-primary-700 ring-1 ring-zinc-200 hover:bg-zinc-50 dark:text-primary-400 dark:ring-zinc-700 dark:hover:bg-zinc-800">
                    <x-heroicon-o-paper-clip class="size-4" /> {{ $file->name }}
                </a>
            </li>
        @endforeach
    </ul>
@endif
