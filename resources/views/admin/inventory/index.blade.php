@php
    $menuItems = $menuItems ?? [
        ['id' => 2, 'name' => 'Cheeseburger', 'category' => 'Burgers', 'recipe' => ['Burger bun', 'Beef patty', 'Cheese slice'], 'stock' => null,
            'variants' => [['label' => 'Regular', 'cost' => 46.00, 'price' => 109.00]]],
        ['id' => 4, 'name' => 'Bacon Burger', 'category' => 'Burgers', 'recipe' => ['Burger bun', 'Beef patty', 'Bacon strip ×2'], 'stock' => null,
            'variants' => [['label' => 'Regular', 'cost' => 68.00, 'price' => 149.00]]],
        ['id' => 7, 'name' => 'Tapsilog', 'category' => 'Rice meals', 'recipe' => ['Beef tapa 120g', 'Egg', 'Rice cup'], 'stock' => null,
            'variants' => [['label' => 'w/ egg', 'cost' => 78.00, 'price' => 139.00]]],
        ['id' => 8, 'name' => 'Fries', 'category' => 'Sides', 'recipe' => ['Fries pack'], 'stock' => null,
            'variants' => [['label' => 'Reg', 'cost' => 18.00, 'price' => 59.00], ['label' => 'Large', 'cost' => 26.00, 'price' => 89.00]]],
        ['id' => 11, 'name' => 'Iced Tea', 'category' => 'Drinks', 'recipe' => ['Cup + lid'], 'stock' => null,
            'variants' => [['label' => '16oz', 'cost' => 9.50, 'price' => 45.00], ['label' => '22oz', 'cost' => 13.00, 'price' => 60.00]]],
        ['id' => 12, 'name' => 'Iced Coffee', 'category' => 'Drinks', 'recipe' => ['Cup + lid'], 'stock' => null,
            'variants' => [['label' => '16oz', 'cost' => 27.00, 'price' => 79.00], ['label' => '22oz', 'cost' => 34.00, 'price' => 99.00]]],
        ['id' => 14, 'name' => 'Bottled Water', 'category' => 'Drinks', 'recipe' => [], 'stock' => 48,
            'variants' => [['label' => '500ml', 'cost' => 11.00, 'price' => 25.00]]],
    ];

    $pieceItems = $pieceItems ?? [
        ['name' => 'Burger bun', 'unit' => 'pc', 'onHand' => 18, 'threshold' => 40, 'usedToday' => 52, 'unitCost' => 7.50],
        ['name' => 'Beef patty', 'unit' => 'pc', 'onHand' => 86, 'threshold' => 40, 'usedToday' => 58, 'unitCost' => 24.00],
        ['name' => 'Cheese slice', 'unit' => 'pc', 'onHand' => 140, 'threshold' => 60, 'usedToday' => 44, 'unitCost' => 6.00],
        ['name' => 'Chicken thigh', 'unit' => 'pc', 'onHand' => 4, 'threshold' => 10, 'usedToday' => 11, 'unitCost' => 38.00],
        ['name' => 'Egg', 'unit' => 'pc', 'onHand' => 60, 'threshold' => 30, 'usedToday' => 14, 'unitCost' => 8.50],
        ['name' => 'Cups 16oz', 'unit' => 'pc', 'onHand' => 210, 'threshold' => 100, 'usedToday' => 29, 'unitCost' => 3.20],
        ['name' => 'Cups 22oz', 'unit' => 'pc', 'onHand' => 35, 'threshold' => 100, 'usedToday' => 46, 'unitCost' => 4.10],
    ];

    $bulkItems = $bulkItems ?? [
        ['name' => 'Cooking oil', 'unit' => '1L bottle', 'onHand' => 5.0, 'lastAudit' => 'Thu 9:51 PM', 'dailyUse' => 0.5, 'unitCost' => 145.00],
        ['name' => 'Mayonnaise', 'unit' => '5kg tub', 'onHand' => 2.0, 'lastAudit' => 'Thu 9:51 PM', 'dailyUse' => 0.3, 'unitCost' => 890.00],
        ['name' => 'Ketchup', 'unit' => 'squeeze bottle', 'onHand' => 5.0, 'lastAudit' => 'Thu 9:51 PM', 'dailyUse' => 1.5, 'unitCost' => 68.00],
        ['name' => 'Coffee beans', 'unit' => '1kg bag', 'onHand' => 1.25, 'lastAudit' => 'Thu 9:51 PM', 'dailyUse' => 0.4, 'unitCost' => 780.00],
        ['name' => 'LPG', 'unit' => '11kg tank', 'onHand' => 0.75, 'lastAudit' => 'Thu 9:51 PM', 'dailyUse' => 0.2, 'unitCost' => 1050.00],
    ];
@endphp

<x-app-layout title="Inventory">
    <div x-data="{ tab: 'menu' }" class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-8">
        <x-page-header eyebrow="Inventory" title="Everything you buy, store and sell"
            description="Menu items appear on the register. Pieces are deducted automatically when a menu item sells. Bulk & liquids are counted by eye at closing.">
            <x-slot:actions>
                <button type="button" class="btn-ghost"><x-icon name="upload" class="size-4" /> Import Excel</button>
                <button type="button" class="btn-ghost"><x-icon name="download" class="size-4" /> Export CSV</button>
                <a href="{{ route('admin.inventory.create') }}" class="btn-primary"><x-icon name="plus" class="size-4" /> Add item</a>
            </x-slot:actions>
        </x-page-header>

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="inline-flex w-full gap-1 overflow-x-auto rounded-xl bg-ink-100 p-1 sm:w-auto dark:bg-white/[0.05]">
                @foreach ([['menu', 'Menu items', count($menuItems)], ['pieces', 'Pieces', count($pieceItems)], ['bulk', 'Bulk & liquids', count($bulkItems)]] as [$key, $label, $count])
                    <button type="button" @click="tab = '{{ $key }}'" :class="tab === '{{ $key }}' ? 'tab-active' : ''" class="tab flex shrink-0 items-center gap-2">
                        {{ $label }} <span class="num text-xs text-ink-400">{{ $count }}</span>
                    </button>
                @endforeach
            </div>
            <div class="relative sm:w-64">
                <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-ink-400" />
                <input type="search" placeholder="Find an item" class="field pl-9">
            </div>
        </div>

        {{-- Menu items --}}
        <section x-show="tab === 'menu'" class="surface overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-sm">
                    <thead class="table-head">
                        <tr class="border-b border-ink-200 dark:border-white/[0.07]">
                            <th class="px-5 py-3 font-semibold">Item</th>
                            <th class="px-3 py-3 font-semibold">Size</th>
                            <th class="px-3 py-3 text-right font-semibold">Cost</th>
                            <th class="px-3 py-3 text-right font-semibold">Price</th>
                            <th class="px-3 py-3 text-right font-semibold">Profit</th>
                            <th class="px-3 py-3 text-right font-semibold">Margin</th>
                            <th class="px-5 py-3 font-semibold">Deducts</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink-100 dark:divide-white/[0.05]">
                        @foreach ($menuItems as $item)
                            @foreach ($item['variants'] as $variant)
                                @php($margin = ($variant['price'] - $variant['cost']) / $variant['price'] * 100)
                                <tr @class(['group hover:bg-ink-50 dark:hover:bg-white/[0.02]', 'border-t-0' => ! $loop->first])>
                                    <td class="px-5 py-3">
                                        @if ($loop->first)
                                            <a href="{{ route('admin.inventory.edit', $item['id']) }}" class="font-medium hover:underline">{{ $item['name'] }}</a>
                                            <p class="text-xs text-ink-500">{{ $item['category'] }}</p>
                                        @endif
                                    </td>
                                    <td class="px-3 py-3 text-ink-600 dark:text-ink-300">{{ $variant['label'] }}</td>
                                    <td class="num px-3 py-3 text-right text-ink-500">₱{{ number_format($variant['cost'], 2) }}</td>
                                    <td class="num px-3 py-3 text-right">₱{{ number_format($variant['price'], 2) }}</td>
                                    <td class="num px-3 py-3 text-right font-medium">₱{{ number_format($variant['price'] - $variant['cost'], 2) }}</td>
                                    <td class="px-3 py-3 text-right">
                                        <span @class(['num font-semibold', 'text-gain-600 dark:text-gain-400' => $margin >= 50, 'text-brand-600 dark:text-brand-300' => $margin < 50])>{{ number_format($margin, 1) }}%</span>
                                    </td>
                                    <td class="px-5 py-3 text-xs text-ink-500">
                                        @if ($loop->first)
                                            @if ($item['recipe'])
                                                <span class="inline-flex items-center gap-1.5"><x-icon name="link" class="size-3.5" /> {{ implode(', ', $item['recipe']) }}</span>
                                            @else
                                                <span class="num">{{ $item['stock'] }} in stock · counted as itself</span>
                                            @endif
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        {{-- Pieces --}}
        <section x-show="tab === 'pieces'" x-cloak class="surface overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[720px] text-sm">
                    <thead class="table-head">
                        <tr class="border-b border-ink-200 dark:border-white/[0.07]">
                            <th class="px-5 py-3 font-semibold">Piece</th>
                            <th class="px-3 py-3 font-semibold">On hand</th>
                            <th class="px-3 py-3 text-right font-semibold">Used today</th>
                            <th class="px-3 py-3 text-right font-semibold">Alert at</th>
                            <th class="px-3 py-3 text-right font-semibold">Unit cost</th>
                            <th class="px-5 py-3 text-right font-semibold">Stock value</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink-100 dark:divide-white/[0.05]">
                        @foreach ($pieceItems as $piece)
                            @php($isLow = $piece['onHand'] <= $piece['threshold'])
                            <tr class="hover:bg-ink-50 dark:hover:bg-white/[0.02]">
                                <td class="px-5 py-3 font-medium">
                                    <span class="flex items-center gap-2">
                                        @if ($isLow)<span class="size-2 rounded-full bg-loss-500" title="Low stock"></span>@endif
                                        {{ $piece['name'] }}
                                    </span>
                                </td>
                                <td class="px-3 py-3">
                                    <div class="flex items-center gap-3">
                                        <span @class(['num w-12 font-semibold', 'text-loss-600 dark:text-loss-400' => $isLow])>{{ $piece['onHand'] }}</span>
                                        <div class="h-1.5 w-24 overflow-hidden rounded-full bg-ink-100 dark:bg-white/[0.07]">
                                            <div @class(['h-full rounded-full', 'bg-loss-500' => $isLow, 'bg-ink-400 dark:bg-ink-500' => ! $isLow]) style="width: {{ min(100, round($piece['onHand'] / ($piece['threshold'] * 3) * 100)) }}%"></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="num px-3 py-3 text-right text-ink-500">−{{ $piece['usedToday'] }}</td>
                                <td class="num px-3 py-3 text-right text-ink-500">{{ $piece['threshold'] }}</td>
                                <td class="num px-3 py-3 text-right text-ink-500">₱{{ number_format($piece['unitCost'], 2) }}</td>
                                <td class="num px-5 py-3 text-right">₱{{ number_format($piece['onHand'] * $piece['unitCost'], 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="border-t border-ink-100 px-5 py-3 text-xs text-ink-500 dark:border-white/[0.05]">Pieces never show on the register. They go down when a linked menu item sells.</p>
        </section>

        {{-- Bulk & liquids --}}
        <section x-show="tab === 'bulk'" x-cloak class="space-y-4">
            <div class="surface flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm font-semibold">Counted by eye, once a day</p>
                    <p class="text-xs text-ink-500">Last closing audit: Thu 17 Sep, 9:51 PM by Jessa · took 52 seconds</p>
                </div>
                <a href="{{ route('audit') }}" class="btn-primary"><x-icon name="audit" class="size-4" /> Run tonight's audit</a>
            </div>

            <div class="surface overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[720px] text-sm">
                        <thead class="table-head">
                            <tr class="border-b border-ink-200 dark:border-white/[0.07]">
                                <th class="px-5 py-3 font-semibold">Item</th>
                                <th class="px-3 py-3 text-right font-semibold">On hand</th>
                                <th class="px-3 py-3 text-right font-semibold">Avg use / day</th>
                                <th class="px-3 py-3 text-right font-semibold">Days left</th>
                                <th class="px-3 py-3 text-right font-semibold">Unit cost</th>
                                <th class="px-5 py-3 text-right font-semibold">Daily cost</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ink-100 dark:divide-white/[0.05]">
                            @foreach ($bulkItems as $bulk)
                                @php($daysLeft = $bulk['onHand'] / $bulk['dailyUse'])
                                <tr class="hover:bg-ink-50 dark:hover:bg-white/[0.02]">
                                    <td class="px-5 py-3">
                                        <p class="font-medium">{{ $bulk['name'] }}</p>
                                        <p class="text-xs text-ink-500">per {{ $bulk['unit'] }}</p>
                                    </td>
                                    <td class="num px-3 py-3 text-right font-semibold">{{ rtrim(rtrim(number_format($bulk['onHand'], 2), '0'), '.') }}</td>
                                    <td class="num px-3 py-3 text-right text-ink-500">{{ $bulk['dailyUse'] }}</td>
                                    <td class="px-3 py-3 text-right">
                                        <span @class(['num font-medium', 'text-loss-600 dark:text-loss-400' => $daysLeft < 4])>{{ number_format($daysLeft, 1) }}</span>
                                    </td>
                                    <td class="num px-3 py-3 text-right text-ink-500">₱{{ number_format($bulk['unitCost'], 2) }}</td>
                                    <td class="num px-5 py-3 text-right">₱{{ number_format($bulk['dailyUse'] * $bulk['unitCost'], 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
</x-app-layout>
