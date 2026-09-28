@php
    /**
     * The owner's period report, rendered by dompdf. dompdf reads tables and simple blocks
     * reliably, so layout is tables and every chart is bars sized in percent.
     */
    $peso = fn (float $amount) => ($amount < 0 ? '−' : '').'₱'.number_format(abs($amount), 2);
    $cost = fn (float $amount) => ($amount < 0 ? '+' : '').'₱'.number_format(abs($amount), 2);
    $qty = fn (float $value, string $unit = '') => trim(\App\Models\Item::trimNumber($value, 3).' '.$unit);
    $pct = fn (?float $value) => $value === null ? '—' : number_format($value, 1).'%';
    $share = fn (float $part, float $whole) => $whole > 0 ? max(0, min(100, $part / $whole * 100)) : 0;
    $sales = $totals['sales'];
    $operating = round($totals['expenses'] - $totals['payables'] - $totals['missing'], 2);
    $pl = [
        ['Sales', $sales, 'total'],
        ['Ingredients (cost of what sold)', -$totals['cogs'], 'cost'],
        ['Bulk & liquids used (closing counts)', -$totals['bulk'], 'cost'],
        ['Missing stock (short deliveries)', -$totals['missing'], 'cost'],
        ['Operating expenses', -$operating, 'cost'],
        ['Equipment payables', -$totals['payables'], 'cost'],
        ['Net profit', $totals['net'], 'result'],
    ];

    // Long periods chart by week so every bar stays readable.
    $byWeek = $days->count() > 45;
    $trend = $byWeek
        ? $days->groupBy(fn (array $day) => $day['date']->startOfWeek()->toDateString())->map(fn ($week) => [
            'label' => $week->first()['date']->startOfWeek()->format('M j'),
            'sales' => round($week->sum('sales'), 2),
            'net' => round($week->sum('net'), 2),
        ])->values()
        : $days->map(fn (array $day) => ['label' => $day['date']->format('j'), 'sales' => $day['sales'], 'net' => $day['net']]);
    $trendMax = max(1, $trend->max('sales'), $trend->max('net'));
    $orders = $totals['orders'];
    $bestMargin = $menu->where('sold', '>', 0)->sortByDesc('margin')->take(5);
    $worstMargin = $menu->where('sold', '>', 0)->sortBy('margin')->take(5);
    $expenseTotal = max(1, $expenseCategories->max('amount') ?? 1);
    $hoursMax = max(1, $hours->max('amount') ?? 1);
    $weekdayMax = max(1, $weekdays->max('average'));
    $paymentMax = max(1, $payments->max('amount') ?? 1);
    $expenseRows = $expenses->take(200);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $business->business_name }} · {{ $from->format('M j') }}–{{ $to->format('M j, Y') }}</title>
    <style>
        @page { margin: 22mm 14mm 18mm 14mm; }
        * { font-family: 'DejaVu Sans', sans-serif; }
        body { font-size: 9pt; color: #1f2328; line-height: 1.4; }
        header { position: fixed; top: -15mm; left: 0; right: 0; height: 10mm; border-bottom: 0.5pt solid #d6d9dd; font-size: 8pt; color: #5b6470; }
        footer { position: fixed; bottom: -12mm; left: 0; right: 0; height: 8mm; font-size: 7.5pt; color: #7a838e; }
        h1 { font-size: 20pt; margin: 0 0 2pt; }
        h2 { font-size: 13pt; margin: 0 0 3pt; padding-top: 2pt; }
        h3 { font-size: 10pt; margin: 12pt 0 4pt; }
        p { margin: 0 0 5pt; }
        .muted { color: #5b6470; }
        .small { font-size: 7.5pt; }
        .section { page-break-before: always; }
        .intro { color: #5b6470; margin-bottom: 8pt; }
        table { width: 100%; border-collapse: collapse; }
        table.data th { text-align: left; font-size: 7.5pt; text-transform: uppercase; letter-spacing: 0.4pt; color: #5b6470; border-bottom: 0.75pt solid #1f2328; padding: 4pt 4pt; }
        table.data th.r { text-align: right; }
        table.data td { padding: 3.5pt 4pt; border-bottom: 0.5pt solid #e6e8eb; vertical-align: top; }
        table.data tr.total td { border-top: 0.75pt solid #1f2328; border-bottom: none; font-weight: bold; }
        table.data tr.stripe td { background: #f6f7f8; }
        .r { text-align: right; }
        .num { white-space: nowrap; }
        .loss { color: #b42318; }
        .gain { color: #1a7f37; }
        .kpis td { width: 25%; padding: 8pt 8pt 8pt 0; vertical-align: top; }
        .kpi { border: 0.75pt solid #d6d9dd; border-radius: 4pt; padding: 7pt 9pt; }
        .kpi .label { font-size: 7.5pt; color: #5b6470; text-transform: uppercase; letter-spacing: 0.4pt; }
        .kpi .value { font-size: 15pt; font-weight: bold; margin-top: 2pt; }
        .bar-track { background: #eef0f2; height: 7pt; width: 100%; }
        .bar { height: 7pt; }
        .sales { background: #3f76c4; }
        .profit { background: #d97706; }
        .lossbar { background: #b42318; }
        .costbar { background: #3f76c4; }
        .swatch { display: inline-block; width: 7pt; height: 7pt; margin-right: 3pt; }
        .note { border-left: 2pt solid #d97706; background: #fdf6ec; padding: 5pt 7pt; margin: 6pt 0; font-size: 8pt; }
        .two td.half { width: 50%; vertical-align: top; }
        .two td.half:first-child { padding-right: 10pt; }
    </style>
</head>
<body>
<header>
    <table><tr>
        <td><strong>{{ $business->business_name }}</strong> · Business report</td>
        <td class="r">{{ $from->format('M j, Y') }} – {{ $to->format('M j, Y') }}</td>
    </tr></table>
</header>
<footer>
    <table><tr>
        <td>Generated {{ $generatedAt->format('M j, Y g:i A') }} by iPOSa · All amounts in Philippine pesos</td>
        <td></td>
    </tr></table>
</footer>

{{-- 1. Summary --}}
<h1>{{ $business->business_name }}</h1>
<p class="intro">Business report for {{ $from->format('F j, Y') }} to {{ $to->format('F j, Y') }} ({{ $days->count() }} {{ \Illuminate\Support\Str::plural('day', $days->count()) }}).</p>

<table class="kpis"><tr>
    <td><div class="kpi"><div class="label">Sales</div><div class="value num">{{ $peso($sales) }}</div><div class="small muted">{{ number_format($orders) }} {{ \Illuminate\Support\Str::plural('order', $orders) }}</div></div></td>
    <td><div class="kpi"><div class="label">Net profit</div><div class="value num {{ $totals['net'] < 0 ? 'loss' : '' }}">{{ $peso($totals['net']) }}</div><div class="small muted">{{ $pct($margin) }} of sales</div></div></td>
    <td><div class="kpi"><div class="label">Average order</div><div class="value num">{{ $peso($orders > 0 ? $sales / $orders : 0) }}</div><div class="small muted">{{ $peso($days->count() > 0 ? $sales / $days->count() : 0) }} sales per day</div></div></td>
    <td><div class="kpi"><div class="label">Food cost</div><div class="value num">{{ $pct($sales > 0 ? ($totals['cogs'] + $totals['bulk']) / $sales * 100 : null) }}</div><div class="small muted">ingredients + bulk, of sales</div></div></td>
</tr></table>

<h3>Profit and loss</h3>
<table class="data">
    <thead><tr><th>Line</th><th class="r">Amount</th><th class="r">% of sales</th><th style="width: 38%">Share of sales</th></tr></thead>
    <tbody>
    @foreach ($pl as [$label, $amount, $kind])
        <tr class="{{ $kind === 'result' ? 'total' : '' }}">
            <td>{{ $label }}</td>
            <td class="r num {{ $kind === 'result' && $amount < 0 ? 'loss' : '' }}">{{ $peso($amount) }}</td>
            <td class="r num">{{ $sales > 0 ? $pct(abs($amount) / $sales * 100 * ($kind === 'result' && $amount < 0 ? -1 : 1)) : '—' }}</td>
            <td><div class="bar-track"><div class="bar {{ $kind === 'total' ? 'sales' : ($kind === 'result' ? ($amount < 0 ? 'lossbar' : 'profit') : 'costbar') }}" style="width: {{ $share(abs($amount), $sales) }}%"></div></div></td>
        </tr>
    @endforeach
    </tbody>
</table>
<p class="small muted" style="margin-top: 4pt">Stock bought in this period: {{ $peso($totals['stock_purchases']) }}. It is listed but not subtracted: stock lowers profit when it's used (ingredients and closing counts), so it is never counted twice.</p>

@if ($auditedDays < $days->count())
    <div class="note">Closing counts were done on {{ $auditedDays }} of {{ $days->count() }} days. On the other days, bulk and liquids used aren't in the profit yet, so profit may be a little high.</div>
@endif
@if ($pendingDeliveries > 0)
    <div class="note">{{ $pendingDeliveries }} cashier {{ \Illuminate\Support\Str::plural('delivery', $pendingDeliveries) }} not checked against the receipt yet. A short delivery lowers profit once it's checked.</div>
@endif

<h3>Sales and profit {{ $byWeek ? 'per week' : 'per day' }}</h3>
<p class="small muted"><span class="swatch sales"></span>Sales &nbsp; <span class="swatch profit"></span>Profit &nbsp; <span class="swatch lossbar"></span>Loss</p>
@if ($trend->isNotEmpty())
    @php($best = $trend->sortByDesc('net')->first())
    <p class="small muted" style="margin-top: 4pt">Highest sales: {{ $peso($trend->max('sales')) }}. Best {{ $byWeek ? 'week' : 'day' }} for profit: {{ $byWeek ? 'week of '.$best['label'] : $days->sortByDesc('net')->first()['date']->format('D, M j') }} ({{ $peso($best['net']) }}). {{ $trend->where('net', '<', 0)->count() }} {{ $byWeek ? \Illuminate\Support\Str::plural('week', $trend->where('net', '<', 0)->count()) : \Illuminate\Support\Str::plural('day', $trend->where('net', '<', 0)->count()) }} with a loss.</p>
@endif
<img src="{{ \App\Reports\TrendChart::dataUri($trend) }}" style="width: 100%" alt="Sales and profit chart">


{{-- 2. Daily ledger --}}
<div class="section">
    <h2>Daily ledger</h2>
    <p class="intro">Every day in the period. Bulk shows — on days without a closing count.</p>
    <table class="data">
        <thead><tr><th>Date</th><th class="r">Orders</th><th class="r">Sales</th><th class="r">Ingredients</th><th class="r">Bulk</th><th class="r">Expenses</th><th class="r">Net profit</th><th class="r">Margin</th></tr></thead>
        <tbody>
        @foreach ($days as $day)
            <tr class="{{ $loop->even ? 'stripe' : '' }}">
                <td class="num">{{ $day['date']->format('D, M j') }}</td>
                <td class="r num">{{ $day['orders'] }}</td>
                <td class="r num">{{ number_format($day['sales'], 2) }}</td>
                <td class="r num">{{ number_format($day['cogs'], 2) }}</td>
                <td class="r num">{{ $day['audited'] ? number_format($day['bulk'], 2) : '—' }}</td>
                <td class="r num">{{ number_format($day['expenses'], 2) }}</td>
                <td class="r num {{ $day['net'] < 0 ? 'loss' : '' }}">{{ $day['net'] < 0 ? '('.number_format(abs($day['net']), 2).')' : number_format($day['net'], 2) }}</td>
                <td class="r num">{{ $day['sales'] > 0 ? $pct($day['net'] / $day['sales'] * 100) : '—' }}</td>
            </tr>
        @endforeach
        <tr class="total">
            <td>Total</td>
            <td class="r num">{{ number_format($orders) }}</td>
            <td class="r num">{{ number_format($sales, 2) }}</td>
            <td class="r num">{{ number_format($totals['cogs'], 2) }}</td>
            <td class="r num">{{ number_format($totals['bulk'], 2) }}</td>
            <td class="r num">{{ number_format($totals['expenses'], 2) }}</td>
            <td class="r num {{ $totals['net'] < 0 ? 'loss' : '' }}">{{ $totals['net'] < 0 ? '('.number_format(abs($totals['net']), 2).')' : number_format($totals['net'], 2) }}</td>
            <td class="r num">{{ $pct($margin) }}</td>
        </tr>
        </tbody>
    </table>
    <p class="small muted" style="margin-top: 4pt">Losses are in (brackets). Voided orders are left out everywhere.</p>
</div>

{{-- 3. Sales & menu --}}
<div class="section">
    <h2>Sales</h2>
    <p class="intro">How customers paid, and when the shop is busiest.</p>

    <h3>By payment method</h3>
    <table class="data">
        <thead><tr><th>Method</th><th class="r">Orders</th><th class="r">Amount</th><th class="r">Share</th><th style="width: 40%"></th></tr></thead>
        <tbody>
        @forelse ($payments as $payment)
            <tr>
                <td>{{ $payment['label'] }}</td>
                <td class="r num">{{ number_format($payment['orders']) }}</td>
                <td class="r num">{{ $peso($payment['amount']) }}</td>
                <td class="r num">{{ $pct($sales > 0 ? $payment['amount'] / $sales * 100 : null) }}</td>
                <td><div class="bar-track"><div class="bar sales" style="width: {{ $share($payment['amount'], $paymentMax) }}%"></div></div></td>
            </tr>
        @empty
            <tr><td colspan="5" class="muted">No sales in this period.</td></tr>
        @endforelse
        </tbody>
    </table>

    <table class="two" style="margin-top: 6pt"><tr>
        <td class="half">
            <h3>Average sales by day of the week</h3>
            <table class="data">
                <thead><tr><th>Day</th><th class="r">Average</th><th style="width: 45%"></th></tr></thead>
                <tbody>
                @foreach ($weekdays as $weekday)
                    <tr>
                        <td>{{ $weekday['label'] }} <span class="small muted">({{ $weekday['days'] }})</span></td>
                        <td class="r num">{{ $peso($weekday['average']) }}</td>
                        <td><div class="bar-track"><div class="bar sales" style="width: {{ $share($weekday['average'], $weekdayMax) }}%"></div></div></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            <p class="small muted">In brackets: how many of that day had sales.</p>
        </td>
        <td class="half">
            <h3>Sales by hour</h3>
            <table class="data">
                <thead><tr><th>Hour</th><th class="r">Orders</th><th class="r">Amount</th><th style="width: 38%"></th></tr></thead>
                <tbody>
                @forelse ($hours as $hour)
                    <tr>
                        <td class="num">{{ $hour['label'] }}</td>
                        <td class="r num">{{ $hour['orders'] }}</td>
                        <td class="r num">{{ $peso($hour['amount']) }}</td>
                        <td><div class="bar-track"><div class="bar sales" style="width: {{ $share($hour['amount'], $hoursMax) }}%"></div></div></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="muted">No sales in this period.</td></tr>
                @endforelse
                </tbody>
            </table>
        </td>
    </tr></table>
</div>

<div class="section">
    <h2>Menu performance</h2>
    <p class="intro">Every item and size sold, most profitable first. Cost is what each sale cost at the moment it was rung up.</p>

    <table class="two"><tr>
        <td class="half">
            <h3>Best margins</h3>
            <table class="data">
                <thead><tr><th>Item</th><th class="r">Sold</th><th class="r">Margin</th></tr></thead>
                <tbody>
                @forelse ($bestMargin as $row)
                    <tr><td>{{ $row['name'] }}</td><td class="r num">{{ $row['sold'] }}</td><td class="r num gain">{{ $pct($row['margin']) }}</td></tr>
                @empty
                    <tr><td colspan="3" class="muted">Nothing sold.</td></tr>
                @endforelse
                </tbody>
            </table>
        </td>
        <td class="half">
            <h3>Lowest margins: check their price or cost</h3>
            <table class="data">
                <thead><tr><th>Item</th><th class="r">Sold</th><th class="r">Margin</th></tr></thead>
                <tbody>
                @forelse ($worstMargin as $row)
                    <tr><td>{{ $row['name'] }}</td><td class="r num">{{ $row['sold'] }}</td><td class="r num {{ ($row['margin'] ?? 0) < 25 ? 'loss' : '' }}">{{ $pct($row['margin']) }}</td></tr>
                @empty
                    <tr><td colspan="3" class="muted">Nothing sold.</td></tr>
                @endforelse
                </tbody>
            </table>
        </td>
    </tr></table>

    <h3>All items</h3>
    <table class="data">
        <thead><tr><th>Item</th><th class="r">Sold</th><th class="r">Sales</th><th class="r">Cost</th><th class="r">Profit</th><th class="r">Margin</th><th style="width: 22%">Profit share</th></tr></thead>
        <tbody>
        @php($menuProfitMax = max(1, $menu->max('profit') ?? 1))
        @forelse ($menu as $row)
            <tr class="{{ $loop->even ? 'stripe' : '' }}">
                <td>{{ $row['name'] }}</td>
                <td class="r num">{{ number_format($row['sold']) }}</td>
                <td class="r num">{{ number_format($row['sales'], 2) }}</td>
                <td class="r num">{{ number_format($row['cost'], 2) }}</td>
                <td class="r num {{ $row['profit'] < 0 ? 'loss' : '' }}">{{ number_format($row['profit'], 2) }}</td>
                <td class="r num">{{ $pct($row['margin']) }}</td>
                <td><div class="bar-track"><div class="bar {{ $row['profit'] < 0 ? 'lossbar' : 'profit' }}" style="width: {{ $share(abs($row['profit']), $menuProfitMax) }}%"></div></div></td>
            </tr>
        @empty
            <tr><td colspan="7" class="muted">Nothing sold in this period.</td></tr>
        @endforelse
        @if ($menu->isNotEmpty())
            <tr class="total">
                <td>Total</td>
                <td class="r num">{{ number_format($menu->sum('sold')) }}</td>
                <td class="r num">{{ number_format($menu->sum('sales'), 2) }}</td>
                <td class="r num">{{ number_format($menu->sum('cost'), 2) }}</td>
                <td class="r num">{{ number_format($menu->sum('profit'), 2) }}</td>
                <td class="r num">{{ $pct($menu->sum('sales') > 0 ? $menu->sum('profit') / $menu->sum('sales') * 100 : null) }}</td>
                <td></td>
            </tr>
        @endif
        </tbody>
    </table>
    <p class="small muted" style="margin-top: 4pt">Profit here is after ingredients only. Bulk, missing stock and expenses come off the whole business, in the profit and loss.</p>
</div>

{{-- 4. Costs & stock --}}
<div class="section">
    <h2>Costs and stock</h2>
    <p class="intro">Where the ingredient money went, what went missing, and what is on the shelf now.</p>

    <h3>Taken off the shelf by sales</h3>
    <table class="data">
        <thead><tr><th>Piece or liquid</th><th class="r">Used by sales</th><th class="r">Of which in the item's cost</th><th class="r">Value at today's cost</th></tr></thead>
        <tbody>
        @forelse ($stockUsed as $row)
            <tr class="{{ $loop->even ? 'stripe' : '' }}">
                <td>{{ $row['name'] }}</td>
                <td class="r num">{{ $qty($row['qty'], $row['unit']) }}</td>
                <td class="r num {{ $row['costed'] + 0.0005 < $row['qty'] ? 'loss' : '' }}">{{ $qty($row['costed'], $row['unit']) }}</td>
                <td class="r num">{{ $peso($row['value']) }}</td>
            </tr>
        @empty
            <tr><td colspan="4" class="muted">No linked pieces or liquids were used.</td></tr>
        @endforelse
        </tbody>
    </table>
    <p class="small muted" style="margin-top: 4pt">In red: part of it isn't added by the app ("Include in cost" is off). That part is only costed if the cost typed for those items already includes it.</p>

    <h3>Closing counts: used or missing beyond the recipes</h3>
    <table class="data">
        <thead><tr><th>Item</th><th class="r">Counts</th><th class="r">Used or missing</th><th class="r">Recipes took too much</th><th class="r">Unrecorded restocks</th><th class="r">Cost</th></tr></thead>
        <tbody>
        @forelse ($closingCounts as $row)
            <tr class="{{ $loop->even ? 'stripe' : '' }}">
                <td>{{ $row['name'] }}</td>
                <td class="r num">{{ $row['counts'] }}</td>
                <td class="r num">{{ $qty($row['used'], $row['unit']) }}</td>
                <td class="r num">{{ $row['recipe_surplus'] > 0 ? $qty($row['recipe_surplus'], $row['unit']) : '—' }}</td>
                <td class="r num">{{ $row['restocked'] > 0 ? $qty($row['restocked'], $row['unit']) : '—' }}</td>
                <td class="r num">{{ $cost($row['cost']) }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="muted">No closing counts in this period.</td></tr>
        @endforelse
        @if ($closingCounts->isNotEmpty())
            <tr class="total"><td colspan="5">Total (the bulk line of the profit and loss)</td><td class="r num">{{ $cost($closingCounts->sum('cost')) }}</td></tr>
        @endif
        </tbody>
    </table>

    <h3>Cashier deliveries</h3>
    <table class="data">
        <thead><tr><th>Date</th><th>Item</th><th>Received by</th><th class="r">Recorded</th><th class="r">Receipt</th><th class="r">Paid</th><th class="r">Missing</th><th>Status</th></tr></thead>
        <tbody>
        @forelse ($deliveries as $delivery)
            @php($unit = $delivery->item?->unit ?: 'pc')
            <tr class="{{ $loop->even ? 'stripe' : '' }}">
                <td class="num">{{ $delivery->created_at->format('M j') }}</td>
                <td>{{ $delivery->item?->name ?? 'Deleted item' }}</td>
                <td>{{ $delivery->received_by }}</td>
                <td class="r num">{{ $qty((float) $delivery->added, $unit) }}</td>
                <td class="r num">{{ $delivery->receipt_added !== null ? $qty((float) $delivery->receipt_added, $unit) : '—' }}</td>
                <td class="r num">{{ $delivery->paid !== null ? $peso((float) $delivery->paid) : '—' }}</td>
                <td class="r num {{ (float) $delivery->missing_cost > 0 ? 'loss' : '' }}">{{ (float) $delivery->missing_cost > 0 ? $peso((float) $delivery->missing_cost) : '—' }}</td>
                <td>{{ $delivery->isPending() ? 'Not checked' : 'Checked by '.$delivery->checked_by_name }}</td>
            </tr>
        @empty
            <tr><td colspan="8" class="muted">No cashier deliveries in this period.</td></tr>
        @endforelse
        </tbody>
    </table>

    <h3>On the shelf now</h3>
    <p class="small muted">As of {{ $generatedAt->format('M j, Y g:i A') }}. {{ $stock['low'] }} {{ \Illuminate\Support\Str::plural('item', $stock['low']) }} at or below the alert level.</p>
    <table class="data">
        <thead><tr><th>Item</th><th>Kind</th><th class="r">On hand</th><th class="r">Cost per unit</th><th class="r">Value</th><th>Alert</th></tr></thead>
        <tbody>
        @forelse ($stock['rows'] as $row)
            <tr class="{{ $loop->even ? 'stripe' : '' }}">
                <td>{{ $row['name'] }}</td>
                <td>{{ $row['kind'] }}</td>
                <td class="r num {{ $row['on_hand'] < 0 ? 'loss' : '' }}">{{ $qty($row['on_hand'], $row['unit']) }}</td>
                <td class="r num">₱{{ \App\Models\Item::formatUnitCost($row['unit_cost']) }}</td>
                <td class="r num">{{ $peso($row['value']) }}</td>
                <td class="{{ $row['low'] ? 'loss' : 'muted' }}">{{ $row['low'] ? 'Low' : '' }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="muted">No counted stock.</td></tr>
        @endforelse
        @if ($stock['rows']->isNotEmpty())
            <tr class="total"><td colspan="4">Stock value</td><td class="r num">{{ $peso($stock['value']) }}</td><td></td></tr>
        @endif
        </tbody>
    </table>
</div>

{{-- 5. Expenses & team --}}
<div class="section">
    <h2>Expenses</h2>
    <p class="intro">By category, then every entry.</p>
    <table class="data">
        <thead><tr><th>Category</th><th class="r">Entries</th><th class="r">Amount</th><th>In profit?</th><th style="width: 35%"></th></tr></thead>
        <tbody>
        @forelse ($expenseCategories as $category)
            <tr>
                <td>{{ $category['label'] }}</td>
                <td class="r num">{{ $category['entries'] }}</td>
                <td class="r num">{{ $peso($category['amount']) }}</td>
                <td class="{{ $category['lowers_profit'] ? '' : 'muted' }}">{{ $category['lowers_profit'] ? 'Yes' : 'No, counted when used' }}</td>
                <td><div class="bar-track"><div class="bar costbar" style="width: {{ $share($category['amount'], $expenseTotal) }}%"></div></div></td>
            </tr>
        @empty
            <tr><td colspan="5" class="muted">No expenses in this period.</td></tr>
        @endforelse
        @if ($expenseCategories->isNotEmpty())
            <tr class="total"><td colspan="2">Counted in profit</td><td class="r num">{{ $peso($totals['expenses']) }}</td><td colspan="2"></td></tr>
        @endif
        </tbody>
    </table>

    @if ($expenseRows->isNotEmpty())
        <h3>Every entry{{ $expenses->count() > $expenseRows->count() ? ' (first '.$expenseRows->count().' of '.$expenses->count().'; the CSV export has all)' : '' }}</h3>
        <table class="data">
            <thead><tr><th>Date</th><th>Category</th><th>Description</th><th>Logged by</th><th class="r">Amount</th></tr></thead>
            <tbody>
            @foreach ($expenseRows as $expense)
                <tr class="{{ $loop->even ? 'stripe' : '' }} {{ $expense->category->lowersProfit() ? '' : 'muted' }}">
                    <td class="num">{{ $expense->date->format('M j') }}</td>
                    <td>{{ $expense->category->label() }}</td>
                    <td>{{ $expense->description ?: '—' }}</td>
                    <td>{{ $expense->logged_by }}</td>
                    <td class="r num">{{ $peso((float) $expense->amount) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif

    <h2 style="margin-top: 16pt">Team</h2>
    <p class="intro">Sales per cashier. A high number of voids is worth a conversation.</p>
    <table class="data">
        <thead><tr><th>Cashier</th><th class="r">Orders</th><th class="r">Sales</th><th class="r">Average order</th><th class="r">Voided</th><th class="r">Voided amount</th><th class="r">Void requests open</th></tr></thead>
        <tbody>
        @forelse ($cashiers as $cashier)
            <tr class="{{ $loop->even ? 'stripe' : '' }}">
                <td>{{ $cashier['name'] }}</td>
                <td class="r num">{{ number_format($cashier['orders']) }}</td>
                <td class="r num">{{ $peso($cashier['sales']) }}</td>
                <td class="r num">{{ $peso($cashier['average']) }}</td>
                <td class="r num {{ $cashier['voided'] > 0 ? 'loss' : '' }}">{{ $cashier['voided'] }}</td>
                <td class="r num">{{ $cashier['voided_amount'] > 0 ? $peso($cashier['voided_amount']) : '—' }}</td>
                <td class="r num">{{ $cashier['requested'] ?: '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="7" class="muted">No orders in this period.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
</body>
</html>
