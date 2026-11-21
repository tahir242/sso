<div class="mx-auto max-w-2xl">
    <x-breadcrumbs :items="[['My requests', route('access.requests')], ['New request', route('requests.new')], ['Get IT help', null]]" />
    <h1 class="mt-6 text-3xl font-semibold tracking-tight">Get IT help</h1>
    <p class="mt-2 text-zinc-600 dark:text-zinc-400">Tell the IT helpdesk what’s wrong. You’ll get replies here and by notification.</p>
    <p class="mt-3 text-sm text-zinc-500 dark:text-zinc-400">Have an idea rather than a problem? <a href="{{ route('suggestions.new', array_filter(['app' => $contextApp?->slug])) }}" class="font-medium text-primary-700 underline-offset-2 hover:underline dark:text-primary-400">Suggest an improvement</a> instead.</p>

    @php
        $select = 'mt-2 block w-full rounded-xl border-0 bg-white py-3 pl-3 pr-10 text-sm shadow-sm ring-1 ring-zinc-300 focus:outline-none focus:ring-2 focus:ring-primary-600 dark:bg-zinc-900 dark:ring-zinc-700';
        $label = 'block text-sm font-medium text-zinc-800 dark:text-zinc-200';
        $error = 'mt-2 text-sm text-primary-700 dark:text-primary-400';
    @endphp

    <form wire:submit="submit" class="mt-8 space-y-6 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-zinc-200 dark:bg-zinc-900 dark:ring-zinc-800" novalidate>
        <p class="text-xs text-zinc-500 dark:text-zinc-400"><span class="text-primary-600 dark:text-primary-400" aria-hidden="true">*</span> Required</p>
        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label for="application_id" class="{{ $label }}">Which application?</label>
                <select wire:model.live="application_id" id="application_id" class="{{ $select }}">
                    <option value="">Not about an application</option>
                    @foreach ($applications as $application)
                        <option value="{{ $application->id }}">{{ $application->name }}</option>
                    @endforeach
                </select>
                @error('application_id') <p class="{{ $error }}" role="alert">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="category_id" class="{{ $label }}">What kind of problem?</label>
                <select wire:model="category_id" id="category_id" class="{{ $select }}" @disabled($categories->isEmpty())>
                    <option value="">{{ $categories->isEmpty() ? 'No categories' : 'Choose…' }}</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" wire:key="cat-{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
                @error('category_id') <p class="{{ $error }}" role="alert">{{ $message }}</p> @enderror
            </div>
        </div>

        <x-text-input wire:model="subject" name="subject" label="Short summary" maxlength="150" placeholder="e.g. Ward 3 printer prints blank pages" required marked />

        <div>
            <label for="description" class="{{ $label }}">Details<x-required /></label>
            <textarea wire:model="description" id="description" rows="6" maxlength="5000" required
                      placeholder="What were you doing, what did you expect, and what happened? Include any error message and your extension number."
                      class="mt-2 block w-full rounded-xl border-0 bg-white p-3 text-sm shadow-sm ring-1 ring-zinc-300 placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-primary-600 dark:bg-zinc-900 dark:ring-zinc-700"></textarea>
            @error('description') <p class="{{ $error }}" role="alert">{{ $message }}</p> @enderror
        </div>

        <fieldset>
            <legend class="{{ $label }}">How urgent?<x-required /></legend>
            <div class="mt-3 grid gap-2 sm:grid-cols-2">
                @foreach ($priorities as $option)
                    <label class="flex cursor-pointer items-start gap-3 rounded-xl p-3 text-sm ring-1 ring-zinc-200 transition has-[:checked]:bg-primary-50 has-[:checked]:ring-primary-300 dark:ring-zinc-700 dark:has-[:checked]:bg-primary-950/40 dark:has-[:checked]:ring-primary-800">
                        <input type="radio" wire:model="priority" value="{{ $option->value }}" class="mt-0.5 size-4 accent-primary-600">
                        <span><span class="font-medium">{{ $option->getLabel() }}</span><span class="block text-zinc-500">{{ $option->hint() }}</span></span>
                    </label>
                @endforeach
            </div>
        </fieldset>

        <div>
            <label for="attachments" class="{{ $label }}">Screenshots or files <span class="font-normal text-zinc-500">(up to 3, 5 MB each)</span></label>
            <input wire:model="attachments" id="attachments" type="file" multiple accept=".png,.jpg,.jpeg,.webp,.pdf"
                   class="mt-2 block w-full text-sm text-zinc-600 file:mr-3 file:rounded-lg file:border-0 file:bg-zinc-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-zinc-700 hover:file:bg-zinc-200 dark:text-zinc-400 dark:file:bg-zinc-800 dark:file:text-zinc-200">
            <p class="mt-1 text-xs text-zinc-500" wire:loading wire:target="attachments">Uploading…</p>
            @error('attachments') <p class="{{ $error }}" role="alert">{{ $message }}</p> @enderror
            @error('attachments.*') <p class="{{ $error }}" role="alert">{{ $message }}</p> @enderror
        </div>

        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-end">
            <a href="{{ route('requests.new') }}" class="inline-flex items-center justify-center gap-1.5 rounded-xl px-4 py-2.5 text-sm font-semibold text-zinc-700 ring-1 ring-zinc-300 transition hover:bg-zinc-50 dark:text-zinc-200 dark:ring-zinc-700 dark:hover:bg-zinc-800"><x-heroicon-m-arrow-left class="size-4" /> Back</a>
            <x-primary-button target="submit" loading="Sending…" class="sm:!w-auto">Send to IT helpdesk</x-primary-button>
        </div>
    </form>
</div>
