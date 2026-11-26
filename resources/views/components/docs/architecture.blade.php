@props(['product', 'protocol' => 'OpenID Connect'])

{{-- How a product signs staff in through SSO (drawn, not a screenshot). --}}
@php($uid = 'arch-'.\Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(6)))
<figure {{ $attributes->class('overflow-hidden rounded-3xl bg-white p-4 shadow-xl shadow-slate-900/5 ring-1 ring-slate-200 sm:p-6 dark:bg-zinc-900 dark:ring-zinc-800') }}>
    <svg viewBox="0 0 760 300" class="h-auto w-full" role="img" aria-labelledby="{{ $uid }}-title {{ $uid }}-desc">
        <title id="{{ $uid }}-title">How {{ $product }} signs staff in through SSO</title>
        <desc id="{{ $uid }}-desc">The staff member opens {{ $product }}, which sends them to SSO using {{ $protocol }}. SSO checks the password against Active Directory, applies two-step verification for users who have turned it on, and returns a signed identity to {{ $product }}.</desc>
        <defs>
            <linearGradient id="{{ $uid }}-red" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#d10f0f"/><stop offset="1" stop-color="#7e0505"/></linearGradient>
            <marker id="{{ $uid }}-arrow" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse"><path d="M0 0 10 5 0 10z" class="fill-slate-400"/></marker>
            <filter id="{{ $uid }}-shadow" x="-10%" y="-10%" width="120%" height="140%"><feDropShadow dx="0" dy="6" stdDeviation="8" flood-color="#0f172a" flood-opacity=".08"/></filter>
        </defs>

        {{-- Lanes --}}
        <g font-family="Inter, Segoe UI, sans-serif" text-anchor="middle">
            {{-- Staff --}}
            <g filter="url(#{{ $uid }}-shadow)"><rect x="10" y="90" width="140" height="110" rx="18" class="fill-white stroke-slate-200 dark:fill-zinc-800 dark:stroke-zinc-700"/></g>
            <circle cx="80" cy="128" r="16" class="fill-slate-100 dark:fill-zinc-700"/><path d="M70 150c2-9 18-9 20 0" class="fill-none stroke-slate-400" stroke-width="3" stroke-linecap="round"/><circle cx="80" cy="125" r="6" class="fill-slate-400"/>
            <text x="80" y="178" font-size="14" font-weight="600" class="fill-slate-900 dark:fill-white">Staff member</text>
            <text x="80" y="194" font-size="11" class="fill-slate-500">web browser</text>

            {{-- Product --}}
            <g filter="url(#{{ $uid }}-shadow)"><rect x="210" y="75" width="170" height="140" rx="20" class="fill-white stroke-sky-200 dark:fill-zinc-800 dark:stroke-sky-900"/></g>
            <rect x="275" y="98" width="40" height="40" rx="10" class="fill-sky-100 dark:fill-sky-950"/><path d="M285 110h20M285 118h20M285 126h12" class="stroke-sky-600" stroke-width="3" stroke-linecap="round"/>
            <text x="295" y="165" font-size="15" font-weight="700" class="fill-slate-900 dark:fill-white">{{ \Illuminate\Support\Str::limit($product, 18, '…') }}</text>
            <text x="295" y="183" font-size="11" class="fill-slate-500">third-party software</text>
            <text x="295" y="199" font-size="11" class="fill-sky-700 dark:fill-sky-400">{{ $protocol }} client</text>

            {{-- SSO --}}
            <g filter="url(#{{ $uid }}-shadow)"><rect x="450" y="65" width="170" height="160" rx="22" fill="url(#{{ $uid }}-red)"/></g>
            <path d="M535 92 551 98v13c0 11-7 19-16 24-9-5-16-13-16-24V98Z" fill="#fff"/><circle cx="535" cy="110" r="4.5" fill="#a80202"/>
            <text x="535" y="160" font-size="15" font-weight="700" fill="#fff">SSO</text>
            <text x="535" y="178" font-size="11" fill="#ffd7d7">single sign-on · approvals</text>
            <text x="535" y="194" font-size="11" fill="#ffd7d7">two-step verification</text>

            {{-- Directory --}}
            <g filter="url(#{{ $uid }}-shadow)"><rect x="660" y="100" width="90" height="90" rx="18" class="fill-white stroke-slate-200 dark:fill-zinc-800 dark:stroke-zinc-700"/></g>
            <ellipse cx="705" cy="127" rx="18" ry="6" class="fill-none stroke-slate-400" stroke-width="2.5"/><path d="M687 127v20c0 3 8 6 18 6s18-3 18-6v-20" class="fill-none stroke-slate-400" stroke-width="2.5"/>
            <text x="705" y="172" font-size="11" font-weight="600" class="fill-slate-700 dark:fill-zinc-200">Active</text>
            <text x="705" y="184" font-size="11" font-weight="600" class="fill-slate-700 dark:fill-zinc-200">Directory</text>
        </g>

        {{-- Flow --}}
        <g font-family="Inter, Segoe UI, sans-serif" font-size="11" class="fill-slate-600 dark:fill-zinc-400">
            <path d="M152 125H206" class="stroke-slate-400" stroke-width="2" marker-end="url(#{{ $uid }}-arrow)"/>
            <path d="M382 110H446" class="stroke-slate-400" stroke-width="2" marker-end="url(#{{ $uid }}-arrow)"/>
            <path d="M622 145H656" class="stroke-slate-400" stroke-width="2" stroke-dasharray="4 4" marker-end="url(#{{ $uid }}-arrow)" marker-start="url(#{{ $uid }}-arrow)"/>
            <path d="M446 180H382" class="stroke-primary-600" stroke-width="2.5" marker-end="url(#{{ $uid }}-arrow)"/>

            @foreach ([[179, 112, '1', 'opens'], [414, 97, '2', 'sign in'], [639, 132, '3', 'verify'], [414, 200, '4', 'identity']] as [$x, $y, $n, $label])
                <circle cx="{{ $x }}" cy="{{ $y - 4 }}" r="9" class="fill-primary-600"/>
                <text x="{{ $x }}" y="{{ $y }}" text-anchor="middle" font-size="10" font-weight="700" fill="#fff">{{ $n }}</text>
                <text x="{{ $x }}" y="{{ $y + 22 }}" text-anchor="middle">{{ $label }}</text>
            @endforeach
        </g>

        <g font-family="Inter, Segoe UI, sans-serif" font-size="12" class="fill-slate-500 dark:fill-zinc-400">
            <text x="20" y="262">1 Staff open {{ \Illuminate\Support\Str::limit($product, 22, '…') }}  ·  2 It sends them to SSO ({{ $protocol }})</text>
            <text x="20" y="282">3 Password checked against Active Directory (+ two-step if turned on)  ·  4 A signed identity returns; no second password</text>
        </g>
    </svg>
</figure>
