@props(['codes'])

{{-- Shown once: plain recovery codes with copy and download helpers. --}}
<div
    x-data="{
        codes: @js($codes),
        copied: false,
        copy() { navigator.clipboard?.writeText(this.codes.join('\n')); this.copied = true; setTimeout(() => this.copied = false, 2000) },
        download() {
            const blob = new Blob(['single sign-on recovery codes\nEach code works once.\n\n' + this.codes.join('\n') + '\n'], { type: 'text/plain' });
            const a = Object.assign(document.createElement('a'), { href: URL.createObjectURL(blob), download: 'recovery-codes.txt' });
            a.click(); URL.revokeObjectURL(a.href);
        },
    }"
    {{ $attributes->class('rounded-2xl bg-amber-50 p-5 ring-1 ring-amber-200 dark:bg-amber-950/30 dark:ring-amber-900') }}
>
    <p class="text-sm font-semibold text-amber-900 dark:text-amber-200">Save your recovery codes</p>
    <p class="mt-1 text-sm text-amber-800 dark:text-amber-300/90">If you lose your phone, each code lets you sign in once. They won’t be shown again.</p>
    <ul class="mt-4 grid grid-cols-2 gap-2 font-mono text-sm">
        @foreach ($codes as $code)
            <li class="rounded-lg bg-white px-3 py-2 text-center text-zinc-900 ring-1 ring-amber-200 dark:bg-zinc-900 dark:text-zinc-100 dark:ring-amber-900">{{ $code }}</li>
        @endforeach
    </ul>
    <div class="mt-4 flex gap-2">
        <button type="button" x-on:click="copy()" class="rounded-lg px-3 py-1.5 text-sm font-medium text-amber-900 ring-1 ring-amber-300 transition hover:bg-amber-100 dark:text-amber-200 dark:ring-amber-800 dark:hover:bg-amber-900/40">
            <span x-show="!copied">Copy</span><span x-show="copied" x-cloak>Copied</span>
        </button>
        <button type="button" x-on:click="download()" class="rounded-lg px-3 py-1.5 text-sm font-medium text-amber-900 ring-1 ring-amber-300 transition hover:bg-amber-100 dark:text-amber-200 dark:ring-amber-800 dark:hover:bg-amber-900/40">Download</button>
    </div>
</div>
