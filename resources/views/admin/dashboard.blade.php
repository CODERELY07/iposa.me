@use('App\Enums\BusinessStatus')

@php
    $chartMax = max(1, $week->max('sales'));
    $daysLeft = $business->daysUntilDue();
    $setupDone = collect($setupSteps)->every(fn (array $step) => $step['done']);
    $equation = [
        ['label' => 'Sales', 'amount' => $today['sales'], 'negative' => false, 'hint' => $today['orders'].' '.\Illuminate\Support\Str::plural('order', $today['orders']), 'href' => $business->hasFeature('reports') ? route('admin.reports') : null],
        ['label' => 'Ingredients', 'amount' => $today['cogs'], 'negative' => true, 'hint' => 'cost of what sold', 'href' => route('admin.inventory')],
        ['label' => 'Bulk used', 'amount' => $today['audited'] ? $today['bulk'] : null, 'negative' => true, 'hint' => $today['audited'] ? 'from tonight’s audit' : 'after closing audit', 'href' => route('audit')],
        ['label' => 'Expenses', 'amount' => $today['expenses'], 'negative' => true, 'hint' => $today['expenseCount'].' '.\Illuminate\Support\Str::plural('entry', $today['expenseCount']).' today', 'href' => $business->hasFeature('expenses') ? route('admin.expenses') : null],
    ];
@endphp

<x-app-layout title="Today">
    <div class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-8">
        <x-page-header :eyebrow="now()->format('l, j F').' · as of '.now()->format('g:i A')" :title="'Today at '.$business->business_name">
            <x-slot:actions>
                @if ($business->status === BusinessStatus::Trial && $daysLeft !== null)
                    <a href="{{ route('admin.settings') }}#billing" class="pill bg-brand-400/15 text-brand-700 dark:text-brand-300">Trial · {{ $daysLeft }} {{ \Illuminate\Support\Str::plural('day', $daysLeft) }} left</a>
                @endif
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
                <p @class(['num mt-3 text-5xl font-semibold tracking-tighter sm:text-6xl', 'text-loss-600 dark:text-loss-400' => $profitSoFar < 0])>
                    {{ $profitSoFar < 0 ? '−' : '' }}₱{{ number_format(abs($profitSoFar), 2) }}
                </p>

                {{-- The equation: every peso is traceable --}}
                <div class="mt-8 grid gap-px overflow-hidden rounded-xl border border-ink-200 bg-ink-200 sm:grid-cols-4 dark:border-white/[0.07] dark:bg-white/[0.07]">
                    @foreach ($equation as $part)
                        <a href="{{ $part['href'] ?? '#' }}" @class(['group bg-white p-4 transition dark:bg-ink-900', 'hover:bg-ink-50 dark:hover:bg-ink-800/60' => $part['href'], 'pointer-events-none' => ! $part['href']])>
                            <p class="flex items-center justify-between text-xs text-ink-500">
                                {{ $part['label'] }}
                                @if ($part['href'])<x-icon name="chevron-right" class="size-3.5 opacity-0 transition group-hover:opacity-100" />@endif
                            </p>
                            @if ($part['amount'] === null)
                                <p class="num mt-1 text-lg font-semibold text-ink-400">pending</p>
                            @else
                                <p class="num mt-1 text-lg font-semibold">{{ $part['negative'] && $part['amount'] > 0 ? '−' : '' }}₱{{ number_format($part['amount'], 2) }}</p>
                            @endif
                            <p class="mt-0.5 text-[11px] text-ink-400">{{ $part['hint'] }}</p>
                        </a>
                    @endforeach
                </div>
            </section>

            {{-- Needs attention --}}
            <section class="surface flex flex-col p-6">
                <p class="eyebrow">Needs you tonight</p>
                <ul class="mt-4 flex-1 space-y-3">
                    @if (! $auditDone)
                        <li class="flex gap-3 rounded-xl bg-brand-400/10 p-3">
                            <x-icon name="audit" class="size-5 text-brand-600 dark:text-brand-300" />
                            <div class="min-w-0 flex-1">
                                <a href="{{ route('audit') }}" class="text-sm font-medium hover:underline">Closing audit not done</a>
                                <p class="text-xs text-ink-500">Profit leaves out oil, mayo and sauces until it is.</p>
                            </div>
                        </li>
                    @endif

                    @foreach ($voidRequests as $voidRequest)
                        <li class="rounded-xl border border-ink-200 p-3 dark:border-white/[0.07]">
                            <p class="text-sm font-medium">Void request · order <span class="num">#{{ $voidRequest->number }}</span> · <span class="num">₱{{ number_format((float) $voidRequest->subtotal, 2) }}</span></p>
                            <p class="truncate text-xs text-ink-500">{{ $voidRequest->cashier_name }} · {{ $voidRequest->paid_at->format('g:i A') }} · {{ $voidRequest->lines->pluck('name')->join(', ') }}</p>
                            <div class="mt-2 flex gap-2">
                                <form method="POST" action="{{ route('admin.orders.void.approve', $voidRequest) }}">
                                    @csrf
                                    <button type="submit" class="btn-ghost px-3 py-1.5 text-xs text-loss-600 dark:text-loss-400" data-loading-text="Voiding…">Void it</button>
                                </form>
                                <form method="POST" action="{{ route('admin.orders.void.reject', $voidRequest) }}">
                                    @csrf
                                    <button type="submit" class="btn-quiet px-3 py-1.5 text-xs" data-loading-text="…">Keep sale</button>
                                </form>
                            </div>
                        </li>
                    @endforeach

                    @foreach ($lowStock as $stockItem)
                        <li class="flex gap-3 p-3 pt-0">
                            <span class="mt-1.5 size-2 shrink-0 rounded-full bg-loss-500"></span>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium">{{ $stockItem['name'] }} <span class="num font-normal text-ink-500">· {{ $stockItem['left'] }}</span></p>
                                <p class="text-xs text-ink-500">{{ $stockItem['runsOut'] ? 'Runs out '.$stockItem['runsOut'] : 'At or below its alert level' }}</p>
                            </div>
                        </li>
                    @endforeach

                    @if ($auditDone && $voidRequests->isEmpty() && $lowStock->isEmpty())
                        <li class="flex h-full flex-col items-center justify-center py-8 text-center text-sm text-ink-500">
                            <x-icon name="check" class="mb-2 size-6 text-gain-500" />
                            All clear for tonight.
                        </li>
                    @endif
                </ul>
                <a href="{{ route('admin.inventory', ['tab' => 'pieces']) }}" class="btn-ghost mt-4 w-full">Review stock</a>
            </section>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            {{-- 7-day chart --}}
            <section class="surface p-6 lg:col-span-2">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="eyebrow">Last 7 days</p>
                        <p class="mt-1 text-sm text-ink-500">Sales vs what was left after every cost</p>
                    </div>
                    <div class="flex items-center gap-4 text-xs text-ink-500">
                        <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-ink-300 dark:bg-ink-600"></span>Sales</span>
                        <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-brand-400"></span>Profit</span>
                    </div>
                </div>

                <div class="mt-8 grid h-56 grid-cols-7 items-end gap-2 sm:gap-4">
                    @foreach ($week as $day)
                        @php($isToday = $day['date']->isToday())
                        <div class="group relative flex h-full flex-col justify-end">
                            <div class="pointer-events-none absolute -top-2 left-1/2 z-10 hidden -translate-x-1/2 -translate-y-full whitespace-nowrap rounded-lg bg-ink-900 px-2.5 py-1.5 text-[11px] text-white group-hover:block dark:bg-white dark:text-ink-900">
                                <span class="num">₱{{ number_format($day['sales']) }}</span> sales · <span class="num">₱{{ number_format($day['net']) }}</span> profit
                            </div>
                            <div class="relative w-full overflow-hidden rounded-t-lg bg-ink-200 dark:bg-white/[0.08]" style="height: {{ max(1, round($day['sales'] / $chartMax * 100)) }}%">
                                @if ($day['net'] > 0 && $day['sales'] > 0)
                                    <div @class(['absolute inset-x-0 bottom-0 rounded-t-md', 'bg-brand-400' => ! $isToday, 'bg-brand-400/50 bg-[repeating-linear-gradient(135deg,transparent_0_4px,rgba(0,0,0,.12)_4px_8px)]' => $isToday])
                                        style="height: {{ min(100, round($day['net'] / $day['sales'] * 100)) }}%"></div>
                                @elseif ($day['net'] < 0)
                                    <div class="absolute inset-x-0 bottom-0 h-1.5 bg-loss-500" title="Loss day"></div>
                                @endif
                            </div>
                            <p @class(['mt-2 text-center text-[11px]', 'font-semibold text-ink-900 dark:text-white' => $isToday, 'text-ink-500' => ! $isToday])>
                                {{ $day['date']->format('D') }} <span class="num">{{ $day['date']->format('j') }}</span>
                            </p>
                        </div>
                    @endforeach
                </div>
                <p class="mt-4 text-[11px] text-ink-400">Today is striped: still open. A red base marks a loss day (e.g. rent or payroll). Hover a bar for the numbers.</p>
            </section>

            {{-- Best sellers --}}
            <section class="surface p-6">
                <div class="flex items-center justify-between">
                    <p class="eyebrow">Best sellers today</p>
                    <span class="text-[11px] text-ink-400">margin</span>
                </div>
                @if ($bestSellers->isEmpty())
                    <p class="mt-6 text-sm text-ink-500">No sales yet today.</p>
                @else
                    <ol class="mt-4 space-y-4">
                        @foreach ($bestSellers as $seller)
                            <li class="flex items-center gap-3">
                                <span class="num w-4 text-xs text-ink-400">{{ $loop->iteration }}</span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium">{{ $seller['name'] }}</p>
                                    <p class="num text-xs text-ink-500">{{ $seller['sold'] }} sold · ₱{{ number_format($seller['revenue'], 2) }}</p>
                                </div>
                                @if ($seller['margin'] !== null)
                                    <span @class(['num text-sm font-semibold', 'text-gain-600 dark:text-gain-400' => $seller['margin'] >= 50, 'text-brand-600 dark:text-brand-300' => $seller['margin'] < 50])>{{ number_format($seller['margin'], 0) }}%</span>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                @endif
            </section>

            {{-- Liquids in recipes: what the recipes say against what the count says --}}
            @if ($recipeVariance)
                <section class="surface p-6 lg:col-span-3">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <p class="eyebrow">Liquids · recipes vs the count</p>
                        <span class="text-[11px] text-ink-400">closing audit {{ $recipeVariance['date']->isToday() ? 'tonight' : $recipeVariance['date']->format('D j M') }}</span>
                    </div>
                    <div class="mt-4 overflow-x-auto">
                        <table class="w-full min-w-[560px] text-sm">
                            <thead class="table-head">
                                <tr class="border-b border-ink-200 dark:border-white/[0.07]">
                                    <th class="py-2 pr-3 font-semibold">Item</th>
                                    <th class="px-3 py-2 text-right font-semibold">Recipes used</th>
                                    <th class="px-3 py-2 text-right font-semibold">Extra the count found</th>
                                    <th class="px-3 py-2 text-right font-semibold">Total used</th>
                                    <th class="py-2 pl-3 font-semibold">What it means</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-ink-100 dark:divide-white/[0.05]">
                                @foreach ($recipeVariance['rows'] as $row)
                                    <tr>
                                        <td class="py-2.5 pr-3 font-medium">{{ $row['name'] }}</td>
                                        <td class="num px-3 py-2.5 text-right">{{ \App\Models\Item::trimNumber($row['recipe']) }} {{ $row['unit'] }}</td>
                                        <td class="num px-3 py-2.5 text-right">
                                            @if ($row['over_deducted'] > 0)
                                                <span class="text-gain-600 dark:text-gain-400">−{{ \App\Models\Item::trimNumber($row['over_deducted']) }} {{ $row['unit'] }}</span>
                                            @else
                                                {{ \App\Models\Item::trimNumber($row['extra']) }} {{ $row['unit'] }}
                                                @if ($row['extra_cost'] > 0)<span class="block text-xs text-ink-500">₱{{ number_format($row['extra_cost'], 2) }}</span>@endif
                                            @endif
                                        </td>
                                        <td class="num px-3 py-2.5 text-right font-semibold">{{ \App\Models\Item::trimNumber($row['total']) }} {{ $row['unit'] }}</td>
                                        <td class="py-2.5 pl-3 text-xs text-ink-500">{{ $row['verdict'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="mt-3 text-[11px] text-ink-400">Recipes are charged in Ingredients through each item's cost; the audit only charges the extra, in Bulk &amp; liquids. Nothing is counted twice.</p>
                </section>
            @endif
        </div>

        {{-- Setup checklist: disappears once everything is done --}}
        @unless ($setupDone)
            <section class="surface flex flex-col gap-4 p-5 sm:flex-row sm:items-center">
                <div class="shrink-0">
                    <p class="text-sm font-semibold">Setup <span class="num">{{ collect($setupSteps)->where('done', true)->count() }}/{{ count($setupSteps) }}</span></p>
                    <p class="text-xs text-ink-500">Finish these for your first true-profit night.</p>
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
        @endunless
    </div>
</x-app-layout>
