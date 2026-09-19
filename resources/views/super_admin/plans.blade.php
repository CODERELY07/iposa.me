@php
    $plans = $plans ?? [
        ['name' => 'Tindahan', 'price' => 499, 'subscribers' => 38, 'staff' => '3 staff', 'features' => ['Register (POS)', 'Inventory: menu, pieces, bulk', 'Closing audit', 'Daily & weekly sales', 'CSV / Excel export']],
        ['name' => 'Negosyo', 'price' => 999, 'subscribers' => 66, 'staff' => 'Unlimited staff', 'features' => ['Everything in Tindahan', 'Ingredient links (recipes)', 'Expenses & equipment payables', 'P&L and daily ledger', 'Priority support on Viber']],
    ];

    $payments = $payments ?? [
        ['business' => 'Boba Lab', 'plan' => 'Negosyo', 'amount' => 999, 'method' => 'GCash', 'date' => 'Sep 18', 'status' => 'Paid'],
        ['business' => 'Pandesal Republic', 'plan' => 'Tindahan', 'amount' => 499, 'method' => 'Card', 'date' => 'Sep 18', 'status' => 'Paid'],
        ['business' => 'Kapihan sa Kanto', 'plan' => 'Negosyo', 'amount' => 999, 'method' => 'Maya', 'date' => 'Sep 17', 'status' => 'Paid'],
        ['business' => 'Milky Way Tea', 'plan' => 'Negosyo', 'amount' => 999, 'method' => 'Card', 'date' => 'Sep 15', 'status' => 'Failed'],
        ['business' => "Tita Nena's Eatery", 'plan' => 'Tindahan', 'amount' => 499, 'method' => 'GCash', 'date' => 'Sep 14', 'status' => 'Paid'],
    ];
@endphp

<x-app-layout title="Plans & billing">
    <div class="mx-auto max-w-6xl space-y-8 px-4 py-8 sm:px-8">
        <x-page-header eyebrow="Billing" title="Plans & billing"
            description="Two plans, one branch each. Export stays on every plan: it's the promise that wins people over from Excel.">
            <x-slot:actions>
                <button type="button" class="btn-ghost"><x-icon name="plus" class="size-4" /> New plan</button>
            </x-slot:actions>
        </x-page-header>

        <div class="grid gap-4 md:grid-cols-2">
            @foreach ($plans as $plan)
                <section class="surface p-6">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="font-semibold">{{ $plan['name'] }}</p>
                            <p class="mt-1"><span class="num text-3xl font-semibold">₱{{ number_format($plan['price']) }}</span><span class="text-sm text-ink-500"> / month</span></p>
                        </div>
                        <div class="text-right">
                            <p class="num text-xl font-semibold">{{ $plan['subscribers'] }}</p>
                            <p class="text-xs text-ink-500">paying</p>
                        </div>
                    </div>
                    <p class="num mt-4 text-xs text-ink-500">₱{{ number_format($plan['price'] * $plan['subscribers']) }} MRR · {{ $plan['staff'] }} · 14-day trial</p>
                    <ul class="mt-5 space-y-2 border-t border-ink-100 pt-5 text-sm dark:border-white/[0.06]">
                        @foreach ($plan['features'] as $feature)
                            <li class="flex items-center gap-2"><x-icon name="check" class="size-4 text-gain-500" /> {{ $feature }}</li>
                        @endforeach
                    </ul>
                    <button type="button" class="btn-ghost mt-6 w-full">Edit plan</button>
                </section>
            @endforeach
        </div>

        <section class="surface overflow-hidden">
            <div class="border-b border-ink-200 px-5 py-4 dark:border-white/[0.07]">
                <h2 class="font-semibold">Recent payments</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[640px] text-sm">
                    <thead class="table-head">
                        <tr class="border-b border-ink-200 dark:border-white/[0.07]">
                            <th class="px-5 py-3 font-semibold">Business</th>
                            <th class="px-3 py-3 font-semibold">Plan</th>
                            <th class="px-3 py-3 font-semibold">Method</th>
                            <th class="px-3 py-3 font-semibold">Date</th>
                            <th class="px-3 py-3 font-semibold">Status</th>
                            <th class="px-5 py-3 text-right font-semibold">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink-100 dark:divide-white/[0.05]">
                        @foreach ($payments as $payment)
                            <tr>
                                <td class="px-5 py-3 font-medium">{{ $payment['business'] }}</td>
                                <td class="px-3 py-3 text-ink-500">{{ $payment['plan'] }}</td>
                                <td class="px-3 py-3 text-ink-500">{{ $payment['method'] }}</td>
                                <td class="num px-3 py-3 text-ink-500">{{ $payment['date'] }}</td>
                                <td class="px-3 py-3">
                                    <span @class(['pill', 'bg-gain-500/15 text-gain-700 dark:text-gain-300' => $payment['status'] === 'Paid', 'bg-loss-500/15 text-loss-700 dark:text-loss-300' => $payment['status'] !== 'Paid'])>{{ $payment['status'] }}</span>
                                </td>
                                <td class="num px-5 py-3 text-right">₱{{ number_format($payment['amount'], 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-app-layout>
