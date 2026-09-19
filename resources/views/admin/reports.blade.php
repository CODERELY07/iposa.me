@php
    // Static demo ledger for 1–18 Sep 2026, generated deterministically so totals always add up.
    $expensesPaidOn = [1 => 18000, 12 => 1699, 15 => 23340, 17 => 1920, 18 => 1850];

    $ledger = $ledger ?? collect(range(1, 18))->map(function (int $day) use ($expensesPaidOn) {
        $date = \Illuminate\Support\Carbon::create(2026, 9, $day);
        $weekendBoost = $date->isWeekend() ? 7200 : ($date->isFriday() ? 3100 : 0);
        $sales = 12400 + (($day * 3779) % 3900) + $weekendBoost;

        return [
            'date' => $date,
            'orders' => (int) round($sales / 146),
            'sales' => $sales,
            'cogs' => round($sales * 0.31),
            'bulk' => 290 + (($day * 53) % 160),
            'expenses' => $expensesPaidOn[$day] ?? 0,
        ];
    })->map(fn (array $row) => [...$row, 'net' => $row['sales'] - $row['cogs'] - $row['bulk'] - $row['expenses']])->reverse()->values();

    $totals = [
        'orders' => $ledger->sum('orders'),
        'sales' => $ledger->sum('sales'),
        'cogs' => $ledger->sum('cogs'),
        'bulk' => $ledger->sum('bulk'),
        'expenses' => $ledger->sum('expenses'),
        'net' => $ledger->sum('net'),
    ];
    $payables = 12881.66;
    $running = 0;
    $netAfterPayables = $totals['net'] - $payables;

    $waterfall = [
        ['label' => 'Gross revenue', 'amount' => $totals['sales'], 'kind' => 'total', 'note' => number_format($totals['orders']).' orders from the register'],
        ['label' => 'Ingredients (COGS)', 'amount' => -$totals['cogs'], 'kind' => 'cost', 'note' => 'Auto: linked pieces × sales'],
        ['label' => 'Bulk & liquids', 'amount' => -$totals['bulk'], 'kind' => 'cost', 'note' => 'From 18 closing audits'],
        ['label' => 'Operating expenses', 'amount' => -$totals['expenses'], 'kind' => 'cost', 'note' => 'Rent, wages, utilities, supplies'],
        ['label' => 'Equipment payables', 'amount' => -$payables, 'kind' => 'cost', 'note' => '3 installments due this month'],
        ['label' => 'Net profit', 'amount' => $netAfterPayables, 'kind' => 'result', 'note' => number_format($netAfterPayables / $totals['sales'] * 100, 1).'% of revenue'],
    ];
@endphp

<x-app-layout title="Profit & ledger">
    <div class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-8">
        <x-page-header eyebrow="Profit & loss" title="September, day by day"
            description="Same columns as your spreadsheet. Nothing typed twice: sales come from the register, ingredient costs from recipes, bulk from closing audits.">
            <x-slot:actions>
                <div class="inline-flex gap-1 rounded-xl bg-ink-100 p-1 dark:bg-white/[0.05]">
                    <button type="button" class="tab">Week</button>
                    <button type="button" class="tab tab-active">Month</button>
                    <button type="button" class="tab">Custom</button>
                </div>
                <button type="button" class="btn-primary"><x-icon name="download" class="size-4" /> Export to Excel</button>
            </x-slot:actions>
        </x-page-header>

        {{-- P&L waterfall --}}
        <section class="surface p-6 lg:p-8">
            <div class="grid gap-8 lg:grid-cols-[280px_1fr]">
                <div>
                    <p class="eyebrow">Net profit · Sep 1–18</p>
                    <p @class(['num mt-2 text-4xl font-semibold tracking-tight', 'text-loss-600 dark:text-loss-400' => $netAfterPayables < 0])>₱{{ number_format($netAfterPayables, 2) }}</p>
                    <p class="mt-2 text-sm text-ink-500">Revenue minus every cost, including equipment installments.</p>
                    <dl class="mt-6 space-y-2 border-t border-ink-100 pt-4 text-sm dark:border-white/[0.06]">
                        <div class="flex justify-between"><dt class="text-ink-500">Avg per day</dt><dd class="num">₱{{ number_format($netAfterPayables / 18, 2) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-ink-500">Best day</dt><dd class="num">{{ $ledger->sortByDesc('net')->first()['date']->format('D d') }} · ₱{{ number_format($ledger->max('net'), 0) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-ink-500">Food cost %</dt><dd class="num">{{ number_format(($totals['cogs'] + $totals['bulk']) / $totals['sales'] * 100, 1) }}%</dd></div>
                    </dl>
                </div>

                <ol class="space-y-3">
                    @foreach ($waterfall as $step)
                        @php
                            $width = abs($step['amount']) / $totals['sales'] * 100;
                            $offset = match ($step['kind']) {
                                'cost' => ($running + $step['amount']) / $totals['sales'] * 100,
                                default => 0,
                            };
                            $running = $step['kind'] === 'total' ? $step['amount'] : ($step['kind'] === 'cost' ? $running + $step['amount'] : $running);
                        @endphp
                        <li class="grid grid-cols-[1fr_auto] gap-x-4 gap-y-1.5 sm:grid-cols-[180px_1fr_120px] sm:items-center">
                            <div>
                                <p @class(['text-sm', 'font-semibold' => $step['kind'] !== 'cost'])>{{ $step['label'] }}</p>
                                <p class="text-[11px] text-ink-400">{{ $step['note'] }}</p>
                            </div>
                            <p @class(['num text-right text-sm sm:order-last', 'font-semibold' => $step['kind'] !== 'cost', 'text-ink-500' => $step['kind'] === 'cost'])>
                                {{ $step['amount'] < 0 ? '−' : '' }}₱{{ number_format(abs($step['amount']), 2) }}
                            </p>
                            <div class="col-span-2 h-7 rounded-md bg-ink-100 sm:col-span-1 dark:bg-white/[0.04]">
                                <div @class([
                                    'h-full rounded-md',
                                    'bg-ink-400 dark:bg-ink-500' => $step['kind'] === 'total',
                                    'bg-loss-400/70' => $step['kind'] === 'cost',
                                    'bg-gain-500' => $step['kind'] === 'result',
                                ]) style="width: {{ max(0.5, $width) }}%; margin-left: {{ max(0, $offset) }}%"></div>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>

        {{-- Ledger --}}
        <section class="surface overflow-hidden">
            <div class="flex items-center justify-between border-b border-ink-200 px-5 py-4 dark:border-white/[0.07]">
                <div>
                    <h2 class="font-semibold">Daily ledger</h2>
                    <p class="text-xs text-ink-500">Click a day to see every order and expense behind it.</p>
                </div>
                <button type="button" class="btn-ghost py-2"><x-icon name="download" class="size-4" /> CSV</button>
            </div>
            <div class="max-h-[560px] overflow-auto">
                <table class="w-full min-w-[820px] text-sm">
                    <thead class="table-head">
                        <tr class="border-b border-ink-200 dark:border-white/[0.07]">
                            <th class="px-5 py-3 font-semibold">Date</th>
                            <th class="px-3 py-3 text-right font-semibold">Orders</th>
                            <th class="px-3 py-3 text-right font-semibold">Sales</th>
                            <th class="px-3 py-3 text-right font-semibold">Ingredients</th>
                            <th class="px-3 py-3 text-right font-semibold">Bulk</th>
                            <th class="px-3 py-3 text-right font-semibold">Expenses</th>
                            <th class="px-3 py-3 text-right font-semibold">Net</th>
                            <th class="px-5 py-3 text-right font-semibold">Margin</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink-100 dark:divide-white/[0.05]">
                        @foreach ($ledger as $row)
                            <tr class="cursor-pointer hover:bg-ink-50 dark:hover:bg-white/[0.02]">
                                <td class="whitespace-nowrap px-5 py-2.5">
                                    <span class="num">{{ $row['date']->format('D d') }}</span>
                                    @if ($row['date']->isWeekend())<span class="ml-1 text-[10px] uppercase tracking-wider text-ink-400">wknd</span>@endif
                                </td>
                                <td class="num px-3 py-2.5 text-right text-ink-500">{{ $row['orders'] }}</td>
                                <td class="num px-3 py-2.5 text-right">{{ number_format($row['sales'], 2) }}</td>
                                <td class="num px-3 py-2.5 text-right text-ink-500">{{ number_format($row['cogs'], 2) }}</td>
                                <td class="num px-3 py-2.5 text-right text-ink-500">{{ number_format($row['bulk'], 2) }}</td>
                                <td class="num px-3 py-2.5 text-right text-ink-500">{{ $row['expenses'] ? number_format($row['expenses'], 2) : '—' }}</td>
                                <td @class(['num px-3 py-2.5 text-right font-semibold', 'text-loss-600 dark:text-loss-400' => $row['net'] < 0])>{{ $row['net'] < 0 ? '(' . number_format(abs($row['net']), 2) . ')' : number_format($row['net'], 2) }}</td>
                                <td class="num px-5 py-2.5 text-right text-ink-500">{{ number_format($row['net'] / $row['sales'] * 100, 1) }}%</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="sticky bottom-0 bg-white dark:bg-ink-900">
                        <tr class="border-t-2 border-ink-200 font-semibold dark:border-white/10">
                            <td class="px-5 py-3">Total</td>
                            <td class="num px-3 py-3 text-right">{{ number_format($totals['orders']) }}</td>
                            <td class="num px-3 py-3 text-right">{{ number_format($totals['sales'], 2) }}</td>
                            <td class="num px-3 py-3 text-right">{{ number_format($totals['cogs'], 2) }}</td>
                            <td class="num px-3 py-3 text-right">{{ number_format($totals['bulk'], 2) }}</td>
                            <td class="num px-3 py-3 text-right">{{ number_format($totals['expenses'], 2) }}</td>
                            <td class="num px-3 py-3 text-right">{{ number_format($totals['net'], 2) }}</td>
                            <td class="num px-5 py-3 text-right">{{ number_format($totals['net'] / $totals['sales'] * 100, 1) }}%</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <p class="border-t border-ink-100 px-5 py-3 text-[11px] text-ink-400 dark:border-white/[0.05]">All amounts in ₱. Losses shown in (brackets), like accounting sheets. Equipment payables are applied monthly, not per day.</p>
        </section>
    </div>
</x-app-layout>
