@php
    $collectedMax = max(1, $collected->max('amount'));
    $points = $collected->values()->map(fn (array $month, int $index) => round($index / max(1, $collected->count() - 1) * 300, 1).','.round(80 - $month['amount'] / $collectedMax * 72, 1))->join(' ');
    $thisMonth = $collected->last()['amount'] ?? 0;
    $lastMonth = $collected->count() > 1 ? $collected->slice(-2, 1)->first()['amount'] : 0;
    $collectedDelta = $lastMonth > 0 ? round(($thisMonth - $lastMonth) / $lastMonth * 100, 1) : null;
    $funnelTop = max(1, $funnel[0]['count']);
    $byTypeMax = max(1, $byType->max() ?? 1);
@endphp

<x-app-layout title="Platform overview">
    <div class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-8">
        <x-page-header :eyebrow="'Platform · '.now()->format('l j F')" title="How iPOSa is doing">
            <x-slot:actions>
                @if ($metrics['pendingVerifications'] > 0)
                    <a href="{{ route('super_admin.verifications') }}" class="btn-primary">{{ $metrics['pendingVerifications'] }} {{ \Illuminate\Support\Str::plural('verification', $metrics['pendingVerifications']) }} requested</a>
                @endif
                @if ($metrics['pendingPayments'] > 0)
                    <a href="{{ route('super_admin.plans', ['status' => 'pending']) }}" class="btn-primary">{{ $metrics['pendingPayments'] }} {{ \Illuminate\Support\Str::plural('payment', $metrics['pendingPayments']) }} to confirm</a>
                @endif
                <a href="{{ route('super_admin.businesses.index') }}" class="btn-ghost"><x-icon name="building" class="size-4" /> All businesses</a>
            </x-slot:actions>
        </x-page-header>

        <div class="grid gap-6 lg:grid-cols-3">
            <section class="surface p-6 lg:col-span-2">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="eyebrow">Monthly recurring revenue</p>
                        <p class="num mt-2 text-5xl font-semibold tracking-tighter">₱{{ number_format($metrics['mrr']) }}</p>
                        <p class="mt-2 text-sm text-ink-500">From {{ $metrics['paying'] }} active {{ \Illuminate\Support\Str::plural('business', $metrics['paying']) }} at their plan price</p>
                    </div>
                    <dl class="grid grid-cols-3 gap-6 text-right">
                        <div><dt class="text-xs text-ink-500">Paying</dt><dd class="num mt-1 text-xl font-semibold">{{ $metrics['paying'] }}</dd></div>
                        <div><dt class="text-xs text-ink-500">In trial</dt><dd class="num mt-1 text-xl font-semibold">{{ $metrics['trials'] }}</dd></div>
                        <div><dt class="text-xs text-ink-500">Past due</dt><dd @class(['num mt-1 text-xl font-semibold', 'text-loss-600 dark:text-loss-400' => $metrics['pastDue'] > 0])>{{ $metrics['pastDue'] }}</dd></div>
                    </dl>
                </div>

                <div class="mt-6 flex items-baseline justify-between text-sm">
                    <p class="text-ink-500">Payments collected per month</p>
                    <p>
                        <span class="num font-semibold">₱{{ number_format($thisMonth) }}</span> this month
                        @if ($collectedDelta !== null)
                            <span @class(['ml-1 font-semibold', 'text-gain-600 dark:text-gain-400' => $collectedDelta >= 0, 'text-loss-600 dark:text-loss-400' => $collectedDelta < 0])>{{ $collectedDelta >= 0 ? '+' : '' }}{{ $collectedDelta }}%</span>
                        @endif
                    </p>
                </div>
                <svg viewBox="0 0 300 84" class="mt-3 h-28 w-full" preserveAspectRatio="none" aria-label="Payments collected over the last 12 months">
                    <polyline points="0,84 {{ $points }} 300,84" class="fill-brand-400/10 stroke-none" />
                    <polyline points="{{ $points }}" fill="none" class="stroke-brand-400" stroke-width="2" vector-effect="non-scaling-stroke" stroke-linejoin="round" />
                </svg>
                <div class="mt-2 flex justify-between text-[11px] text-ink-400"><span>{{ $collected->first()['label'] }}</span><span>{{ $collected->last()['label'] }}</span></div>
            </section>

            <section class="surface p-6">
                <p class="eyebrow">Trial funnel · last 30 days</p>
                <ol class="mt-5 space-y-3">
                    @foreach ($funnel as $step)
                        @php($isActivation = $step['activation'] ?? false)
                        <li>
                            <div class="flex justify-between text-sm">
                                <span @class(['font-semibold' => $isActivation])>{{ $step['label'] }}</span>
                                <span class="num text-ink-500">{{ $step['count'] }}</span>
                            </div>
                            <div class="mt-1.5 h-2 rounded-full bg-ink-100 dark:bg-white/[0.06]">
                                <div @class(['h-full rounded-full', 'bg-brand-400' => $isActivation, 'bg-ink-400 dark:bg-ink-500' => ! $isActivation]) style="width: {{ $step['count'] / $funnelTop * 100 }}%"></div>
                            </div>
                        </li>
                    @endforeach
                </ol>
                <p class="mt-5 text-xs text-ink-500">
                    @if ($activationRate !== null)
                        {{ $activationRate }}% of shops that ever finished a closing audit have paid. It's the activation moment.
                    @else
                        No new shop has finished a closing audit yet. That's the activation moment to push for.
                    @endif
                </p>
            </section>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <section class="surface overflow-hidden">
                <div class="border-b border-ink-200 px-5 py-4 dark:border-white/[0.07]">
                    <h2 class="font-semibold">Gone quiet</h2>
                    <p class="text-xs text-ink-500">Paying shops with no sales for 3+ days. Call before they cancel.</p>
                </div>
                <ul class="divide-y divide-ink-100 dark:divide-white/[0.05]">
                    @forelse ($goneQuiet as $quiet)
                        <li>
                            <a href="{{ route('super_admin.businesses.show', $quiet) }}" class="flex items-center gap-4 px-5 py-3.5 hover:bg-ink-50 dark:hover:bg-white/[0.02]">
                                <span class="size-2 shrink-0 rounded-full bg-loss-500"></span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium">{{ $quiet->business_name }} <span class="font-normal text-ink-500">· {{ $quiet->business_type }}</span></p>
                                    <p class="text-xs text-ink-500">Last closing audit: {{ $quiet->audits_max_date ? \Illuminate\Support\Carbon::parse($quiet->audits_max_date)->format('M j') : 'never' }}</p>
                                </div>
                                <span class="text-right text-xs text-ink-500">Last sale<br><span class="text-ink-900 dark:text-ink-100">{{ $quiet->orders_max_paid_at ? \Illuminate\Support\Carbon::parse($quiet->orders_max_paid_at)->diffForHumans() : 'never' }}</span></span>
                            </a>
                        </li>
                    @empty
                        <li class="px-5 py-8 text-center text-sm text-ink-500">Every paying shop sold something in the last 3 days.</li>
                    @endforelse
                </ul>
            </section>

            <section class="surface overflow-hidden">
                <div class="border-b border-ink-200 px-5 py-4 dark:border-white/[0.07]">
                    <h2 class="font-semibold">Trials ending this week</h2>
                    <p class="text-xs text-ink-500">Not activated = no closing audit yet. Those need a nudge, not a discount.</p>
                </div>
                <ul class="divide-y divide-ink-100 dark:divide-white/[0.05]">
                    @forelse ($trialsEnding as $trial)
                        <li>
                            <a href="{{ route('super_admin.businesses.show', $trial) }}" class="flex items-center gap-4 px-5 py-3.5 hover:bg-ink-50 dark:hover:bg-white/[0.02]">
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium">{{ $trial->business_name }}</p>
                                    <p class="num text-xs text-ink-500">{{ number_format($trial->orders_count) }} orders so far</p>
                                </div>
                                <span @class(['pill', 'bg-gain-500/15 text-gain-700 dark:text-gain-300' => $trial->audits_count > 0, 'bg-ink-100 text-ink-600 dark:bg-white/[0.07] dark:text-ink-300' => $trial->audits_count === 0])>
                                    {{ $trial->audits_count > 0 ? 'Activated' : 'No audit yet' }}
                                </span>
                                <span class="num w-16 text-right text-xs text-ink-500">{{ $trial->daysUntilDue() }} {{ \Illuminate\Support\Str::plural('day', $trial->daysUntilDue()) }}</span>
                            </a>
                        </li>
                    @empty
                        <li class="px-5 py-8 text-center text-sm text-ink-500">No trials end in the next 7 days.</li>
                    @endforelse
                </ul>
            </section>
        </div>

        <section class="surface p-6">
            <p class="eyebrow">Businesses by type · {{ $metrics['total'] }} total</p>
            @if ($byType->isEmpty())
                <p class="mt-4 text-sm text-ink-500">No businesses yet.</p>
            @else
                <div class="mt-5 grid gap-x-10 gap-y-3 sm:grid-cols-2">
                    @foreach ($byType as $type => $count)
                        <div class="grid grid-cols-[150px_1fr_36px] items-center gap-3 text-sm">
                            <span class="truncate text-ink-600 dark:text-ink-300">{{ $type }}</span>
                            <div class="h-2 rounded-full bg-ink-100 dark:bg-white/[0.06]"><div class="h-full rounded-full bg-ink-400 dark:bg-ink-500" style="width: {{ $count / $byTypeMax * 100 }}%"></div></div>
                            <span class="num text-right text-ink-500">{{ $count }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
    </div>
</x-app-layout>
