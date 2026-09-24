{{-- Shared by the four day pages: which day, the four numbers of its profit, and a tab for each. --}}
@php
    $isToday = $day->isToday();
    $dayQuery = $isToday ? [] : ['date' => $day->toDateString()];
    $tabs = [
        'sales' => ['label' => 'Sales', 'amount' => $ledgerDay['sales'], 'sign' => ''],
        'ingredients' => ['label' => 'Ingredients', 'amount' => $ledgerDay['cogs'], 'sign' => '−'],
        'bulk' => ['label' => $business->auditsPieces() ? 'Used at closing' : 'Bulk used', 'amount' => $ledgerDay['audited'] ? $ledgerDay['bulk'] : null, 'sign' => '−'],
        'expenses' => ['label' => 'Expenses', 'amount' => $ledgerDay['expenses'], 'sign' => '−'],
    ];
    $net = $ledgerDay['net'];
@endphp

<a href="{{ route('admin.dashboard') }}" class="inline-flex items-center gap-1 text-sm text-ink-500 hover:text-ink-900 dark:hover:text-white">
    <x-icon name="chevron-right" class="size-4 rotate-180" /> Today
</a>

<div class="flex flex-wrap items-end justify-between gap-4">
    <div>
        <p class="eyebrow">{{ $isToday ? 'Today · as of '.now()->format('g:i A') : $day->format('l') }}</p>
        <h1 class="mt-1 text-2xl font-semibold tracking-tight">{{ $day->format('F j, Y') }}</h1>
        <p class="mt-1 text-sm text-ink-500">
            {{ $isToday ? 'Profit so far' : 'Profit' }}:
            <span @class(['num font-semibold text-ink-900 dark:text-white', '!text-loss-600 dark:!text-loss-400' => $net < 0])>{{ $net < 0 ? '−' : '' }}₱{{ number_format(abs($net), 2) }}</span>
            <span class="text-ink-400">= sales − ingredients − {{ strtolower($tabs['bulk']['label']) }} − expenses</span>
        </p>
    </div>
    <form method="GET" action="{{ route('admin.day', $section) }}" class="flex items-center gap-2">
        <a href="{{ route('admin.day', ['section' => $section, 'date' => $day->subDay()->toDateString()]) }}" class="btn-ghost size-10 !px-0" aria-label="Day before"><x-icon name="chevron-right" class="size-4 rotate-180" /></a>
        <input type="date" name="date" value="{{ $day->toDateString() }}" max="{{ today()->toDateString() }}" class="field num w-auto py-2" aria-label="Day" onchange="this.form.requestSubmit ? this.form.requestSubmit() : this.form.submit()">
        @unless ($isToday)
            <a href="{{ route('admin.day', ['section' => $section] + ($day->addDay()->isToday() ? [] : ['date' => $day->addDay()->toDateString()])) }}" class="btn-ghost size-10 !px-0" aria-label="Day after"><x-icon name="chevron-right" class="size-4" /></a>
        @endunless
    </form>
</div>

<nav class="grid grid-cols-2 gap-px overflow-hidden rounded-xl border border-ink-200 bg-ink-200 sm:grid-cols-4 dark:border-white/[0.07] dark:bg-white/[0.07]" aria-label="Parts of the day's profit">
    @foreach ($tabs as $key => $tab)
        <a href="{{ route('admin.day', ['section' => $key] + $dayQuery) }}" @class([
            'block p-4 transition',
            'bg-brand-400/10 dark:bg-brand-400/10' => $section === $key,
            'bg-white hover:bg-ink-50 dark:bg-ink-900 dark:hover:bg-ink-800/60' => $section !== $key,
        ]) @if ($section === $key) aria-current="page" @endif>
            <p @class(['text-xs', 'font-semibold text-brand-700 dark:text-brand-300' => $section === $key, 'text-ink-500' => $section !== $key])>{{ $tab['label'] }}</p>
            @if ($tab['amount'] === null)
                <p class="num mt-1 text-lg font-semibold text-ink-400">pending</p>
            @else
                {{-- A cost below zero (recipes over-charged) adds to profit. --}}
                <p class="num mt-1 text-lg font-semibold">{{ $tab['sign'] !== '' && $tab['amount'] < 0 ? '+' : ($tab['amount'] > 0 ? $tab['sign'] : '') }}₱{{ number_format(abs($tab['amount']), 2) }}</p>
            @endif
        </a>
    @endforeach
</nav>
