@php
    $categories = [
        'Utilities' => 'bg-sky-400',
        'Rent' => 'bg-violet-400',
        'Wages' => 'bg-brand-400',
        'Supplies' => 'bg-yellow-300',
        'Stock purchase' => 'bg-rose-400',
        'Misc' => 'bg-ink-400',
    ];

    $expenses = $expenses ?? [
        ['date' => 'Fri 18 Sep', 'category' => 'Supplies', 'description' => 'LPG refill 11kg', 'kind' => 'Variable', 'amount' => 1050.00, 'by' => 'Maria'],
        ['date' => 'Fri 18 Sep', 'category' => 'Supplies', 'description' => 'Ice, 3 sacks', 'kind' => 'Variable', 'amount' => 300.00, 'by' => 'Jessa'],
        ['date' => 'Fri 18 Sep', 'category' => 'Wages', 'description' => 'Part-timer, 1 day', 'kind' => 'Variable', 'amount' => 500.00, 'by' => 'Maria'],
        ['date' => 'Thu 17 Sep', 'category' => 'Stock purchase', 'description' => 'Buns ×200 · Pan de Manila', 'kind' => 'Variable', 'amount' => 1500.00, 'by' => 'Maria'],
        ['date' => 'Thu 17 Sep', 'category' => 'Misc', 'description' => 'Paper bags, tissue', 'kind' => 'Variable', 'amount' => 420.00, 'by' => 'Jessa'],
        ['date' => 'Tue 15 Sep', 'category' => 'Wages', 'description' => 'Payroll · 3 staff, 1st half', 'kind' => 'Fixed', 'amount' => 16500.00, 'by' => 'Maria'],
        ['date' => 'Tue 15 Sep', 'category' => 'Utilities', 'description' => 'Meralco · August', 'kind' => 'Fixed', 'amount' => 6840.00, 'by' => 'Maria'],
        ['date' => 'Sat 12 Sep', 'category' => 'Utilities', 'description' => 'PLDT fiber', 'kind' => 'Fixed', 'amount' => 1699.00, 'by' => 'Maria'],
        ['date' => 'Tue 1 Sep', 'category' => 'Rent', 'description' => 'Stall rent · September', 'kind' => 'Fixed', 'amount' => 18000.00, 'by' => 'Maria'],
    ];

    $monthByCategory = collect($expenses)->groupBy('category')->map->sum('amount')->sortDesc();
    $monthTotal = $monthByCategory->sum();

    $assets = $assets ?? [
        ['name' => 'Espresso machine', 'vendor' => 'Wellcraft PH', 'price' => 85000.00, 'installment' => 7083.33, 'paid' => 6, 'terms' => 12, 'nextDue' => 'Oct 5'],
        ['name' => 'Chest freezer 9 cu.ft.', 'vendor' => 'Abenson', 'price' => 24990.00, 'installment' => 4165.00, 'paid' => 5, 'terms' => 6, 'nextDue' => 'Sep 28'],
        ['name' => 'Commercial griddle', 'vendor' => 'Cash', 'price' => 12500.00, 'installment' => null, 'paid' => 1, 'terms' => 1, 'nextDue' => null],
        ['name' => 'Blender ×2', 'vendor' => 'Home Credit', 'price' => 9800.00, 'installment' => 1633.33, 'paid' => 2, 'terms' => 6, 'nextDue' => 'Oct 2'],
    ];
@endphp

<x-app-layout title="Expenses">
    <div x-data="{ tab: 'operating' }" class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-8">
        <x-page-header eyebrow="September 2026" title="Expenses"
            description="Log it the way you did in Excel: date, category, amount. It flows straight into your profit.">
            <x-slot:actions>
                <select class="field w-auto py-2" aria-label="Month">
                    <option>September 2026</option>
                    <option>August 2026</option>
                    <option>July 2026</option>
                </select>
                <button type="button" class="btn-ghost"><x-icon name="download" class="size-4" /> Export CSV</button>
            </x-slot:actions>
        </x-page-header>

        <div class="inline-flex gap-1 rounded-xl bg-ink-100 p-1 dark:bg-white/[0.05]">
            <button type="button" @click="tab = 'operating'" :class="tab === 'operating' ? 'tab-active' : ''" class="tab">Daily expenses</button>
            <button type="button" @click="tab = 'assets'" :class="tab === 'assets' ? 'tab-active' : ''" class="tab">Equipment & payables</button>
        </div>

        <div x-show="tab === 'operating'" class="grid gap-6 lg:grid-cols-[1fr_300px]">
            <div class="space-y-4">
                {{-- Quick add: one spreadsheet row --}}
                <form @submit.prevent class="surface grid gap-3 p-4 sm:grid-cols-[140px_160px_1fr_140px_auto] sm:items-end">
                    <div>
                        <label class="field-label" for="expense_date">Date</label>
                        <input id="expense_date" type="date" value="2026-09-18" class="field num">
                    </div>
                    <div>
                        <label class="field-label" for="expense_category">Category</label>
                        <select id="expense_category" class="field">
                            @foreach (array_keys($categories) as $category)
                                <option>{{ $category }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="field-label" for="expense_description">What for</label>
                        <input id="expense_description" type="text" class="field" placeholder="e.g. Ice, 3 sacks">
                    </div>
                    <div>
                        <label class="field-label" for="expense_amount">Amount</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-ink-400">₱</span>
                            <input id="expense_amount" type="number" step="0.01" min="0" class="field num pl-7 text-right" placeholder="0.00">
                        </div>
                    </div>
                    <button type="submit" class="btn-primary"><x-icon name="plus" class="size-4" /> Add</button>
                </form>

                <div class="surface overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[680px] text-sm">
                            <thead class="table-head">
                                <tr class="border-b border-ink-200 dark:border-white/[0.07]">
                                    <th class="px-5 py-3 font-semibold">Date</th>
                                    <th class="px-3 py-3 font-semibold">Category</th>
                                    <th class="px-3 py-3 font-semibold">Description</th>
                                    <th class="px-3 py-3 font-semibold">Type</th>
                                    <th class="px-5 py-3 text-right font-semibold">Amount</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-ink-100 dark:divide-white/[0.05]">
                                @foreach ($expenses as $expense)
                                    <tr class="hover:bg-ink-50 dark:hover:bg-white/[0.02]">
                                        <td class="num whitespace-nowrap px-5 py-3 text-ink-500">{{ $expense['date'] }}</td>
                                        <td class="px-3 py-3">
                                            <span class="inline-flex items-center gap-2">
                                                <span class="size-2 rounded-full {{ $categories[$expense['category']] }}"></span>{{ $expense['category'] }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-3">
                                            {{ $expense['description'] }}
                                            <span class="text-xs text-ink-400">· {{ $expense['by'] }}</span>
                                        </td>
                                        <td class="px-3 py-3 text-xs text-ink-500">{{ $expense['kind'] }}</td>
                                        <td class="num px-5 py-3 text-right font-medium">₱{{ number_format($expense['amount'], 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="border-t border-ink-200 dark:border-white/[0.07]">
                                    <td colspan="4" class="px-5 py-3 text-sm font-semibold">September so far</td>
                                    <td class="num px-5 py-3 text-right text-base font-semibold">₱{{ number_format($monthTotal, 2) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <aside class="surface h-fit p-5">
                <p class="eyebrow">Where it went</p>
                <p class="num mt-2 text-2xl font-semibold">₱{{ number_format($monthTotal, 2) }}</p>
                <div class="mt-4 flex h-2 overflow-hidden rounded-full">
                    @foreach ($monthByCategory as $category => $amount)
                        <div class="{{ $categories[$category] }}" style="width: {{ $amount / $monthTotal * 100 }}%"></div>
                    @endforeach
                </div>
                <ul class="mt-5 space-y-3 text-sm">
                    @foreach ($monthByCategory as $category => $amount)
                        <li class="flex items-center justify-between gap-3">
                            <span class="flex items-center gap-2"><span class="size-2 rounded-full {{ $categories[$category] }}"></span>{{ $category }}</span>
                            <span class="num text-ink-600 dark:text-ink-300">₱{{ number_format($amount, 2) }}</span>
                        </li>
                    @endforeach
                </ul>
                <p class="mt-5 border-t border-ink-100 pt-4 text-xs text-ink-500 dark:border-white/[0.06]">Ingredient costs aren't logged here. They're counted automatically from sales and the closing audit.</p>
            </aside>
        </div>

        <div x-show="tab === 'assets'" x-cloak class="space-y-4">
            <div class="grid gap-4 sm:grid-cols-3">
                <div class="surface p-5">
                    <p class="text-xs text-ink-500">Equipment value</p>
                    <p class="num mt-1 text-xl font-semibold">₱{{ number_format(array_sum(array_column($assets, 'price')), 2) }}</p>
                </div>
                <div class="surface p-5">
                    <p class="text-xs text-ink-500">Payables this month</p>
                    <p class="num mt-1 text-xl font-semibold">₱{{ number_format(7083.33 + 4165.00 + 1633.33, 2) }}</p>
                </div>
                <div class="surface p-5">
                    <p class="text-xs text-ink-500">Next due</p>
                    <p class="mt-1 text-xl font-semibold">Sep 28 <span class="text-sm font-normal text-ink-500">· Chest freezer</span></p>
                </div>
            </div>

            <div class="surface overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[720px] text-sm">
                        <thead class="table-head">
                            <tr class="border-b border-ink-200 dark:border-white/[0.07]">
                                <th class="px-5 py-3 font-semibold">Equipment</th>
                                <th class="px-3 py-3 text-right font-semibold">Price</th>
                                <th class="px-3 py-3 text-right font-semibold">Monthly</th>
                                <th class="px-3 py-3 font-semibold">Paid</th>
                                <th class="px-5 py-3 font-semibold">Next due</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ink-100 dark:divide-white/[0.05]">
                            @foreach ($assets as $asset)
                                <tr class="hover:bg-ink-50 dark:hover:bg-white/[0.02]">
                                    <td class="px-5 py-3">
                                        <p class="font-medium">{{ $asset['name'] }}</p>
                                        <p class="text-xs text-ink-500">{{ $asset['vendor'] }}</p>
                                    </td>
                                    <td class="num px-3 py-3 text-right">₱{{ number_format($asset['price'], 2) }}</td>
                                    <td class="num px-3 py-3 text-right text-ink-500">{{ $asset['installment'] ? '₱'.number_format($asset['installment'], 2) : '—' }}</td>
                                    <td class="px-3 py-3">
                                        <div class="flex items-center gap-3">
                                            <div class="flex gap-0.5">
                                                @for ($month = 1; $month <= $asset['terms']; $month++)
                                                    <span @class(['h-3 w-1.5 rounded-sm', 'bg-gain-500' => $month <= $asset['paid'], 'bg-ink-200 dark:bg-white/10' => $month > $asset['paid']])></span>
                                                @endfor
                                            </div>
                                            <span class="num text-xs text-ink-500">{{ $asset['paid'] }}/{{ $asset['terms'] }}</span>
                                        </div>
                                    </td>
                                    <td class="px-5 py-3">
                                        @if ($asset['nextDue'])
                                            <span class="pill bg-brand-400/15 text-brand-700 dark:text-brand-300">{{ $asset['nextDue'] }}</span>
                                        @else
                                            <span class="pill bg-gain-500/15 text-gain-700 dark:text-gain-300">Fully paid</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <button type="button" class="btn-ghost"><x-icon name="plus" class="size-4" /> Add equipment</button>
        </div>
    </div>
</x-app-layout>
