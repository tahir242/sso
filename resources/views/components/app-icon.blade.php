@props(['name', 'size' => 'size-12'])

{{-- Application initials on the brand gradient. --}}
<span {{ $attributes->class("{$size} grid shrink-0 place-items-center rounded-xl bg-gradient-to-br from-primary-500 to-primary-800 text-sm font-semibold text-white shadow-sm") }} aria-hidden="true">
    {{ \App\Domain\Identity\Auth\RecentAccounts::initials($name) }}
</span>
