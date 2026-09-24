@php
    $sales = $totals['sales'];
    $operatingExpenses = round($totals['expenses'] - $totals['payables'] - $totals['missing'], 2);
    $net = $totals['net'];
    $base = max($sales, 0.01);
    $waterfall = [
        ['label' => 'Gross revenue', 'amount' => $sales, 'kind' => 'total', 'note' => number_format($totals['orders']).' '.\Illuminate\Support\Str::plural('order', $totals['orders']).' from the register'],
        ['label' => 'Ingredients (COGS)', 'amount' => -$totals['cogs'], 'kind' => 'cost', 'note' => 'Cost of each size sold'],
        ['label' => 'Bulk & liquids', 'amount' => -$totals['bulk'], 'kind' => 'cost', 'note' => 'From closing audits, beyond what recipes took'],
        ['label' => 'Missing stock', 'amount' => -$totals['missing'], 'kind' => 'cost', 'note' => 'Bought but never reached the shelf'],
        ['label' => 'Operating expenses', 'amount' => -$operatingExpenses, 'kind' => 'cost', 'note' => 'Rent, wages, utilities, supplies'],
        ['label' => 'Equipment payables', 'amount' => -$totals['payables'], 'kind' => 'cost', 'note' => 'Installments paid in this period'],
        ['label' => 'Net profit', 'amount' => $net, 'kind' => 'result', 'note' => $sales > 0 ? number_format($net / $sales * 100, 1).'% of revenue' : 'No sales yet'],
    ];
    $running = 0;
    $bestDay = $ledger->where('orders', '>', 0)->sortByDesc('net')->first();
    $periodLabel = $from->isSameDay($to) ? $from->format('M j, Y') : $from->format('M j').' – '.$to->format('M j, Y');
    $exportRange = ['from' => $from->toDateString(), 'to' => $to->toDateString()];
    $pdfRange = ['period' => 'custom'] + $exportRange;
@endphp

<x-app-layout title="Profit & ledger">
    <div class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-8">
        <x-page-header eyebrow="Profit & loss" :title="$periodLabel"
            description="Same columns as your spreadsheet. Nothing typed twice: sales come from the register, ingredient costs from your prices, bulk from closing audits.">
            <x-slot:actions>
                <div class="inline-flex gap-1 rounded-xl bg-ink-100 p-1 dark:bg-white/[0.05]">
                    <a href="{{ route('admin.reports', ['period' => 'week']) }}" @class(['tab', 'tab-active' => $period === 'week'])>Last 7 days</a>
                    <a href="{{ route('admin.reports', ['period' => 'month']) }}" @class(['tab', 'tab-active' => $period === 'month'])>This month</a>
                </div>
                <a href="{{ route('admin.reports.pdf', $pdfRange) }}" class="btn-primary" data-no-loader><x-icon name="download" class="size-4" /> Download PDF report</a>
                <a href="{{ route('admin.exports.download', ['dataset' => 'ledger'] + $exportRange) }}" download class="btn-ghost"><x-icon name="download" class="size-4" /> Ledger CSV</a>
            </x-slot:actions>
        </x-page-header>

        <form method="GET" action="{{ route('admin.reports') }}" class="flex flex-wrap items-end gap-3">
            <input type="hidden" name="period" value="custom">
            <div>
                <label class="field-label" for="from">From</label>
                <input id="from" name="from" type="date" value="{{ $from->toDateString() }}" max="{{ today()->toDateString() }}" class="field num py-2">
            </div>
            <div>
                <label class="field-label" for="to">To</label>
                <input id="to" name="to" type="date" value="{{ $to->toDateString() }}" max="{{ today()->toDateString() }}" class="field num py-2">
            </div>
            <button type="submit" @class(['btn-ghost py-2', 'ring-2 ring-brand-400' => $period === 'custom']) data-loading-text="Loading…">Show range</button>
        </form>

        {{-- P&L waterfall --}}
        <section class="surface p-6 lg:p-8">
            <div class="grid gap-8 lg:grid-cols-[280px_1fr]">
                <div>
                    <p class="eyebrow">Net profit · {{ $periodLabel }}</p>
                    <p @class(['num mt-2 text-4xl font-semibold tracking-tight', 'text-loss-600 dark:text-loss-400' => $net < 0])>{{ $net < 0 ? '−' : '' }}₱{{ number_format(abs($net), 2) }}</p>
                    <p class="mt-2 text-sm text-ink-500">Revenue minus every cost, including equipment installments. Stock you bought counts when it's used, not when you pay for it.</p>
                    @if ($uncheckedDeliveries > 0)
                        <a href="{{ route('admin.dashboard') }}#waiting" class="mt-3 flex gap-2 rounded-xl bg-brand-400/10 px-3 py-2 text-xs text-brand-800 hover:underline dark:text-brand-200">
                            <x-icon name="alert" class="mt-0.5 size-3.5 shrink-0" />
                            {{ $uncheckedDeliveries }} {{ \Illuminate\Support\Str::plural('delivery', $uncheckedDeliveries) }} not checked yet. A short delivery lowers profit once you check it.
                        </a>
                    @endif
                    <dl class="mt-6 space-y-2 border-t border-ink-100 pt-4 text-sm dark:border-white/[0.06]">
                        <div class="flex justify-between"><dt class="text-ink-500">Avg per day</dt><dd class="num">₱{{ number_format($net / max(1, $dayCount), 2) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-ink-500">Best day</dt><dd class="num">{{ $bestDay ? $bestDay['date']->format('D j').' · ₱'.number_format($bestDay['net'], 0) : '—' }}</dd></div>
                        <div class="flex justify-between"><dt class="text-ink-500">Stock bought</dt><dd class="num">₱{{ number_format($totals['stock_purchases'], 2) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-ink-500">Food cost %</dt><dd class="num">{{ $sales > 0 ? number_format(($totals['cogs'] + $totals['bulk']) / $sales * 100, 1).'%' : '—' }}</dd></div>
                    </dl>
                </div>

                <ol class="space-y-3">
                    @foreach ($waterfall as $step)
                        @php
                            $width = min(100, abs($step['amount']) / $base * 100);
                            $offset = $step['kind'] === 'cost' ? max(0, ($running + $step['amount']) / $base * 100) : 0;
                            $running = $step['kind'] === 'total' ? $step['amount'] : ($step['kind'] === 'cost' ? $running + $step['amount'] : $running);
                        @endphp
                        <li class="grid grid-cols-[1fr_auto] gap-x-4 gap-y-1.5 sm:grid-cols-[180px_1fr_120px] sm:items-center">
                            <div>
                                <p @class(['text-sm', 'font-semibold' => $step['kind'] !== 'cost'])>{{ $step['label'] }}</p>
                                <p class="text-[11px] text-ink-400">{{ $step['note'] }}</p>
                            </div>
                            <p @class(['num text-right text-sm sm:order-last', 'font-semibold' => $step['kind'] !== 'cost', 'text-ink-500' => $step['kind'] === 'cost', 'text-loss-600 dark:text-loss-400' => $step['kind'] === 'result' && $step['amount'] < 0])>
                                {{ $step['amount'] < 0 ? '−' : '' }}₱{{ number_format(abs($step['amount']), 2) }}
                            </p>
                            <div class="col-span-2 h-7 rounded-md bg-ink-100 sm:col-span-1 dark:bg-white/[0.04]">
                                <div @class([
                                    'h-full rounded-md',
                                    'bg-ink-400 dark:bg-ink-500' => $step['kind'] === 'total',
                                    'bg-loss-400/70' => $step['kind'] === 'cost' || ($step['kind'] === 'result' && $step['amount'] < 0),
                                    'bg-gain-500' => $step['kind'] === 'result' && $step['amount'] >= 0,
                                ]) style="width: {{ $step['amount'] == 0 ? 0 : max(0.5, $width) }}%; margin-left: {{ min(99.5, $offset) }}%"></div>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>

        <div class="grid gap-6 lg:grid-cols-[1fr_320px]">
            {{-- Ledger --}}
            <section class="surface overflow-hidden">
                <div class="flex items-center justify-between border-b border-ink-200 px-5 py-4 dark:border-white/[0.07]">
                    <div>
                        <h2 class="font-semibold">Daily ledger</h2>
                        <p class="text-xs text-ink-500">Newest first. Days without a closing audit show bulk as —.</p>
                    </div>
                    <a href="{{ route('admin.exports.download', ['dataset' => 'orders'] + $exportRange) }}" download class="btn-ghost py-2 text-xs"><x-icon name="download" class="size-4" /> Orders CSV</a>
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
                                <tr class="hover:bg-ink-50 dark:hover:bg-white/[0.02]">
                                    <td class="whitespace-nowrap px-5 py-2.5">
                                        <span class="num">{{ $row['date']->format('D j M') }}</span>
                                        @if ($row['date']->isWeekend())<span class="ml-1 text-[10px] uppercase tracking-wider text-ink-400">wknd</span>@endif
                                    </td>
                                    <td class="num px-3 py-2.5 text-right text-ink-500">{{ $row['orders'] ?: '—' }}</td>
                                    <td class="num px-3 py-2.5 text-right">{{ number_format($row['sales'], 2) }}</td>
                                    <td class="num px-3 py-2.5 text-right text-ink-500">{{ number_format($row['cogs'], 2) }}</td>
                                    <td class="num px-3 py-2.5 text-right text-ink-500">{{ $row['audited'] ? number_format($row['bulk'], 2) : '—' }}</td>
                                    <td class="num px-3 py-2.5 text-right text-ink-500">{{ $row['expenses'] ? number_format($row['expenses'], 2) : '—' }}</td>
                                    <td @class(['num px-3 py-2.5 text-right font-semibold', 'text-loss-600 dark:text-loss-400' => $row['net'] < 0])>{{ $row['net'] < 0 ? '('.number_format(abs($row['net']), 2).')' : number_format($row['net'], 2) }}</td>
                                    <td class="num px-5 py-2.5 text-right text-ink-500">{{ $row['sales'] > 0 ? number_format($row['net'] / $row['sales'] * 100, 1).'%' : '—' }}</td>
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
                                <td @class(['num px-3 py-3 text-right', 'text-loss-600 dark:text-loss-400' => $net < 0])>{{ $net < 0 ? '('.number_format(abs($net), 2).')' : number_format($net, 2) }}</td>
                                <td class="num px-5 py-3 text-right">{{ $sales > 0 ? number_format($net / $sales * 100, 1).'%' : '—' }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <p class="border-t border-ink-100 px-5 py-3 text-[11px] text-ink-400 dark:border-white/[0.05]">All amounts in ₱. Losses shown in (brackets), like accounting sheets. Voided orders are left out.</p>
            </section>

            {{-- Best sellers --}}
            <section class="surface h-fit p-6">
                <p class="eyebrow">Best sellers · this period</p>
                @if ($bestSellers->isEmpty())
                    <p class="mt-6 text-sm text-ink-500">No sales in this period.</p>
                @else
                    <ol class="mt-4 space-y-4">
                        @foreach ($bestSellers as $seller)
                            <li class="flex items-center gap-3">
                                <span class="num w-4 text-xs text-ink-400">{{ $loop->iteration }}</span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium">{{ $seller['name'] }}</p>
                                    <p class="num text-xs text-ink-500">{{ number_format($seller['sold']) }} sold · ₱{{ number_format($seller['revenue'], 2) }}</p>
                                </div>
                                @if ($seller['margin'] !== null)
                                    <span @class(['num text-sm font-semibold', 'text-gain-600 dark:text-gain-400' => $seller['margin'] >= 50, 'text-brand-600 dark:text-brand-300' => $seller['margin'] < 50])>{{ number_format($seller['margin'], 0) }}%</span>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
