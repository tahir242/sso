@props(['greeting' => null, 'settings'])

{{-- Split layout shared by every sign-in screen: brand panel + content panel. --}}
<div class="flex min-h-full">
    <aside class="relative hidden w-1/2 overflow-hidden bg-primary-800 lg:flex lg:flex-col lg:justify-between lg:p-12 xl:p-16">
        <div class="absolute inset-0 bg-gradient-to-br from-primary-600 via-primary-800 to-primary-950" aria-hidden="true"></div>
        <svg class="absolute inset-0 size-full text-white/[0.06]" aria-hidden="true">
            <defs>
                <pattern id="grid" width="32" height="32" patternUnits="userSpaceOnUse">
                    <path d="M32 0H0v32" fill="none" stroke="currentColor" stroke-width="1" />
                </pattern>
            </defs>
            <rect width="100%" height="100%" fill="url(#grid)" />
        </svg>
        <div class="absolute -bottom-40 -left-40 size-[32rem] rounded-full bg-primary-500/30 blur-3xl" aria-hidden="true"></div>

        <x-brand-mark :inverted="true" class="relative" />

        <div class="relative max-w-lg">
            @if ($greeting)
                <x-login-greeting :greeting="$greeting" />
            @else
                <h2 class="text-3xl font-semibold tracking-tight text-white">{{ $settings->tagline }}</h2>
            @endif
        </div>

        <p class="relative text-sm text-primary-100/70">{{ $settings->organization_name }}</p>
    </aside>

    <main class="flex flex-1 flex-col px-4 py-6 sm:px-6 lg:px-12">
        <div class="flex items-center justify-between">
            <x-brand-mark class="lg:invisible" />
            <x-theme-toggle />
        </div>

        <div class="mx-auto flex w-full max-w-sm flex-1 flex-col justify-center py-10">
            {{ $slot }}
        </div>

        <p class="text-center text-xs text-zinc-500">&copy; {{ now()->year }} {{ $settings->organization_name }}</p>
    </main>
</div>
