{{-- Admin console header: the logo from Settings → General (or the built-in mark) and the site name. --}}
@php($logo = \App\Domain\Settings\Branding::logoUrl())
<span class="flex h-full items-center gap-2.5">
    @if ($logo)
        <img src="{{ $logo }}" alt="" class="h-full w-auto max-w-[8rem] object-contain" decoding="async">
    @else
        <x-brand-logo-fallback class="h-full w-auto" />
    @endif
    <span class="truncate text-base font-semibold tracking-tight text-gray-950 dark:text-white">{{ config('app.name') }}</span>
</span>
