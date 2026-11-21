<div class="mx-auto max-w-2xl">
    <x-breadcrumbs :items="[['My requests', route('access.requests')], ['New request', route('requests.new')], ['Suggest an improvement', null]]" />
    <h1 class="mt-6 text-3xl font-semibold tracking-tight">Suggest an improvement</h1>
    <p class="mt-2 text-zinc-600 dark:text-zinc-400">
        An idea that would make {{ $application?->name ?? 'an application' }} better for you or your team. It goes to the team that builds it; you can follow it under My requests → Suggestions.
    </p>

    {{-- Problems are handled by the helpdesk, not here. --}}
    <a href="{{ route('support.new', array_filter(['app' => $application?->slug])) }}" class="mt-6 flex items-start gap-3 rounded-xl bg-rose-50 p-4 text-sm text-rose-900 ring-1 ring-rose-200 transition hover:bg-rose-100 dark:bg-rose-950/40 dark:text-rose-100 dark:ring-rose-900 dark:hover:bg-rose-950/70">
        <x-heroicon-o-lifebuoy class="mt-0.5 size-5 shrink-0" />
        <span><span class="font-semibold">Something not working?</span> Errors, wrong data or not being able to sign in go to the IT helpdesk instead — <span class="font-semibold underline underline-offset-2">get IT help</span>.</span>
    </a>

    <form wire:submit="submit" class="mt-6 space-y-6 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-zinc-200 dark:bg-zinc-900 dark:ring-zinc-800" novalidate>
        <p class="text-xs text-zinc-500 dark:text-zinc-400"><span class="text-primary-600 dark:text-primary-400" aria-hidden="true">*</span> Required</p>
        <div>
                <label for="app" class="block text-sm font-medium text-zinc-800 dark:text-zinc-200">Which application?<x-required /></label>
                <select wire:model.live="app" id="app" required
                        class="mt-2 block w-full rounded-xl border-0 bg-white py-3 pl-3 pr-10 text-sm shadow-sm ring-1 ring-zinc-300 focus:outline-none focus:ring-2 focus:ring-primary-600 dark:bg-zinc-900 dark:ring-zinc-700">
                    <option value="">Choose an application…</option>
                    @foreach ($applications as $option)
                        <option value="{{ $option->slug }}">{{ $option->name }}</option>
                    @endforeach
                </select>
                @error('app') <p class="mt-2 text-sm text-primary-700 dark:text-primary-400" role="alert">{{ $message }}</p> @enderror
            </div>

        <fieldset>
            <legend class="text-sm font-medium text-zinc-800 dark:text-zinc-200">What kind of idea?<x-required /></legend>
            <div class="mt-3 grid gap-2 sm:grid-cols-2">
                @foreach ($types as $option)
                    <label class="flex cursor-pointer items-start gap-2.5 rounded-xl p-3 text-sm ring-1 ring-zinc-200 transition has-[:checked]:bg-primary-50 has-[:checked]:ring-primary-300 dark:ring-zinc-700 dark:has-[:checked]:bg-primary-950/40 dark:has-[:checked]:ring-primary-800">
                        <input type="radio" wire:model="type" value="{{ $option->value }}" class="mt-0.5 size-4 accent-primary-600">
                        <span><span class="block font-medium">{{ $option->getLabel() }}</span><span class="text-zinc-500 dark:text-zinc-400">{{ $option->hint() }}</span></span>
                    </label>
                @endforeach
            </div>
            @error('type') <p class="mt-2 text-sm text-primary-700 dark:text-primary-400" role="alert">{{ $message }}</p> @enderror
        </fieldset>

        <x-text-input wire:model="title" name="title" label="Short summary" maxlength="150" placeholder="e.g. Allow printing the leave form in Urdu" required marked />

        <div>
            <label for="description" class="block text-sm font-medium text-zinc-800 dark:text-zinc-200">Details<x-required /></label>
            <textarea wire:model="description" id="description" rows="6" maxlength="5000" required
                      placeholder="What would you like, and how would it help you or your patients? Who else would use it?"
                      class="mt-2 block w-full rounded-xl border-0 bg-white p-3 text-sm shadow-sm ring-1 ring-zinc-300 placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-primary-600 dark:bg-zinc-900 dark:ring-zinc-700"></textarea>
            @error('description') <p class="mt-2 text-sm text-primary-700 dark:text-primary-400" role="alert">{{ $message }}</p> @enderror
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label for="priority" class="block text-sm font-medium text-zinc-800 dark:text-zinc-200">How important is it?<x-required /></label>
                <select wire:model="priority" id="priority"
                        class="mt-2 block w-full rounded-xl border-0 bg-white py-3 pl-3 pr-10 text-sm shadow-sm ring-1 ring-zinc-300 focus:outline-none focus:ring-2 focus:ring-primary-600 dark:bg-zinc-900 dark:ring-zinc-700">
                    @foreach ($priorities as $option)
                        <option value="{{ $option->value }}">{{ $option->getLabel() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="attachments" class="block text-sm font-medium text-zinc-800 dark:text-zinc-200">Screenshots or files <span class="font-normal text-zinc-500">(up to 3)</span></label>
                <input wire:model="attachments" id="attachments" type="file" multiple accept=".png,.jpg,.jpeg,.webp,.pdf"
                       class="mt-2 block w-full text-sm text-zinc-600 file:mr-3 file:rounded-lg file:border-0 file:bg-zinc-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-zinc-700 hover:file:bg-zinc-200 dark:text-zinc-400 dark:file:bg-zinc-800 dark:file:text-zinc-200">
                <p class="mt-1 text-xs text-zinc-500" wire:loading wire:target="attachments">Uploading…</p>
                @error('attachments') <p class="mt-2 text-sm text-primary-700 dark:text-primary-400" role="alert">{{ $message }}</p> @enderror
                @error('attachments.*') <p class="mt-2 text-sm text-primary-700 dark:text-primary-400" role="alert">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-end">
            <a href="{{ route('requests.new') }}" class="inline-flex items-center justify-center gap-1.5 rounded-xl px-4 py-2.5 text-sm font-semibold text-zinc-700 ring-1 ring-zinc-300 transition hover:bg-zinc-50 dark:text-zinc-200 dark:ring-zinc-700 dark:hover:bg-zinc-800"><x-heroicon-m-arrow-left class="size-4" /> Back</a>
            <x-primary-button target="submit" loading="Sending…" class="sm:!w-auto">Send suggestion</x-primary-button>
        </div>
    </form>
</div>
