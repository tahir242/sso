@php
    $applications = $getApplications();
    $statePath = $getStatePath();
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        x-data="{
            state: $wire.$entangle(@js($statePath)),
            apps: @js($applications),
            q: '',
            get selectable() { return this.apps.filter(a => ! a.everyone) },
            get visible() {
                const q = this.q.trim().toLowerCase();
                return q === '' ? this.apps : this.apps.filter(a => (a.name + ' ' + a.description + ' ' + (a.owner ?? '')).toLowerCase().includes(q));
            },
            has(id) { return (this.state ?? []).map(Number).includes(id) },
            toggle(id) {
                const s = (this.state ?? []).map(Number);
                this.state = s.includes(id) ? s.filter(x => x !== id) : [...s, id];
            },
            selectVisible() { this.state = [...new Set([...(this.state ?? []).map(Number), ...this.visible.filter(a => ! a.everyone).map(a => a.id)])] },
            clearVisible() { const ids = this.visible.map(a => a.id); this.state = (this.state ?? []).map(Number).filter(x => ! ids.includes(x)) },
        }"
        class="rounded-xl ring-1 ring-gray-950/10 dark:ring-white/10"
    >
        <div class="flex flex-wrap items-center gap-3 border-b border-gray-950/5 p-3 dark:border-white/10">
            <div class="relative min-w-48 flex-1">
                <svg class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-gray-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11ZM2 9a7 7 0 1 1 12.45 4.39l3.08 3.08a.75.75 0 1 1-1.06 1.06l-3.08-3.08A7 7 0 0 1 2 9Z" clip-rule="evenodd"/></svg>
                <input type="search" x-model="q" placeholder="Search applications" aria-label="Search applications"
                       class="block w-full rounded-lg border-0 bg-white py-1.5 pl-9 pr-3 text-sm text-gray-950 ring-1 ring-gray-950/10 placeholder:text-gray-400 focus:ring-2 focus:ring-primary-600 dark:bg-white/5 dark:text-white dark:ring-white/20">
            </div>
            <p class="text-sm text-gray-500 dark:text-gray-400" aria-live="polite">
                <span class="font-semibold text-gray-950 dark:text-white" x-text="(state ?? []).length"></span> of <span x-text="selectable.length"></span> selected
            </p>
            <div class="flex gap-1">
                <button type="button" x-on:click="selectVisible()" class="rounded-lg px-2.5 py-1.5 text-sm font-medium text-primary-600 hover:bg-primary-50 dark:text-primary-400 dark:hover:bg-white/5">Select all</button>
                <button type="button" x-on:click="clearVisible()" class="rounded-lg px-2.5 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-white/5">Clear</button>
            </div>
        </div>

        <div class="max-h-[28rem] overflow-y-auto p-3" role="group" aria-label="Applications">
            <ul class="grid gap-2 md:grid-cols-2">
                <template x-for="app in visible" :key="app.id">
                    <li>
                        <label
                            class="flex h-full cursor-pointer items-start gap-3 rounded-xl p-3 ring-1 transition"
                            :class="app.everyone
                                ? 'cursor-default bg-sky-50/60 ring-sky-200 dark:bg-sky-500/5 dark:ring-sky-500/20'
                                : (has(app.id) ? 'bg-primary-50 ring-2 ring-primary-600 dark:bg-primary-500/10 dark:ring-primary-500' : 'bg-white ring-gray-950/10 hover:bg-gray-50 hover:ring-gray-950/20 dark:bg-white/5 dark:ring-white/10 dark:hover:bg-white/10')"
                        >
                            <input type="checkbox" class="mt-1 size-4 rounded border-gray-300 text-primary-600 focus:ring-primary-600 disabled:opacity-60 dark:border-white/20 dark:bg-white/5"
                                   :checked="app.everyone || has(app.id)" :disabled="app.everyone" x-on:change="toggle(app.id)"
                                   :aria-describedby="'app-desc-' + app.id">
                            <span class="grid size-10 shrink-0 place-items-center rounded-lg bg-gradient-to-br from-primary-500 to-primary-800 text-xs font-semibold text-white shadow-sm" x-text="app.initials" aria-hidden="true"></span>
                            <span class="min-w-0 flex-1">
                                <span class="flex items-center gap-1.5">
                                    <span class="truncate text-sm font-semibold text-gray-950 dark:text-white" x-text="app.name"></span>
                                    <template x-if="app.mfa">
                                        <span class="rounded bg-amber-50 px-1.5 text-[11px] font-medium text-amber-700 ring-1 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-500/20" title="Requires two-step verification">2-step</span>
                                    </template>
                                </span>
                                <span :id="'app-desc-' + app.id" class="mt-0.5 line-clamp-2 block text-xs text-gray-500 dark:text-gray-400" x-text="app.description || 'No description'"></span>
                                <span class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-gray-500 dark:text-gray-400">
                                    <span x-show="app.everyone" class="font-medium text-sky-700 dark:text-sky-300">Open to everyone — no assignment needed</span>
                                    <span x-show="! app.everyone"><span x-text="app.users"></span> users</span>
                                    <span x-show="app.owner">Owner: <span x-text="app.owner"></span></span>
                                    <span x-show="! app.everyone && app.roles.length">Gets: <span x-text="app.roles.join(', ')"></span></span>
                                </span>
                            </span>
                        </label>
                    </li>
                </template>
            </ul>
            <p x-show="visible.length === 0" class="p-6 text-center text-sm text-gray-500">No applications match “<span x-text="q"></span>”.</p>
        </div>
    </div>
</x-dynamic-component>
