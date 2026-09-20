@use('App\Models\Plan')

@php
    $isNew = ! $plan->exists;
    $featureList = old('feature_list', implode("\n", $plan->feature_list ?? []));
    $staffLimit = old('staff_limit', $plan->staff_limit);
    $unlimited = old('unlimited_staff', $plan->exists && $plan->staff_limit === null) ? true : false;
@endphp

<x-app-layout :title="$isNew ? 'New plan' : 'Edit '.$plan->name">
    <div class="mx-auto max-w-3xl space-y-8 px-4 py-8 sm:px-8">
        <x-page-header eyebrow="Billing" :title="$isNew ? 'New plan' : 'Edit '.$plan->name"
            :description="$isNew
                ? 'The key is what every business record stores, so it can\'t be changed later.'
                : 'Changing the price never changes what shops already pay: their price is locked until their next renewal.'">
            <x-slot:actions>
                <a href="{{ route('super_admin.plans') }}" class="btn-ghost">Back to plans</a>
            </x-slot:actions>
        </x-page-header>

        @if (! $isNew && $subscriberCount > 0)
            <p class="rounded-2xl bg-sky-500/10 px-4 py-3 text-sm text-sky-800 dark:text-sky-200">
                <span class="num font-semibold">{{ $subscriberCount }}</span>
                {{ \Illuminate\Support\Str::plural('shop', $subscriberCount) }} on this plan.
                A new price applies to each of them at their next renewal.
            </p>
        @endif

        <form method="POST" action="{{ $isNew ? route('super_admin.plans.store') : route('super_admin.plans.update', $plan) }}" class="surface space-y-6 p-6">
            @csrf
            @unless ($isNew) @method('PUT') @endunless

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="field-label" for="name">Plan name</label>
                    <input id="name" name="name" type="text" required maxlength="60" class="field" value="{{ old('name', $plan->name) }}" placeholder="e.g. Negosyo">
                    <x-input-error :messages="$errors->get('name')" class="mt-1" />
                </div>

                <div>
                    <label class="field-label" for="key">Key</label>
                    @if ($isNew)
                        <input id="key" name="key" type="text" maxlength="40" class="field font-mono" value="{{ old('key') }}" placeholder="negosyo (left empty: made from the name)">
                        <p class="mt-1 text-xs text-ink-500">Lowercase letters, numbers and dashes. Permanent.</p>
                    @else
                        <input id="key" type="text" class="field font-mono" value="{{ $plan->key }}" disabled>
                        <p class="mt-1 text-xs text-ink-500">Stored on every business on this plan, so it can't change.</p>
                    @endif
                    <x-input-error :messages="$errors->get('key')" class="mt-1" />
                </div>

                <div>
                    <label class="field-label" for="price">Price per month (₱)</label>
                    <input id="price" name="price" type="number" inputmode="decimal" min="0" max="999999" step="0.01" required class="field num" value="{{ old('price', $plan->exists ? (float) $plan->price : '') }}">
                    <x-input-error :messages="$errors->get('price')" class="mt-1" />
                </div>

                <div x-data="{ unlimited: {{ $unlimited ? 'true' : 'false' }} }">
                    <label class="field-label" for="staff_limit">Staff limit</label>
                    <input id="staff_limit" name="staff_limit" type="number" min="1" max="999" class="field num" value="{{ $staffLimit }}" x-bind:disabled="unlimited" placeholder="3">
                    <label class="mt-2 flex items-center gap-2 text-sm text-ink-600 dark:text-ink-300">
                        <input type="checkbox" name="unlimited_staff" value="1" x-model="unlimited" class="size-4 rounded border-ink-300 text-brand-500 focus:ring-brand-400 dark:border-white/20 dark:bg-white/[0.06]">
                        Unlimited staff
                    </label>
                    <x-input-error :messages="$errors->get('staff_limit')" class="mt-1" />
                </div>
            </div>

            <div>
                <label class="field-label" for="pitch">One-line pitch</label>
                <input id="pitch" name="pitch" type="text" maxlength="160" class="field" value="{{ old('pitch', $plan->pitch) }}" placeholder="Register, inventory, closing audit, daily sales">
                <x-input-error :messages="$errors->get('pitch')" class="mt-1" />
            </div>

            <fieldset class="border-t border-ink-100 pt-5 dark:border-white/[0.06]">
                <legend class="field-label">Modules this plan unlocks</legend>
                <p class="mb-3 text-xs text-ink-500">These switch real screens on and off. The register, inventory, closing audit and CSV export are on every plan.</p>

                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach (Plan::FEATURES as $feature => $label)
                        <label class="flex items-start gap-3 rounded-xl border border-ink-200 px-4 py-3 text-sm dark:border-white/10">
                            <input type="hidden" name="features[{{ $feature }}]" value="0">
                            <input type="checkbox" name="features[{{ $feature }}]" value="1" class="mt-0.5 size-4 rounded border-ink-300 text-brand-500 focus:ring-brand-400 dark:border-white/20 dark:bg-white/[0.06]"
                                @checked(old('features.'.$feature, $plan->hasFeature($feature)))>
                            <span>{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <div>
                <label class="field-label" for="feature_list">What owners see on the pricing card</label>
                <textarea id="feature_list" name="feature_list" rows="6" maxlength="2000" class="field" placeholder="One line per bullet">{{ $featureList }}</textarea>
                <p class="mt-1 text-xs text-ink-500">One line per bullet. Shown in Settings → Plan &amp; billing.</p>
                <x-input-error :messages="$errors->get('feature_list')" class="mt-1" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="field-label" for="sort">Order on the pricing cards</label>
                    <input id="sort" name="sort" type="number" min="0" max="999" class="field num" value="{{ old('sort', $plan->sort ?? 0) }}">
                    <x-input-error :messages="$errors->get('sort')" class="mt-1" />
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 border-t border-ink-100 pt-5 dark:border-white/[0.06]">
                <a href="{{ route('super_admin.plans') }}" class="btn-quiet">Cancel</a>
                <button type="submit" class="btn-primary" data-loading-text="Saving…">{{ $isNew ? 'Create plan' : 'Save plan' }}</button>
            </div>
        </form>
    </div>
</x-app-layout>
