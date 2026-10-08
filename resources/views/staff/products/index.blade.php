@php($business = auth()->user()->business)

<x-app-layout title="Products">
    <div x-data="productsPage()" class="mx-auto max-w-4xl space-y-8 px-4 py-8 sm:px-8">
        <p x-show="! online" x-cloak role="status" class="rounded-2xl border border-brand-400/30 bg-brand-400/10 px-4 py-3 text-sm text-brand-800 dark:text-brand-200">
            You're offline. The counts are as of {{ $loadedAt }}. Deliveries you add now are kept on this device and sync by themselves.
        </p>

        <p x-show="error" x-cloak role="alert" class="rounded-2xl bg-loss-500/10 px-4 py-3 text-sm text-loss-700 dark:text-loss-300" x-text="error"></p>

        <x-page-header
            title="Products"
            :description="$canRestock && $canLink ? 'Add deliveries to the count, and set what one sale uses.' : ($canRestock ? 'Add deliveries to the count.' : 'Set what one sale uses.')" />

        <form method="GET" action="{{ route('staff.products') }}" class="flex gap-2">
            <input type="search" name="q" value="{{ $search }}" x-model="filter" class="field flex-1" placeholder="Search products" aria-label="Search products">
            <button type="submit" class="btn-ghost">Search</button>
        </form>

        @if ($canRestock)
            <section class="space-y-3">
                <div>
                    <h2 class="font-semibold">Restock</h2>
                    <p class="text-xs text-ink-500">Enter how many arrived. It is added to what is on the shelf, and the owner checks it against the receipt.</p>
                </div>

                {{-- Saved on this device, not on the server yet --}}
                <div x-data x-show="$store.offlineQueue.restocks.length" x-cloak class="surface overflow-hidden">
                    <div class="border-b border-ink-100 px-4 py-3 sm:px-5 dark:border-white/[0.06]">
                        <h3 class="text-sm font-semibold">Saved on this device</h3>
                        <p class="text-xs text-ink-500">These sync by themselves when the internet is back.</p>
                    </div>
                    <ul class="divide-y divide-ink-100 dark:divide-white/[0.06]">
                        <template x-for="entry in $store.offlineQueue.restocks" :key="entry.uuid">
                            <li class="flex flex-wrap items-center gap-x-4 gap-y-1 px-4 py-3 sm:px-5">
                                <span class="num w-24 shrink-0 text-sm font-semibold" x-text="entry.summary.display"></span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm" x-text="entry.summary.lines.join(', ')"></p>
                                    <p class="text-xs" :class="entry.status === 'failed' ? 'text-loss-600 dark:text-loss-400' : 'text-ink-500'" x-text="entry.status === 'failed' ? entry.error : 'Waiting to sync'"></p>
                                </div>
                                <div class="flex items-center gap-1">
                                    <button type="button" x-show="entry.status === 'failed'" @click="$store.offlineQueue.retry(entry)" class="btn-quiet px-2 text-xs">Retry</button>
                                    <button type="button" x-show="entry.status === 'failed'" @click="$store.confirm.ask({ title: 'Discard this delivery?', message: 'It was refused by the server and will never be added.', action: 'Discard', danger: true }).then((ok) => ok !== false && $store.offlineQueue.discard(entry.uuid))" class="btn-quiet px-2 text-xs text-loss-600 dark:text-loss-400">Discard</button>
                                </div>
                            </li>
                        </template>
                    </ul>
                </div>

                @if ($stockItems->isEmpty())
                    <p class="surface px-5 py-8 text-center text-sm text-ink-500">{{ $search !== '' ? 'Nothing matches “'.$search.'”.' : 'No stock items yet.' }}</p>
                @else
                    <ul class="surface divide-y divide-ink-100 dark:divide-white/[0.06]">
                        @foreach ($stockItems as $item)
                            <li x-show="! filter || $el.dataset.name.includes(filter.toLowerCase().trim())" data-name="{{ \Illuminate\Support\Str::lower($item->name) }}" class="flex flex-wrap items-center gap-x-4 gap-y-3 px-4 py-3.5 sm:px-5">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium">{{ $item->name }}</p>
                                    <p x-data x-show="waitingFor({{ $item->id }}).length" x-cloak class="num text-xs text-brand-700 dark:text-brand-300" x-text="waitingFor({{ $item->id }}).join(', ') + ' waiting to sync'"></p>
                                    @if ($item->tracksStockPerSize())
                                        @foreach ($item->variants->filter->tracksStock() as $size)
                                            @php($sizeLow = $item->isVariantLowStock($size, $business))
                                            <p @class(['num text-xs', 'text-loss-600 dark:text-loss-400' => $sizeLow, 'text-ink-500' => ! $sizeLow])>
                                                {{ $size->label }} on hand: {{ $item->describeQuantity($size->on_hand) }}{{ $sizeLow ? ' · low' : '' }}
                                            </p>
                                        @endforeach
                                    @else
                                        <p @class(['num text-xs', 'text-loss-600 dark:text-loss-400' => $item->isLowStock($business), 'text-ink-500' => ! $item->isLowStock($business)])>
                                            On hand: {{ $item->describeQuantity($item->on_hand) }}{{ $item->isLowStock($business) ? ' · low' : '' }}
                                        </p>
                                    @endif
                                </div>
                                <form method="POST" action="{{ route('staff.products.restock', ['item' => $item, 'q' => $search !== '' ? $search : null]) }}" data-url="{{ route('staff.products.restock', $item, absolute: false) }}"
                                    @submit.prevent="restock($el, @js(['id' => $item->id, 'name' => $item->name, 'unit' => $item->unit ?: 'pc']))" class="flex items-center gap-2">
                                    @csrf
                                    @if ($item->tracksStockPerSize())
                                        <select name="item_variant_id" class="field w-32" aria-label="Size of {{ $item->name }}">
                                            @foreach ($item->variants->filter->tracksStock() as $size)
                                                <option value="{{ $size->id }}">{{ $size->label }}</option>
                                            @endforeach
                                        </select>
                                    @endif
                                    <input name="quantity" type="number" min="0" step="any" required value="1" class="field num w-20 text-center" aria-label="How many arrived for {{ $item->name }}">
                                    @if ($item->containers->isNotEmpty())
                                        <select name="container_id" class="field w-40" aria-label="Container">
                                            @foreach ($item->containers as $container)
                                                <option value="{{ $container->id }}">{{ $container->label }} ({{ \App\Models\Item::trimNumber((float) $container->size) }} {{ $item->unit }})</option>
                                            @endforeach
                                        </select>
                                    @else
                                        <span class="w-10 text-sm text-ink-500">{{ $item->unit ?: 'pc' }}</span>
                                    @endif
                                    <button type="submit" :disabled="saving" class="btn-primary py-2">Restock</button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @endif

        @if ($canLink)
            <section class="space-y-3">
                <div>
                    <h2 class="font-semibold">What one sale uses</h2>
                    <p class="text-xs text-ink-500">Each sale takes these off the shelf: 1 bun, 15 ml ketchup. Changes need the owner's approval.</p>
                </div>

                @if ($menuItems->isEmpty())
                    <p class="surface px-5 py-8 text-center text-sm text-ink-500">{{ $search !== '' ? 'Nothing matches “'.$search.'”.' : 'No menu items yet.' }}</p>
                @else
                    <ul class="surface divide-y divide-ink-100 dark:divide-white/[0.06]">
                        @foreach ($menuItems as $item)
                            <li class="flex flex-wrap items-center gap-x-4 gap-y-2 px-4 py-3.5 sm:px-5">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium">{{ $item->name }}
                                        @if ($pendingItemIds->has($item->id))
                                            <span class="pill ml-1 bg-brand-400/15 text-brand-700 dark:text-brand-300">waiting for owner</span>
                                        @endif
                                    </p>
                                    <p class="truncate text-xs text-ink-500">
                                        @if ($item->recipeLines->isEmpty())
                                            No links
                                        @else
                                            {{ $item->recipeLines->map(fn ($line) => \App\Models\Item::trimNumber((float) $line->qty).' '.($line->piece?->unit ? $line->piece->unit.' ' : '× ').($line->piece?->name ?? '?').($line->variant ? ' ('.$line->variant->label.')' : ''))->join(', ') }}
                                        @endif
                                    </p>
                                </div>
                                <a href="{{ route('staff.products.links', $item) }}" class="btn-ghost py-2"><x-icon name="link" class="size-4" /> Edit links</a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @endif
    </div>
</x-app-layout>
