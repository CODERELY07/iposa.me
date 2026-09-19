@php
    $plans = $plans ?? [
        ['name' => 'Tindahan', 'price' => 499, 'pitch' => 'Register, inventory, closing audit, daily sales', 'current' => false],
        ['name' => 'Negosyo', 'price' => 999, 'pitch' => 'Everything + expenses, P&L ledger, equipment payables, unlimited staff', 'current' => true],
    ];
@endphp

<x-app-layout title="Settings">
    <div class="mx-auto max-w-5xl space-y-10 px-4 py-8 sm:px-8">
        <x-page-header eyebrow="Settings" title="Your shop" />

        <section class="grid gap-6 lg:grid-cols-[240px_1fr]">
            <div>
                <h2 class="font-semibold">Business</h2>
                <p class="mt-1 text-sm text-ink-500">Shows on receipts and exports.</p>
            </div>
            <form @submit.prevent class="surface grid gap-5 p-6 sm:grid-cols-2">
                <div>
                    <label class="field-label" for="business_name">Business name</label>
                    <input id="business_name" type="text" value="Kape't Burger" class="field">
                </div>
                <div>
                    <label class="field-label" for="business_type">Type</label>
                    <select id="business_type" class="field">
                        <option>Burger & fast food</option>
                        <option>Café / coffee shop</option>
                        <option>Milk tea & drinks</option>
                        <option>Carinderia / eatery</option>
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label class="field-label" for="address">Address</label>
                    <input id="address" type="text" value="J.P. Rizal St., Sto. Niño, Marikina City" class="field">
                </div>
                <div>
                    <label class="field-label" for="tin">TIN <span class="text-ink-400">(optional)</span></label>
                    <input id="tin" type="text" class="field num" placeholder="000-000-000-000">
                </div>
                <div>
                    <label class="field-label" for="receipt_footer">Receipt footer</label>
                    <input id="receipt_footer" type="text" value="Salamat po! Balik kayo 🍔" class="field">
                </div>
                <div class="sm:col-span-2 sm:text-right">
                    <button type="submit" class="btn-primary">Save</button>
                </div>
            </form>
        </section>

        <section class="grid gap-6 lg:grid-cols-[240px_1fr]">
            <div>
                <h2 class="font-semibold">Register & closing</h2>
                <p class="mt-1 text-sm text-ink-500">How the counter works day to day.</p>
            </div>
            <div class="surface grid gap-5 p-6 sm:grid-cols-2">
                <fieldset>
                    <legend class="field-label">Payment methods</legend>
                    <div class="space-y-2">
                        @foreach (['Cash' => true, 'GCash' => true, 'Maya' => true, 'Card' => false] as $method => $isEnabled)
                            <label class="flex items-center gap-2.5 text-sm">
                                <input type="checkbox" @checked($isEnabled) class="rounded border-ink-300 text-brand-500 focus:ring-brand-400 dark:border-white/20 dark:bg-ink-900">
                                {{ $method }}
                            </label>
                        @endforeach
                    </div>
                </fieldset>
                <div class="space-y-5">
                    <div>
                        <label class="field-label" for="closing_time">Remind staff to audit at</label>
                        <input id="closing_time" type="time" value="21:30" class="field num">
                    </div>
                    <div>
                        <label class="field-label" for="default_threshold">Default low-stock alert</label>
                        <input id="default_threshold" type="number" value="20" class="field num">
                    </div>
                </div>
            </div>
        </section>

        <section id="billing" class="grid gap-6 lg:grid-cols-[240px_1fr]">
            <div>
                <h2 class="font-semibold">Plan & billing</h2>
                <p class="mt-1 text-sm text-ink-500">Trial ends Sep 27. No card on file yet.</p>
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach ($plans as $plan)
                    <div @class(['rounded-2xl border p-5', 'border-brand-400 bg-brand-400/[0.06]' => $plan['current'], 'border-ink-200 dark:border-white/[0.07]' => ! $plan['current']])>
                        <div class="flex items-center justify-between">
                            <p class="font-semibold">{{ $plan['name'] }}</p>
                            @if ($plan['current'])<span class="pill bg-brand-400 text-ink-950">On trial</span>@endif
                        </div>
                        <p class="mt-2"><span class="num text-2xl font-semibold">₱{{ number_format($plan['price']) }}</span><span class="text-sm text-ink-500"> / month</span></p>
                        <p class="mt-2 text-sm text-ink-500">{{ $plan['pitch'] }}</p>
                        <button type="button" @class(['mt-4 w-full', 'btn-primary' => $plan['current'], 'btn-ghost' => ! $plan['current']])>
                            {{ $plan['current'] ? 'Keep Negosyo · add GCash or card' : 'Switch to Tindahan' }}
                        </button>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="grid gap-6 lg:grid-cols-[240px_1fr]">
            <div>
                <h2 class="font-semibold">Your data</h2>
                <p class="mt-1 text-sm text-ink-500">It's yours. Take it anywhere.</p>
            </div>
            <div class="surface flex flex-col gap-4 p-6 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm font-medium">Download everything as Excel</p>
                    <p class="text-xs text-ink-500">Menu & prices, stock, audits, expenses, daily ledger. One workbook, one sheet each.</p>
                </div>
                <button type="button" class="btn-ghost shrink-0"><x-icon name="download" class="size-4" /> Download .xlsx</button>
            </div>
        </section>
    </div>
</x-app-layout>
