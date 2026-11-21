@props(['inverted' => false])

@php($logo = \App\Domain\Settings\Branding::logoUrl())

{{-- The logo from Settings → General, or the built-in SSO mark. --}}
@if ($inverted)
    {{-- On the red sign-in panel: logo on a white tile, inside a soft glass badge. --}}
    <div {{ $attributes->merge(['class' => 'inline-flex max-w-full min-w-0 items-center gap-4 self-start rounded-2xl bg-white/10 py-2.5 pr-6 pl-2.5 ring-1 ring-white/15 backdrop-blur-sm']) }}>
        @if ($logo)
            <span class="flex h-14 shrink-0 items-center rounded-xl bg-white p-2 shadow-lg shadow-primary-950/30">
                <img src="{{ $logo }}" alt="" class="h-full w-auto max-w-[10rem] object-contain" decoding="async">
            </span>
        @else
            <x-brand-logo-fallback class="size-14 shrink-0 rounded-xl shadow-lg shadow-primary-950/30 ring-1 ring-white/30" />
        @endif
        <span class="h-10 w-px shrink-0 bg-white/25" aria-hidden="true"></span>
        <span class="min-w-0">
            <span class="block truncate text-xl leading-tight font-semibold tracking-tight text-white">{{ config('app.name') }}</span>
            <span class="mt-1 block truncate text-[11px] font-medium tracking-[0.18em] text-primary-100/80 uppercase">Single sign-on</span>
        </span>
    </div>
@else
    <div {{ $attributes->merge(['class' => 'flex min-w-0 items-center gap-3']) }}>
        @if ($logo)
            <span class="flex h-10 shrink-0 items-center">
                <img src="{{ $logo }}" alt="" class="h-full w-auto max-w-[9rem] object-contain" decoding="async">
            </span>
        @else
            <x-brand-logo-fallback class="size-10 shrink-0 drop-shadow-sm" />
        @endif
        <span class="min-w-0 leading-tight">
            <span class="block truncate text-sm font-semibold text-zinc-900 dark:text-white">{{ config('app.name') }}</span>
            <span class="block truncate text-xs text-zinc-500 dark:text-zinc-400">Single sign-on</span>
        </span>
    </div>
@endif
