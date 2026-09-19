@php
    $metrics = $metrics ?? ['mrr' => 84896, 'mrrDelta' => 8.4, 'paying' => 104, 'trials' => 22, 'pastDue' => 3];

    $mrrHistory = $mrrHistory ?? [36100, 40200, 44800, 48300, 53200, 57900, 62100, 66800, 71300, 75200, 78300, 84896];
    $sparkMax = max($mrrHistory);
    $sparkPoints = collect($mrrHistory)->map(fn (int $value, int $index) => round($index / (count($mrrHistory) - 1) * 300, 1).','.round(80 - $value / $sparkMax * 72, 1))->join(' ');

    $funnel = $funnel ?? [
        ['label' => 'Signed up', 'count' => 64],
        ['label' => 'Added menu', 'count' => 51],
        ['label' => 'First sale', 'count' => 43],
        ['label' => 'First closing audit', 'count' => 29],
        ['label' => 'Paid', 'count' => 21],
    ];

    $goneQuiet = $goneQuiet ?? [
        ['id' => 41, 'name' => 'Brew Haven', 'city' => 'Cebu City', 'plan' => 'Negosyo', 'lastSale' => '4 days ago', 'reason' => 'Audit streak broke Sep 13'],
        ['id' => 77, 'name' => 'Tita Nena\'s Eatery', 'city' => 'Batangas', 'plan' => 'Tindahan', 'lastSale' => '6 days ago', 'reason' => 'Only 1 staff login this month'],
        ['id' => 12, 'name' => 'Milky Way Tea', 'city' => 'Davao', 'plan' => 'Negosyo', 'lastSale' => '3 days ago', 'reason' => 'Card declined Sep 15'],
    ];

    $trialsEnding = $trialsEnding ?? [
        ['id' => 128, 'name' => "Kape't Burger", 'endsIn' => '9 days', 'activated' => false, 'orders' => 412],
        ['id' => 126, 'name' => 'Sizzle Stop', 'endsIn' => '2 days', 'activated' => true, 'orders' => 861],
        ['id' => 125, 'name' => 'Pan de Amor', 'endsIn' => '3 days', 'activated' => false, 'orders' => 12],
        ['id' => 121, 'name' => 'Kanto Wings', 'endsIn' => '5 days', 'activated' => true, 'orders' => 540],
    ];

    $byType = $byType ?? ['Café / coffee' => 38, 'Burger & fast food' => 31, 'Milk tea' => 24, 'Carinderia' => 19, 'Bakery' => 10, 'Other' => 6];
@endphp

<x-app-layout title="Platform overview">
    <div class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-8">
        <x-page-header eyebrow="Platform · Friday 18 September" title="How iPOSa is doing">
            <x-slot:actions>
                <a href="{{ route('super_admin.tenants') }}" class="btn-ghost"><x-icon name="building" class="size-4" /> All businesses</a>
            </x-slot:actions>
        </x-page-header>

        <div class="grid gap-6 lg:grid-cols-3">
            <section class="surface p-6 lg:col-span-2">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="eyebrow">Monthly recurring revenue</p>
                        <p class="num mt-2 text-5xl font-semibold tracking-tighter">₱{{ number_format($metrics['mrr']) }}</p>
                        <p class="mt-2 text-sm"><span class="font-semibold text-gain-600 dark:text-gain-400">+{{ $metrics['mrrDelta'] }}%</span> <span class="text-ink-500">vs August</span></p>
                    </div>
                    <dl class="grid grid-cols-3 gap-6 text-right">
                        <div><dt class="text-xs text-ink-500">Paying</dt><dd class="num mt-1 text-xl font-semibold">{{ $metrics['paying'] }}</dd></div>
                        <div><dt class="text-xs text-ink-500">In trial</dt><dd class="num mt-1 text-xl font-semibold">{{ $metrics['trials'] }}</dd></div>
                        <div><dt class="text-xs text-ink-500">Past due</dt><dd class="num mt-1 text-xl font-semibold text-loss-600 dark:text-loss-400">{{ $metrics['pastDue'] }}</dd></div>
                    </dl>
                </div>
                <svg viewBox="0 0 300 84" class="mt-6 h-28 w-full" preserveAspectRatio="none" aria-label="MRR over the last 12 months">
                    <polyline points="0,84 {{ $sparkPoints }} 300,84" class="fill-brand-400/10 stroke-none" />
                    <polyline points="{{ $sparkPoints }}" fill="none" class="stroke-brand-400" stroke-width="2" vector-effect="non-scaling-stroke" stroke-linejoin="round" />
                </svg>
                <div class="mt-2 flex justify-between text-[11px] text-ink-400"><span>Oct 2025</span><span>Sep 2026</span></div>
            </section>

            <section class="surface p-6">
                <p class="eyebrow">Trial funnel · last 30 days</p>
                <ol class="mt-5 space-y-3">
                    @foreach ($funnel as $step)
                        <li>
                            <div class="flex justify-between text-sm">
                                <span @class(['font-semibold' => $step['label'] === 'First closing audit'])>{{ $step['label'] }}</span>
                                <span class="num text-ink-500">{{ $step['count'] }}</span>
                            </div>
                            <div class="mt-1.5 h-2 rounded-full bg-ink-100 dark:bg-white/[0.06]">
                                <div @class(['h-full rounded-full', 'bg-brand-400' => $step['label'] === 'First closing audit', 'bg-ink-400 dark:bg-ink-500' => $step['label'] !== 'First closing audit']) style="width: {{ $step['count'] / $funnel[0]['count'] * 100 }}%"></div>
                            </div>
                        </li>
                    @endforeach
                </ol>
                <p class="mt-5 text-xs text-ink-500">72% of shops that finish a closing audit go on to pay. It's the activation moment.</p>
            </section>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <section class="surface overflow-hidden">
                <div class="border-b border-ink-200 px-5 py-4 dark:border-white/[0.07]">
                    <h2 class="font-semibold">Gone quiet</h2>
                    <p class="text-xs text-ink-500">Paying shops with no sales for 3+ days. Call before they cancel.</p>
                </div>
                <ul class="divide-y divide-ink-100 dark:divide-white/[0.05]">
                    @foreach ($goneQuiet as $tenant)
                        <li>
                            <a href="{{ route('super_admin.tenants.show', $tenant['id']) }}" class="flex items-center gap-4 px-5 py-3.5 hover:bg-ink-50 dark:hover:bg-white/[0.02]">
                                <span class="size-2 shrink-0 rounded-full bg-loss-500"></span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium">{{ $tenant['name'] }} <span class="font-normal text-ink-500">· {{ $tenant['city'] }}</span></p>
                                    <p class="text-xs text-ink-500">{{ $tenant['reason'] }}</p>
                                </div>
                                <span class="text-right text-xs text-ink-500">Last sale<br><span class="text-ink-900 dark:text-ink-100">{{ $tenant['lastSale'] }}</span></span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>

            <section class="surface overflow-hidden">
                <div class="border-b border-ink-200 px-5 py-4 dark:border-white/[0.07]">
                    <h2 class="font-semibold">Trials ending soon</h2>
                    <p class="text-xs text-ink-500">Not activated = no closing audit yet. Those need a nudge, not a discount.</p>
                </div>
                <ul class="divide-y divide-ink-100 dark:divide-white/[0.05]">
                    @foreach ($trialsEnding as $trial)
                        <li>
                            <a href="{{ route('super_admin.tenants.show', $trial['id']) }}" class="flex items-center gap-4 px-5 py-3.5 hover:bg-ink-50 dark:hover:bg-white/[0.02]">
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium">{{ $trial['name'] }}</p>
                                    <p class="num text-xs text-ink-500">{{ number_format($trial['orders']) }} orders so far</p>
                                </div>
                                <span @class(['pill', 'bg-gain-500/15 text-gain-700 dark:text-gain-300' => $trial['activated'], 'bg-ink-100 text-ink-600 dark:bg-white/[0.07] dark:text-ink-300' => ! $trial['activated']])>
                                    {{ $trial['activated'] ? 'Activated' : 'No audit yet' }}
                                </span>
                                <span class="num w-16 text-right text-xs text-ink-500">{{ $trial['endsIn'] }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        </div>

        <section class="surface p-6">
            <p class="eyebrow">Businesses by type</p>
            <div class="mt-5 grid gap-x-10 gap-y-3 sm:grid-cols-2">
                @foreach ($byType as $type => $count)
                    <div class="grid grid-cols-[140px_1fr_36px] items-center gap-3 text-sm">
                        <span class="truncate text-ink-600 dark:text-ink-300">{{ $type }}</span>
                        <div class="h-2 rounded-full bg-ink-100 dark:bg-white/[0.06]"><div class="h-full rounded-full bg-ink-400 dark:bg-ink-500" style="width: {{ $count / max($byType) * 100 }}%"></div></div>
                        <span class="num text-right text-ink-500">{{ $count }}</span>
                    </div>
                @endforeach
            </div>
        </section>
    </div>
</x-app-layout>
