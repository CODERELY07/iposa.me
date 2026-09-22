@php
    $formatQty = fn ($value) => $value === null ? '—' : rtrim(rtrim(number_format((float) $value, 2), '0'), '.');
    $tabs = [
        ['key' => 'menu', 'label' => 'Menu items', 'count' => $menuItems->count()],
        ['key' => 'pieces', 'label' => 'Pieces', 'count' => $pieceItems->count()],
        ['key' => 'bulk', 'label' => 'Bulk & liquids', 'count' => $bulkItems->count()],
    ];
    $newKind = ['menu' => 'menu', 'pieces' => 'piece', 'bulk' => 'bulk'][$tab];
@endphp

<x-app-layout title="Inventory">
    <div x-data="{ tab: @js($tab), importOpen: {{ session('import_errors') ? 'true' : 'false' }} }" class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-8">
        <x-page-header eyebrow="Inventory" :title="$showArchived ? 'Archived items' : 'Everything you buy, store and sell'"
            description="Menu items appear on the register. Pieces are deducted automatically when a menu item sells. Bulk & liquids are counted by eye at closing.">
            <x-slot:actions>
                <button type="button" @click="importOpen = ! importOpen" class="btn-ghost"><x-icon name="upload" class="size-4" /> Import CSV</button>
                <a href="{{ route('admin.exports.download', 'menu') }}" download class="btn-ghost"><x-icon name="download" class="size-4" /> Export</a>
                <a :href="@js(route('admin.inventory.create')) + '?kind=' + ({ menu: 'menu', pieces: 'piece', bulk: 'bulk' })[tab]" href="{{ route('admin.inventory.create', ['kind' => $newKind]) }}" class="btn-primary"><x-icon name="plus" class="size-4" /> Add item</a>
            </x-slot:actions>
        </x-page-header>

        {{-- Import --}}
        <section x-show="importOpen" x-cloak class="surface p-5">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-xl">
                    <h2 class="font-semibold">Import your menu from Excel</h2>
                    <p class="mt-1 text-sm text-ink-500">
                        Save your sheet as <strong>CSV</strong> with a header row: <code class="num rounded bg-ink-100 px-1 dark:bg-white/10">name, category, size, cost, price</code>.
                        One row per size. Items with the same name become one tile with several sizes. Existing items get their sizes updated.
                    </p>
                </div>
                <form method="POST" action="{{ route('admin.inventory.import') }}" enctype="multipart/form-data" class="flex flex-wrap items-center gap-2">
                    @csrf
                    <input type="file" name="file" accept=".csv,text/csv" required class="block text-sm text-ink-600 file:mr-3 file:rounded-lg file:border-0 file:bg-ink-100 file:px-3 file:py-2 file:text-sm file:font-medium dark:text-ink-300 dark:file:bg-white/10 dark:file:text-ink-100">
                    <button type="submit" class="btn-primary" data-loading-text="Importing…">Import</button>
                </form>
            </div>
            @if (session('import_errors'))
                <ul class="mt-4 space-y-1 rounded-xl bg-loss-500/10 p-3 text-sm text-loss-700 dark:text-loss-300">
                    @foreach (session('import_errors') as $importError)
                        <li>{{ $importError }}</li>
                    @endforeach
                </ul>
            @endif
        </section>

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="inline-flex w-full gap-1 overflow-x-auto rounded-xl bg-ink-100 p-1 sm:w-auto dark:bg-white/[0.05]" role="tablist">
                @foreach ($tabs as $tabInfo)
                    <button type="button" role="tab" @click="tab = '{{ $tabInfo['key'] }}'; history.replaceState(null, '', '?tab={{ $tabInfo['key'] }}{{ $showArchived ? '&archived=1' : '' }}')"
                        :aria-selected="tab === '{{ $tabInfo['key'] }}'" :class="tab === '{{ $tabInfo['key'] }}' ? 'tab-active' : ''" class="tab flex shrink-0 items-center gap-2">
                        {{ $tabInfo['label'] }} <span class="num text-xs text-ink-400">{{ $tabInfo['count'] }}</span>
                    </button>
                @endforeach
            </div>
            <div class="flex items-center gap-3">
                <a :href="'{{ route('admin.inventory') }}?tab=' + tab{{ $showArchived ? '' : " + '&archived=1'" }}" class="text-xs font-medium text-ink-500 hover:text-ink-900 dark:hover:text-white">
                    {{ $showArchived ? '← Back to active items' : 'Show archived' }}
                </a>
                <form method="GET" action="{{ route('admin.inventory') }}" class="relative sm:w-64">
                    <input type="hidden" name="tab" :value="tab">
                    @if ($showArchived)<input type="hidden" name="archived" value="1">@endif
                    <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-ink-400" />
                    <input type="search" name="q" value="{{ $search }}" placeholder="Find an item" class="field pl-9" aria-label="Find an item">
                </form>
            </div>
        </div>

        {{-- Menu items --}}
        <section x-show="tab === 'menu'" class="surface overflow-hidden">
            @if ($menuItems->isEmpty())
                <div class="px-6 py-14 text-center">
                    <p class="font-medium">{{ $search !== '' ? 'No menu items match.' : 'No menu items yet' }}</p>
                    <p class="mt-1 text-sm text-ink-500">Add burgers, drinks and sides with their sizes, cost and price, or import your Excel sheet.</p>
                    <a href="{{ route('admin.inventory.create', ['kind' => 'menu']) }}" class="btn-primary mt-4"><x-icon name="plus" class="size-4" /> Add a menu item</a>
                </div>
            @else
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
                                @forelse ($item->variants as $variant)
                                    @php($margin = $variant->marginPercent())
                                    <tr class="hover:bg-ink-50 dark:hover:bg-white/[0.02]">
                                        <td class="px-5 py-3">
                                            @if ($loop->first)
                                                <a href="{{ route('admin.inventory.edit', $item) }}" class="font-medium hover:underline">{{ $item->name }}</a>
                                                <p class="text-xs text-ink-500">{{ $item->category?->name ?? 'No category' }}</p>
                                            @endif
                                        </td>
                                        <td class="px-3 py-3 text-ink-600 dark:text-ink-300">{{ $variant->label }}</td>
                                        <td class="num px-3 py-3 text-right text-ink-500">₱{{ number_format((float) $variant->cost, 2) }}</td>
                                        <td class="num px-3 py-3 text-right">₱{{ number_format((float) $variant->price, 2) }}</td>
                                        <td class="num px-3 py-3 text-right font-medium">₱{{ number_format($variant->profit(), 2) }}</td>
                                        <td class="px-3 py-3 text-right">
                                            @if ($margin !== null)
                                                <span @class(['num font-semibold', 'text-gain-600 dark:text-gain-400' => $margin >= 50, 'text-brand-600 dark:text-brand-300' => $margin < 50 && $margin >= 25, 'text-loss-600 dark:text-loss-400' => $margin < 25])>{{ number_format($margin, 1) }}%</span>
                                            @else
                                                <span class="text-ink-400">—</span>
                                            @endif
                                        </td>
                                        <td class="px-5 py-3 text-xs text-ink-500">
                                            @if ($loop->first)
                                                @if ($item->recipeLines->isNotEmpty())
                                                    <span class="inline-flex items-center gap-1.5"><x-icon name="link" class="size-3.5" /> {{ $item->recipeLines->map(fn ($line) => ((float) $line->qty != 1 ? $formatQty($line->qty).' × ' : '').($line->piece?->name ?? '?').($line->variant ? ' ('.$line->variant->label.')' : ''))->join(', ') }}</span>
                                                @elseif ($item->tracksStock())
                                                    <span class="num">{{ $formatQty($item->on_hand) }} in stock · counted as itself</span>
                                                @else
                                                    <span class="text-ink-400">Not tracked</span>
                                                @endif
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="px-5 py-3"><a href="{{ route('admin.inventory.edit', $item) }}" class="font-medium hover:underline">{{ $item->name }}</a></td>
                                        <td colspan="6" class="px-3 py-3 text-xs text-loss-600 dark:text-loss-400">No sizes yet: it won't show on the register.</td>
                                    </tr>
                                @endforelse
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        {{-- Pieces --}}
        <section x-show="tab === 'pieces'" x-cloak class="surface overflow-hidden">
            @if ($pieceItems->isEmpty())
                <div class="px-6 py-14 text-center">
                    <p class="font-medium">No pieces yet</p>
                    <p class="mt-1 text-sm text-ink-500">Buns, patties, cheese slices, cups. Link them to menu items and every sale deducts them.</p>
                    <a href="{{ route('admin.inventory.create', ['kind' => 'piece']) }}" class="btn-primary mt-4"><x-icon name="plus" class="size-4" /> Add a piece</a>
                </div>
            @else
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
                                @php($isLow = $piece->isLowStock($business))
                                @php($threshold = $piece->effectiveLowThreshold($business))
                                <tr class="hover:bg-ink-50 dark:hover:bg-white/[0.02]">
                                    <td class="px-5 py-3 font-medium">
                                        <a href="{{ route('admin.inventory.edit', $piece) }}" class="flex items-center gap-2 hover:underline">
                                            @if ($isLow)<span class="size-2 rounded-full bg-loss-500" title="Low stock"></span>@endif
                                            {{ $piece->name }}
                                        </a>
                                    </td>
                                    <td class="px-3 py-3">
                                        <div class="flex items-center gap-3">
                                            <span @class(['num w-14 font-semibold', 'text-loss-600 dark:text-loss-400' => $isLow])>{{ $formatQty($piece->on_hand) }}</span>
                                            <div class="h-1.5 w-24 overflow-hidden rounded-full bg-ink-100 dark:bg-white/[0.07]">
                                                <div @class(['h-full rounded-full', 'bg-loss-500' => $isLow, 'bg-ink-400 dark:bg-ink-500' => ! $isLow]) style="width: {{ $threshold > 0 ? min(100, max(0, round((float) $piece->on_hand / ($threshold * 3) * 100))) : 100 }}%"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="num px-3 py-3 text-right text-ink-500">{{ isset($usedToday[$piece->id]) ? '−'.$formatQty($usedToday[$piece->id]) : '—' }}</td>
                                    <td class="num px-3 py-3 text-right text-ink-500">{{ $formatQty($threshold) }}</td>
                                    <td class="num px-3 py-3 text-right text-ink-500">₱{{ \App\Models\Item::formatUnitCost($piece->unit_cost) }}</td>
                                    <td class="num px-5 py-3 text-right">₱{{ number_format(max(0, (float) $piece->on_hand) * (float) $piece->unit_cost, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="border-t border-ink-100 px-5 py-3 text-xs text-ink-500 dark:border-white/[0.05]">Pieces never show on the register. They go down when a linked menu item sells. Edit a piece to add stock.</p>
            @endif
        </section>

        {{-- Bulk & liquids --}}
        <section x-show="tab === 'bulk'" x-cloak class="space-y-4">
            <div class="surface flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm font-semibold">Counted by eye, once a day</p>
                    <p class="text-xs text-ink-500">
                        @if ($lastAudit)
                            Last closing audit: {{ $lastAudit->date->format('D j M') }}, {{ $lastAudit->submitted_at->format('g:i A') }} by {{ $lastAudit->counted_by }}{{ $lastAudit->duration_seconds ? ' · took '.$lastAudit->duration_seconds.' seconds' : '' }}
                        @else
                            No closing audit yet.
                        @endif
                    </p>
                </div>
                <a href="{{ route('audit') }}" class="btn-primary"><x-icon name="audit" class="size-4" /> Run tonight's audit</a>
            </div>

            <div class="surface overflow-hidden">
                @if ($bulkItems->isEmpty())
                    <div class="px-6 py-14 text-center">
                        <p class="font-medium">No bulk items yet</p>
                        <p class="mt-1 text-sm text-ink-500">Cooking oil, mayo tubs, ketchup bottles, LPG. Counted in decimals at closing.</p>
                        <a href="{{ route('admin.inventory.create', ['kind' => 'bulk']) }}" class="btn-primary mt-4"><x-icon name="plus" class="size-4" /> Add a bulk item</a>
                    </div>
                @else
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
                                    @php($dailyUse = $bulkDailyUse[$bulk->id] ?? null)
                                    @php($daysLeft = $dailyUse ? (float) $bulk->on_hand / $dailyUse : null)
                                    <tr class="hover:bg-ink-50 dark:hover:bg-white/[0.02]">
                                        <td class="px-5 py-3">
                                            <a href="{{ route('admin.inventory.edit', $bulk) }}" class="font-medium hover:underline">{{ $bulk->name }}</a>
                                            @if ($bulk->hasContainers())
                                                <p class="text-xs text-ink-500">{{ $bulk->containers->map(fn ($container) => '1 '.$container->label.' = '.\App\Models\Item::trimNumber((float) $container->size).' '.$bulk->unit)->join(' · ') }}</p>
                                            @else
                                                <p class="text-xs text-ink-500">per {{ $bulk->unit ?? 'unit' }}</p>
                                            @endif
                                        </td>
                                        @if ($bulk->hasContainers())
                                            <td class="num px-3 py-3 text-right font-semibold">{{ $bulk->describeQuantity($bulk->on_hand) }}</td>
                                            <td class="num px-3 py-3 text-right text-ink-500">{{ $dailyUse ? \App\Models\Item::trimNumber($dailyUse).' '.$bulk->unit : '—' }}</td>
                                        @else
                                            <td class="num px-3 py-3 text-right font-semibold">{{ $formatQty($bulk->on_hand) }}</td>
                                            <td class="num px-3 py-3 text-right text-ink-500">{{ $formatQty($dailyUse) }}</td>
                                        @endif
                                        <td class="px-3 py-3 text-right">
                                            @if ($daysLeft !== null)
                                                <span @class(['num font-medium', 'text-loss-600 dark:text-loss-400' => $daysLeft < 4])>{{ number_format($daysLeft, 1) }}</span>
                                            @else
                                                <span class="text-ink-400">—</span>
                                            @endif
                                        </td>
                                        <td class="num px-3 py-3 text-right text-ink-500">
                                            @if ($bulk->hasContainers() && $bulk->containers->first()->price !== null)
                                                ₱{{ number_format((float) $bulk->containers->first()->price, 2) }} / {{ $bulk->containers->first()->label }}
                                                <span class="block text-xs">₱{{ \App\Models\Item::formatUnitCost($bulk->unit_cost) }} / {{ $bulk->unit }}</span>
                                            @else
                                                ₱{{ \App\Models\Item::formatUnitCost($bulk->unit_cost) }}
                                            @endif
                                        </td>
                                        <td class="num px-5 py-3 text-right">{{ $dailyUse ? '₱'.number_format($dailyUse * (float) $bulk->unit_cost, 2) : '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </section>
    </div>
</x-app-layout>
