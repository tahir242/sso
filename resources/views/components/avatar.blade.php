@props(['initials', 'color' => 'from-primary-500 to-primary-800', 'size' => 'size-10 text-sm'])

{{-- Round initials avatar on a gradient (no photos are stored). --}}
<span {{ $attributes->class("grid shrink-0 place-items-center rounded-full bg-gradient-to-br {$color} {$size} font-semibold text-white shadow-sm ring-2 ring-white dark:ring-zinc-900 select-none") }} aria-hidden="true">{{ $initials }}</span>
