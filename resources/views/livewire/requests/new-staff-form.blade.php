<div class="mx-auto max-w-2xl">
    <x-breadcrumbs :items="[['My requests', route('access.requests')], ['New request', route('requests.new')], ['New staff account', null]]" />
    @if ($reference)
        <div class="mt-6 rounded-2xl bg-white p-8 text-center shadow-sm ring-1 ring-zinc-200 dark:bg-zinc-900 dark:ring-zinc-800" role="status">
            <div class="mx-auto grid size-12 place-items-center rounded-2xl bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">
                <x-heroicon-o-check-badge class="size-6" />
            </div>
            <h1 class="mt-4 text-2xl font-semibold tracking-tight">Request received</h1>
            <p class="mt-2 text-zinc-600 dark:text-zinc-400">The head of department will be asked to confirm it, then the IT helpdesk will create the account.</p>
            <p class="mt-4 text-sm text-zinc-500">Reference: <span class="font-mono font-semibold text-zinc-900 dark:text-zinc-100">{{ $reference }}</span></p>
            @if ($email)
                <p class="mt-1 text-sm text-zinc-500">We’ll email {{ $email }} when there’s news.</p>
            @endif
            <a href="{{ route('requests.new') }}" class="mt-6 inline-flex rounded-xl px-4 py-2.5 text-sm font-medium text-zinc-700 ring-1 ring-zinc-300 transition hover:bg-zinc-50 dark:text-zinc-200 dark:ring-zinc-700 dark:hover:bg-zinc-800">Back to requests</a>
        </div>
    @else
        <h1 class="mt-6 text-3xl font-semibold tracking-tight">Request an account for a new colleague</h1>
        <p class="mt-2 text-zinc-600 dark:text-zinc-400">For staff joining the organization. The head of department confirms the request; the IT helpdesk then creates the account, and access to the applications you choose follows once their owners approve.</p>

        <form wire:submit="submit" class="mt-8 space-y-6 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-zinc-200 dark:bg-zinc-900 dark:ring-zinc-800" novalidate>
            <p class="text-xs text-zinc-500 dark:text-zinc-400"><span class="text-primary-600 dark:text-primary-400" aria-hidden="true">*</span> Required</p>

            <div class="grid gap-5 sm:grid-cols-2">
                <x-text-input wire:model="full_name" name="full_name" label="Full name" autocomplete="off" required marked class="sm:col-span-2" />
                <x-text-input wire:model="emp_id" name="emp_id" label="Employee ID (if known)" />
                <x-text-input wire:model="designation" name="designation" label="Designation" placeholder="e.g. Staff Nurse" required marked />

                <div class="sm:col-span-2">
                    <label for="department_id" class="block text-sm font-medium text-zinc-800 dark:text-zinc-200">Department<x-required /></label>
                    <select wire:model="department_id" id="department_id" required
                            class="mt-2 block w-full rounded-xl border-0 bg-white py-3 pl-3 pr-10 text-sm shadow-sm ring-1 ring-zinc-300 focus:outline-none focus:ring-2 focus:ring-primary-600 dark:bg-zinc-900 dark:ring-zinc-700">
                        <option value="">Choose the department…</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->id }}">{{ $department->name }}</option>
                        @endforeach
                    </select>
                    @error('department_id') <p class="mt-2 text-sm text-primary-700 dark:text-primary-400" role="alert">{{ $message }}</p> @enderror
                </div>

                <x-text-input wire:model="email" name="email" type="email" label="Their email address" autocomplete="off" required marked hint="Updates and sign-in details are sent here." />
                <x-text-input wire:model="mobile" name="mobile" type="tel" label="Mobile (optional)" autocomplete="off" placeholder="0300 1234567" />
                <x-text-input wire:model="joining_date" name="joining_date" type="date" label="Joining date" />
            </div>

            @if ($applications->isNotEmpty())
                <fieldset>
                    <legend class="text-sm font-medium text-zinc-800 dark:text-zinc-200">Applications they’ll need <span class="font-normal text-zinc-500">(optional)</span></legend>
                    <div class="mt-3 grid gap-2 sm:grid-cols-2">
                        @foreach ($applications as $app)
                            <label class="flex cursor-pointer items-center gap-3 rounded-xl p-3 text-sm ring-1 ring-zinc-200 transition has-[:checked]:bg-primary-50 has-[:checked]:ring-primary-300 dark:ring-zinc-700 dark:has-[:checked]:bg-primary-950/40 dark:has-[:checked]:ring-primary-800">
                                <input type="checkbox" wire:model="application_ids" value="{{ $app->id }}" class="size-4 accent-primary-600"> {{ $app->name }}
                            </label>
                        @endforeach
                    </div>
                    @error('application_ids') <p class="mt-2 text-sm text-primary-700 dark:text-primary-400" role="alert">{{ $message }}</p> @enderror
                </fieldset>
            @endif

            <div>
                <label for="justification" class="block text-sm font-medium text-zinc-800 dark:text-zinc-200">Anything else the helpdesk should know? <span class="font-normal text-zinc-500">(optional)</span></label>
                <textarea wire:model="justification" id="justification" rows="3" maxlength="1000"
                          class="mt-2 block w-full rounded-xl border-0 bg-white p-3 text-sm shadow-sm ring-1 ring-zinc-300 focus:outline-none focus:ring-2 focus:ring-primary-600 dark:bg-zinc-900 dark:ring-zinc-700"></textarea>
            </div>

            <div class="flex justify-end">
                <x-primary-button target="submit" loading="Sending…" class="sm:!w-auto">Send request</x-primary-button>
            </div>
        </form>
    @endif
</div>
