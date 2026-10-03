<x-app-layout :title="$item->name.' · Stock history'">
    <div class="mx-auto max-w-4xl space-y-6 px-4 py-8 sm:px-8">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-lg font-semibold">{{ $item->name }}</h1>
                <p class="text-sm text-ink-500">
                    Stock history
                    @if ($variant)
                        · {{ $variant->label }}
                    @endif
                    @if ($tracked)
                        · on hand {{ \App\Models\Item::trimNumber((float) $onHand, 3) }} {{ $item->unit ?: ($item->kind === \App\Enums\ItemKind::Piece ? 'pc' : '') }}
                    @endif
                </p>
                @if ($sizes->count() > 1)
                    <div class="mt-3 inline-flex gap-1 rounded-xl bg-ink-100 p-1 dark:bg-white/[0.05]">
                        @foreach ($sizes as $size)
                            <a href="{{ route('admin.inventory.history', ['item' => $item, 'size' => $size->id]) }}" @class(['tab', 'tab-active' => $variant?->is($size)])>{{ $size->label }}</a>
                        @endforeach
                    </div>
                @endif
            </div>
            <a href="{{ route('admin.inventory.edit', $item) }}" class="btn-quiet px-3 py-1.5 text-xs">Back to item</a>
        </div>

        <section class="surface overflow-hidden">
            @if ($movements->isEmpty())
                <p class="px-6 py-8 text-sm text-ink-500">No stock changes recorded yet.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[640px] text-sm">
                        <thead class="table-head">
                            <tr class="border-b border-ink-200 dark:border-white/[0.07]">
                                <th class="px-6 py-2 font-semibold">Date</th>
                                <th class="px-3 py-2 font-semibold">Reason</th>
                                <th class="px-3 py-2 text-right font-semibold">Change</th>
                                @if ($tracked)
                                    <th class="px-3 py-2 text-right font-semibold">Balance</th>
                                @endif
                                <th class="px-6 py-2 font-semibold">Source</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ink-100 dark:divide-white/[0.05]">
                            @foreach ($movements as $movement)
                                <tr>
                                    <td class="whitespace-nowrap px-6 py-2.5 text-ink-500">{{ $movement->created_at->format('M j, g:i A') }}</td>
                                    <td class="px-3 py-2.5">{{ $movement->reason->label() }}</td>
                                    <td @class([
                                        'num px-3 py-2.5 text-right font-medium',
                                        'text-loss-600 dark:text-loss-400' => (float) $movement->qty_change < 0,
                                        'text-gain-700 dark:text-gain-400' => (float) $movement->qty_change > 0,
                                    ])>
                                        {{ (float) $movement->qty_change > 0 ? '+' : '' }}{{ \App\Models\Item::trimNumber((float) $movement->qty_change, 3) }} {{ $item->unit ?: ($item->kind === \App\Enums\ItemKind::Piece ? 'pc' : '') }}
                                    </td>
                                    @if ($tracked)
                                        <td class="num px-3 py-2.5 text-right text-ink-500">{{ \App\Models\Item::trimNumber((float) $movement->balance_after, 3) }}</td>
                                    @endif
                                    <td class="px-6 py-2.5">
                                        @if ($movement->order_id)
                                            <a href="{{ route('admin.orders.show', $movement->order_id) }}" class="hover:underline">Order #{{ $movement->order?->number ?? $movement->order_id }}</a>
                                        @elseif ($movement->audit_id)
                                            <a href="{{ route('admin.day', ['section' => 'bulk', 'date' => ($movement->audit?->date ?? $movement->created_at)->toDateString()]) }}" class="hover:underline">Closing audit</a>
                                        @elseif ($movement->note || $movement->reason === \App\Enums\StockMovementReason::Waste)
                                            <span class="text-ink-600 dark:text-ink-300">{{ $movement->note ?: 'Waste' }}</span>
                                            @if ($movement->reason === \App\Enums\StockMovementReason::Waste && (float) $movement->qty_change < 0)
                                                @if ($movement->undoneBy)
                                                    <span class="ml-2 text-xs text-ink-400">(undone)</span>
                                                @elseif ($movement->created_at->isToday())
                                                    <form method="POST" action="{{ route('admin.inventory.waste.undo', $movement) }}" class="ml-2 inline"
                                                        data-confirm-title="Undo this waste?" data-confirm="The stock goes back on the shelf and the loss leaves today's profit." data-confirm-action="Undo waste">
                                                        @csrf
                                                        <button type="submit" class="text-xs font-medium text-brand-600 hover:underline dark:text-brand-300">Undo</button>
                                                    </form>
                                                @endif
                                            @endif
                                        @else
                                            <span class="text-ink-500">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-ink-200 px-6 py-4 dark:border-white/[0.07]">
                    {{ $movements->links() }}
                </div>
            @endif
        </section>
    </div>
</x-app-layout>
