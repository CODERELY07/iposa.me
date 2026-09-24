@php($business = auth()->user()->business)

<x-app-layout title="Products">
    <div class="mx-auto max-w-4xl space-y-8 px-4 py-8 sm:px-8">
        <x-page-header
            title="Products"
            :description="$canRestock && $canLink ? 'Add deliveries to the count, and set what one sale uses.' : ($canRestock ? 'Add deliveries to the count.' : 'Set what one sale uses.')" />

        <form method="GET" action="{{ route('staff.products') }}" class="flex gap-2">
            <input type="search" name="q" value="{{ $search }}" class="field flex-1" placeholder="Search products" aria-label="Search products">
            <button type="submit" class="btn-ghost">Search</button>
        </form>

        @if ($canRestock)
            <section class="space-y-3">
                <div>
                    <h2 class="font-semibold">Restock</h2>
                    <p class="text-xs text-ink-500">Enter how many arrived. It is added to what is on the shelf, and the owner checks it against the receipt.</p>
                </div>

                @if ($stockItems->isEmpty())
                    <p class="surface px-5 py-8 text-center text-sm text-ink-500">{{ $search !== '' ? 'Nothing matches “'.$search.'”.' : 'No stock items yet.' }}</p>
                @else
                    <ul class="surface divide-y divide-ink-100 dark:divide-white/[0.06]">
                        @foreach ($stockItems as $item)
                            <li class="flex flex-wrap items-center gap-x-4 gap-y-3 px-4 py-3.5 sm:px-5">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium">{{ $item->name }}</p>
                                    <p @class(['num text-xs', 'text-loss-600 dark:text-loss-400' => $item->isLowStock($business), 'text-ink-500' => ! $item->isLowStock($business)])>
                                        On hand: {{ $item->describeQuantity($item->on_hand) }}{{ $item->isLowStock($business) ? ' · low' : '' }}
                                    </p>
                                </div>
                                <form method="POST" action="{{ route('staff.products.restock', ['item' => $item, 'q' => $search !== '' ? $search : null]) }}" class="flex items-center gap-2">
                                    @csrf
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
                                    <button type="submit" class="btn-primary py-2" data-loading-text="Adding…">Restock</button>
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
