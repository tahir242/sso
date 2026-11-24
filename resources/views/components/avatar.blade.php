@props(['initials', 'color' => 'from-primary-500 to-primary-800', 'size' => 'size-10 text-sm', 'src' => null])

{{-- Round avatar: the profile photo when there is one, otherwise initials on a gradient. --}}
@if ($src)
    <img src="{{ $src }}" alt="" {{ $attributes->class("shrink-0 rounded-full object-cover {$size} shadow-sm ring-2 ring-white dark:ring-zinc-900") }}>
@else
    <span {{ $attributes->class("grid shrink-0 place-items-center rounded-full bg-gradient-to-br {$color} {$size} font-semibold text-white shadow-sm ring-2 ring-white dark:ring-zinc-900 select-none") }} aria-hidden="true">{{ $initials }}</span>
@endif
