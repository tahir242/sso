@props(['greeting'])

@php
    use App\Domain\Engagement\Enums\OccasionTheme;

    $occasion = $greeting['type'] === 'occasion' ? $greeting['occasion'] : null;
    $quote = $greeting['type'] === 'quote' ? $greeting['quote'] : null;
    $theme = $occasion?->theme ?? OccasionTheme::General;

    $accent = match ($theme) {
        OccasionTheme::National => 'from-emerald-500/30 to-emerald-900/10 ring-emerald-300/30',
        OccasionTheme::Eid, OccasionTheme::Ramadan => 'from-amber-300/25 to-amber-900/5 ring-amber-200/30',
        default => 'from-white/15 to-white/5 ring-white/20',
    };
@endphp

<figure
    {{ $attributes->merge(['class' => "relative overflow-hidden rounded-2xl bg-gradient-to-br p-6 ring-1 backdrop-blur-sm sm:p-8 {$accent}"]) }}
    x-data="{ shown: false }"
    x-init="requestAnimationFrame(() => shown = true)"
    x-bind:class="shown ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-3'"
    style="transition: opacity .7s ease, transform .7s ease"
>
    @if ($occasion)
        {{-- Decorative layer per theme --}}
        @if (in_array($theme, [OccasionTheme::Eid, OccasionTheme::Ramadan], true))
            <svg class="pointer-events-none absolute -right-6 -top-6 size-36 text-amber-200/25" viewBox="0 0 100 100" aria-hidden="true">
                <path fill="currentColor" d="M62 10a40 40 0 1 0 28 68A34 34 0 1 1 62 10Z" />
            </svg>
            @foreach ([[18, 14, '0s'], [82, 70, '.8s'], [64, 22, '1.6s'], [30, 78, '2.4s']] as [$x, $y, $delay])
                <span class="twinkle pointer-events-none absolute size-1.5 rounded-full bg-amber-100" style="left: {{ $x }}%; top: {{ $y }}%; animation-delay: {{ $delay }}" aria-hidden="true"></span>
            @endforeach
        @elseif ($theme === OccasionTheme::National)
            <svg class="pointer-events-none absolute -right-4 -top-4 size-32 text-white/15" viewBox="0 0 100 100" aria-hidden="true">
                <path fill="currentColor" d="M58 14a36 36 0 1 0 26 60 30 30 0 1 1-26-60Z" />
                <path fill="currentColor" d="m70 30 3.5 8.5 9 .7-6.9 5.9 2.1 8.8L70 49.2l-7.7 4.7 2.1-8.8-6.9-5.9 9-.7Z" />
            </svg>
        @elseif ($theme === OccasionTheme::NewYear)
            <x-heroicon-o-sparkles class="pointer-events-none absolute -right-2 -top-2 size-28 text-white/15" aria-hidden="true" />
        @endif

        <p class="relative text-xs font-semibold uppercase tracking-[0.2em] text-white/70">{{ now()->translatedFormat('l, j F') }}</p>
        <h2 class="relative mt-3 text-2xl font-semibold tracking-tight text-white sm:text-3xl">{{ $occasion->title }}</h2>
        <p class="relative mt-3 max-w-md text-base leading-relaxed text-white/85">{{ $occasion->message }}</p>
        @if ($occasion->message_ur)
            <p class="relative mt-4 text-2xl leading-loose text-white" dir="rtl" lang="ur">{{ $occasion->message_ur }}</p>
        @endif
    @else
        <figure class="relative">
            <svg class="size-8 text-white/40" viewBox="0 0 32 32" fill="currentColor" aria-hidden="true">
                <path d="M9.3 25C6.4 25 4 22.6 4 19.2c0-5.4 3.6-10.4 9.5-12.2l1 1.8c-3.7 1.6-5.6 4.3-5.9 7.2.3-.1.7-.1 1-.1 2.7 0 4.8 2 4.8 4.5S12.2 25 9.3 25Zm14 0C20.4 25 18 22.6 18 19.2c0-5.4 3.6-10.4 9.5-12.2l1 1.8c-3.7 1.6-5.6 4.3-5.9 7.2.3-.1.7-.1 1-.1 2.7 0 4.8 2 4.8 4.5S26.2 25 23.3 25Z" />
            </svg>

            <blockquote class="mt-4 text-xl font-medium leading-relaxed text-white sm:text-2xl">
                <p>{{ trim($quote->text, '"\' ') }}</p>
            </blockquote>

            @if ($quote->author)
                <figcaption class="mt-4 text-sm text-white/70">
                    &mdash; {{ $quote->author }}
                </figcaption>
            @endif

            <p class="mt-6 text-xs font-semibold uppercase tracking-[0.2em] text-white/50">
                Food for thought
            </p>
        </figure>
    @endif
</figure>
