@php
    $user = auth()->user();
    $hour = now()->hour;
    $links = array_filter([
        \App\Filament\Resources\Users\UserResource::canCreate() ? ['Add a user', \App\Filament\Resources\Users\UserResource::getUrl('create'), 'heroicon-m-user-plus'] : null,
        \App\Filament\Resources\Applications\ApplicationResource::canAccess() ? ['Applications', \App\Filament\Resources\Applications\ApplicationResource::getUrl(), 'heroicon-m-squares-2x2'] : null,
        \App\Filament\Pages\LocationDictionary::canAccess() ? ['Locations', \App\Filament\Pages\LocationDictionary::getUrl(), 'heroicon-m-map-pin'] : null,
        ['Staff portal', route('home'), 'heroicon-m-home'],
    ]);
@endphp

<x-filament-widgets::widget>
    <div class="relative overflow-hidden rounded-xl bg-gradient-to-br from-primary-700 via-primary-800 to-primary-950 p-6 text-white shadow-sm ring-1 ring-primary-900/20">
        <div class="absolute -right-16 -top-20 size-64 rounded-full bg-white/10 blur-3xl" aria-hidden="true"></div>
        <div class="absolute -bottom-24 left-1/3 size-64 rounded-full bg-primary-400/20 blur-3xl" aria-hidden="true"></div>
        <div class="relative flex flex-col gap-5 md:flex-row md:items-center md:justify-between">
            <div>
                <p class="text-sm font-medium text-white/70">{{ $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening') }} · {{ now()->format('l, j F Y') }}</p>
                <h2 class="mt-1 text-2xl font-semibold tracking-tight">{{ $user->name }}</h2>
                <p class="mt-1 text-sm text-white/70">Identity &amp; access overview for {{ app(\App\Domain\Settings\GeneralSettings::class)->organization_name }}
                    · {{ $user->getRoleNames()->map(fn ($r) => \Illuminate\Support\Str::headline($r))->implode(', ') }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                @foreach ($links as [$label, $url, $icon])
                    <a href="{{ $url }}" class="inline-flex items-center gap-1.5 rounded-lg bg-white/10 px-3 py-2 text-sm font-medium text-white ring-1 ring-white/20 backdrop-blur transition hover:bg-white/20">
                        <x-filament::icon :icon="$icon" class="size-4" /> {{ $label }}
                    </a>
                @endforeach
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
