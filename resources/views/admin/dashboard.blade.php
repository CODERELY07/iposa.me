@php
    $today = $today ?? [
        'asOf' => '7:48 PM',
        'orders' => 98,
        'sales' => 14260.00,
        'ingredients' => 4420.00,
        'expenses' => 1850.00,
        'yesterdayProfitAtThisHour' => 6910.00,
    ];
    $profitSoFar = $today['sales'] - $today['ingredients'] - $today['expenses'];
    $profitDelta = $profitSoFar - $today['yesterdayProfitAtThisHour'];

    $week = $week ?? [
        ['day' => 'Sat', 'date' => '12', 'sales' => 21860, 'costs' => 12400],
        ['day' => 'Sun', 'date' => '13', 'sales' => 23410, 'costs' => 13020],
        ['day' => 'Mon', 'date' => '14', 'sales' => 12880, 'costs' => 8900],
        ['day' => 'Tue', 'date' => '15', 'sales' => 13920, 'costs' => 9140],
        ['day' => 'Wed', 'date' => '16', 'sales' => 15100, 'costs' => 9650],
        ['day' => 'Thu', 'date' => '17', 'sales' => 16240, 'costs' => 9980],
        ['day' => 'Fri', 'date' => '18', 'sales' => 14260, 'costs' => 6270, 'isToday' => true],
    ];
    $chartMax = max(array_column($week, 'sales'));

    $bestSellers = $bestSellers ?? [
        ['name' => 'Cheeseburger', 'sold' => 38, 'revenue' => 4142.00, 'margin' => 58],
        ['name' => 'Iced Tea 22oz', 'sold' => 31, 'revenue' => 1860.00, 'margin' => 78],
        ['name' => 'Fries Large', 'sold' => 24, 'revenue' => 2136.00, 'margin' => 71],
        ['name' => 'Iced Coffee 16oz', 'sold' => 15, 'revenue' => 1185.00, 'margin' => 66],
        ['name' => 'Tapsilog', 'sold' => 12, 'revenue' => 1668.00, 'margin' => 44],
    ];

    $lowStock = $lowStock ?? [
        ['name' => 'Burger buns', 'left' => '18 pcs', 'threshold' => '40 pcs', 'runsOut' => 'tomorrow lunch'],
        ['name' => 'Chicken thigh', 'left' => '4 pcs', 'threshold' => '10 pcs', 'runsOut' => 'tonight'],
        ['name' => 'Cups 22oz', 'left' => '35 pcs', 'threshold' => '100 pcs', 'runsOut' => 'Saturday'],
    ];

    $setupSteps = [
        ['label' => 'Import your menu', 'done' => true],
        ['label' => 'Add bulk & liquids', 'done' => true],
        ['label' => 'Link ingredients to burgers', 'done' => true],
        ['label' => 'Invite a cashier', 'done' => true],
        ['label' => 'Finish your first closing audit', 'done' => false],
    ];
@endphp

<x-app-layout title="Today">
    <div class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-8">
        <x-page-header eyebrow="Friday, 18 September · as of {{ $today['asOf'] }}" title="Today at Kape't Burger">
            <x-slot:actions>
                <span class="pill bg-brand-400/15 text-brand-700 dark:text-brand-300">Trial · 9 days left</span>
                <a href="{{ route('pos') }}" class="btn-ghost"><x-icon name="pos" class="size-4" /> Open register</a>
            </x-slot:actions>
        </x-page-header>

        <div class="grid gap-6 lg:grid-cols-3">
            {{-- The one number --}}
            <section class="surface p-6 lg:col-span-2 lg:p-8">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="eyebrow">True profit so far</p>
                    <span @class(['pill', 'bg-gain-500/15 text-gain-700 dark:text-gain-300' => $profitDelta >= 0, 'bg-loss-500/15 text-loss-700 dark:text-loss-300' => $profitDelta < 0])>
                        <x-icon :name="$profitDelta >= 0 ? 'arrow-up-right' : 'arrow-down-right'" class="size-3" />
                        ₱{{ number_format(abs($profitDelta), 0) }} vs yesterday at this hour
                    </span>
                </div>
                <p class="num mt-3 text-5xl font-semibold tracking-tighter sm:text-6xl">₱{{ number_format($profitSoFar, 2) }}</p>

                {{-- The equation: every peso is traceable --}}
                <div class="mt-8 grid gap-px overflow-hidden rounded-xl border border-ink-200 bg-ink-200 sm:grid-cols-4 dark:border-white/[0.07] dark:bg-white/[0.07]">
                    @foreach ([
                        ['Sales', $today['sales'], '+', $today['orders'].' orders', route('admin.reports')],
                        ['Ingredients', $today['ingredients'], '−', 'auto from recipes', route('admin.inventory')],
                        ['Bulk used', null, '−', 'after closing audit', route('audit')],
                        ['Expenses', $today['expenses'], '−', '3 entries today', route('admin.expenses')],
                    ] as [$label, $amount, $sign, $hint, $href])
                        <a href="{{ $href }}" class="group bg-white p-4 transition hover:bg-ink-50 dark:bg-ink-900 dark:hover:bg-ink-800/60">
                            <p class="flex items-center justify-between text-xs text-ink-500">
                                {{ $label }}
                                <x-icon name="chevron-right" class="size-3.5 opacity-0 transition group-hover:opacity-100" />
                            </p>
                            @if ($amount === null)
                                <p class="num mt-1 text-lg font-semibold text-ink-400">pending</p>
                            @else
                                <p class="num mt-1 text-lg font-semibold">{{ $sign === '−' ? '−' : '' }}₱{{ number_format($amount, 2) }}</p>
                            @endif
                            <p class="mt-0.5 text-[11px] text-ink-400">{{ $hint }}</p>
                        </a>
                    @endforeach
                </div>
            </section>

            {{-- Needs attention --}}
            <section class="surface flex flex-col p-6">
                <p class="eyebrow">Needs you tonight</p>
                <ul class="mt-4 flex-1 space-y-3">
                    <li class="flex gap-3 rounded-xl bg-brand-400/10 p-3">
                        <x-icon name="audit" class="size-5 text-brand-600 dark:text-brand-300" />
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium">Closing audit not done</p>
                            <p class="text-xs text-ink-500">Profit excludes oil, mayo and sauces until it is.</p>
                        </div>
                    </li>
                    @foreach ($lowStock as $stockItem)
                        <li class="flex gap-3 p-3 pt-0">
                            <span class="mt-1.5 size-2 shrink-0 rounded-full bg-loss-500"></span>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium">{{ $stockItem['name'] }} <span class="num font-normal text-ink-500">· {{ $stockItem['left'] }}</span></p>
                                <p class="text-xs text-ink-500">Runs out {{ $stockItem['runsOut'] }} at this pace</p>
                            </div>
                        </li>
                    @endforeach
                </ul>
                <a href="{{ route('admin.inventory') }}" class="btn-ghost mt-4 w-full">Review stock</a>
            </section>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            {{-- 7-day chart --}}
            <section class="surface p-6 lg:col-span-2">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="eyebrow">Last 7 days</p>
                        <p class="mt-1 text-sm text-ink-500">Sales vs everything it cost to make them</p>
                    </div>
                    <div class="flex items-center gap-4 text-xs text-ink-500">
                        <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-ink-300 dark:bg-ink-600"></span>Sales</span>
                        <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-brand-400"></span>Profit</span>
                    </div>
                </div>

                <div class="mt-8 grid h-56 grid-cols-7 items-end gap-2 sm:gap-4">
                    @foreach ($week as $day)
                        @php($profit = $day['sales'] - $day['costs'])
                        <div class="group relative flex h-full flex-col justify-end">
                            <div class="pointer-events-none absolute -top-2 left-1/2 z-10 hidden -translate-x-1/2 -translate-y-full whitespace-nowrap rounded-lg bg-ink-900 px-2.5 py-1.5 text-[11px] text-white group-hover:block dark:bg-white dark:text-ink-900">
                                <span class="num">₱{{ number_format($day['sales']) }}</span> sales · <span class="num">₱{{ number_format($profit) }}</span> profit
                            </div>
                            <div class="relative w-full overflow-hidden rounded-t-lg bg-ink-200 dark:bg-white/[0.08]" style="height: {{ round($day['sales'] / $chartMax * 100) }}%">
                                <div @class(['absolute inset-x-0 bottom-0 rounded-t-md', 'bg-brand-400' => ! ($day['isToday'] ?? false), 'bg-brand-400/50 bg-[repeating-linear-gradient(135deg,transparent_0_4px,rgba(0,0,0,.12)_4px_8px)]' => $day['isToday'] ?? false])
                                    style="height: {{ round($profit / $day['sales'] * 100) }}%"></div>
                            </div>
                            <p @class(['mt-2 text-center text-[11px]', 'font-semibold text-ink-900 dark:text-white' => $day['isToday'] ?? false, 'text-ink-500' => ! ($day['isToday'] ?? false)])>
                                {{ $day['day'] }} <span class="num">{{ $day['date'] }}</span>
                            </p>
                        </div>
                    @endforeach
                </div>
                <p class="mt-4 text-[11px] text-ink-400">Today is striped: still open, bulk usage not counted yet.</p>
            </section>

            {{-- Best sellers --}}
            <section class="surface p-6">
                <div class="flex items-center justify-between">
                    <p class="eyebrow">Best sellers today</p>
                    <span class="text-[11px] text-ink-400">margin</span>
                </div>
                <ol class="mt-4 space-y-4">
                    @foreach ($bestSellers as $seller)
                        <li class="flex items-center gap-3">
                            <span class="num w-4 text-xs text-ink-400">{{ $loop->iteration }}</span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium">{{ $seller['name'] }}</p>
                                <p class="num text-xs text-ink-500">{{ $seller['sold'] }} sold · ₱{{ number_format($seller['revenue'], 2) }}</p>
                            </div>
                            <span @class(['num text-sm font-semibold', 'text-gain-600 dark:text-gain-400' => $seller['margin'] >= 50, 'text-brand-600 dark:text-brand-300' => $seller['margin'] < 50])>{{ $seller['margin'] }}%</span>
                        </li>
                    @endforeach
                </ol>
            </section>
        </div>

        {{-- Setup checklist: disappears once the first audit is done --}}
        <section class="surface flex flex-col gap-4 p-5 sm:flex-row sm:items-center">
            <div class="shrink-0">
                <p class="text-sm font-semibold">Setup <span class="num">4/5</span></p>
                <p class="text-xs text-ink-500">One step to your first true-profit night.</p>
            </div>
            <ol class="flex flex-1 flex-wrap gap-2">
                @foreach ($setupSteps as $step)
                    <li @class(['pill py-1', 'bg-ink-100 text-ink-500 line-through dark:bg-white/[0.05]' => $step['done'], 'bg-brand-400 text-ink-950' => ! $step['done']])>
                        @if ($step['done'])<x-icon name="check" class="size-3" />@endif
                        {{ $step['label'] }}
                    </li>
                @endforeach
            </ol>
        </section>
    </div>
</x-app-layout>
