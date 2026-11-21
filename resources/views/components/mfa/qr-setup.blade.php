@props(['qrCode', 'secret'])

{{-- Step 1 of MFA enrolment: scan the QR code or type the key. --}}
<ol {{ $attributes->class('space-y-6 text-sm text-zinc-600 dark:text-zinc-400') }}>
    <li class="flex gap-3">
        <span class="grid size-6 shrink-0 place-items-center rounded-full bg-primary-50 text-xs font-semibold text-primary-700 dark:bg-primary-950 dark:text-primary-300">1</span>
        <p>Install <span class="font-medium text-zinc-900 dark:text-zinc-100">Microsoft Authenticator</span> or <span class="font-medium text-zinc-900 dark:text-zinc-100">Google Authenticator</span> on your phone.</p>
    </li>
    <li class="flex gap-3">
        <span class="grid size-6 shrink-0 place-items-center rounded-full bg-primary-50 text-xs font-semibold text-primary-700 dark:bg-primary-950 dark:text-primary-300">2</span>
        <div class="min-w-0 flex-1">
            <p>Scan this QR code in the app.</p>
            <div class="mt-3 inline-block rounded-2xl bg-white p-3 shadow-sm ring-1 ring-zinc-200 dark:ring-zinc-700 [&_svg]:size-44">
                {!! $qrCode !!}
            </div>
            <details class="mt-3">
                <summary class="cursor-pointer text-xs font-medium text-primary-700 dark:text-primary-400">Can’t scan? Enter the key manually</summary>
                <p class="mt-2 select-all break-all rounded-lg bg-zinc-100 px-3 py-2 font-mono text-sm tracking-wider text-zinc-900 dark:bg-zinc-800 dark:text-zinc-100">{{ $secret }}</p>
            </details>
        </div>
    </li>
    <li class="flex gap-3">
        <span class="grid size-6 shrink-0 place-items-center rounded-full bg-primary-50 text-xs font-semibold text-primary-700 dark:bg-primary-950 dark:text-primary-300">3</span>
        <p>Enter the 6-digit code the app shows.</p>
    </li>
</ol>
