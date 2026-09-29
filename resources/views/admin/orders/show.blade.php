<x-app-layout :title="'Order #'.$order->number">
    <div class="mx-auto max-w-3xl space-y-6 px-4 py-8 sm:px-8">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-lg font-semibold">Order #{{ $order->number }}</h1>
                <p class="text-sm text-ink-500">{{ $order->paid_at->format('M j, Y g:i A') }} · {{ $order->cashier_name }} · {{ $order->payment_method->label() }}</p>
                @if ($order->status !== \App\Enums\OrderStatus::Paid)
                    <span class="pill mt-2 bg-loss-500/10 text-loss-700 dark:text-loss-300">{{ $order->status->label() }}</span>
                @endif
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('pos.orders.receipt', $order) }}" target="_blank" class="btn-quiet px-3 py-1.5 text-xs">Receipt</a>
                @if ($order->status === \App\Enums\OrderStatus::Paid)
                    <form method="POST" action="{{ route('pos.orders.void', $order) }}"
                        data-confirm-title="Void order #{{ $order->number }}?" data-confirm="The sale leaves your reports and the stock goes back." data-confirm-action="Void order" data-confirm-danger>
                        @csrf
                        <button type="submit" class="btn-quiet px-3 py-1.5 text-xs text-loss-600 dark:text-loss-400" data-loading-text="Voiding…">Void</button>
                    </form>
                @endif
            </div>
        </div>

        <section class="surface overflow-hidden">
            <div class="px-6 pt-6">
                <h2 class="font-semibold">Items</h2>
            </div>
            <div class="mt-4 overflow-x-auto">
                <table class="w-full min-w-[480px] text-sm">
                    <thead class="table-head">
                        <tr class="border-b border-ink-200 dark:border-white/[0.07]">
                            <th class="px-6 py-2 font-semibold">Item</th>
                            <th class="px-3 py-2 text-right font-semibold">Qty</th>
                            <th class="px-3 py-2 text-right font-semibold">Price</th>
                            <th class="px-6 py-2 text-right font-semibold">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink-100 dark:divide-white/[0.05]">
                        @foreach ($order->lines as $line)
                            <tr>
                                <td class="px-6 py-2.5 font-medium">{{ $line->name }}{{ $line->variant_label !== 'Regular' ? ' · '.$line->variant_label : '' }}</td>
                                <td class="num px-3 py-2.5 text-right">{{ $line->qty }}</td>
                                <td class="num px-3 py-2.5 text-right text-ink-500">₱{{ number_format((float) $line->price, 2) }}</td>
                                <td class="num px-6 py-2.5 text-right font-medium">₱{{ number_format($line->lineTotal(), 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="border-t border-ink-200 font-semibold dark:border-white/[0.07]">
                            <td class="px-6 py-3" colspan="3">Total</td>
                            <td class="num px-6 py-3 text-right">₱{{ number_format((float) $order->subtotal, 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>

        <section class="surface overflow-hidden">
            <div class="px-6 pt-6">
                <h2 class="font-semibold">Stock changes</h2>
                <p class="text-xs text-ink-500">Every ingredient, supply or bulk item this order deducted through its recipe links — and, if voided, put back.</p>
            </div>
            @if ($movements->isEmpty())
                <p class="px-6 py-8 text-sm text-ink-500">This order didn't change any stock — nothing sold was linked or counts itself.</p>
            @else
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full min-w-[480px] text-sm">
                        <thead class="table-head">
                            <tr class="border-b border-ink-200 dark:border-white/[0.07]">
                                <th class="px-6 py-2 font-semibold">Stock</th>
                                <th class="px-3 py-2 font-semibold">Reason</th>
                                <th class="px-6 py-2 text-right font-semibold">Change</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ink-100 dark:divide-white/[0.05]">
                            @foreach ($movements as $movement)
                                <tr>
                                    <td class="px-6 py-2.5 font-medium">
                                        @if ($movement->item)
                                            <a href="{{ route('admin.inventory.edit', $movement->item) }}" class="hover:underline">{{ $movement->item->name }}</a>
                                        @else
                                            Deleted item
                                        @endif
                                    </td>
                                    <td class="px-3 py-2.5 text-ink-500">{{ $movement->reason->label() }}</td>
                                    <td @class([
                                        'num px-6 py-2.5 text-right font-medium',
                                        'text-loss-600 dark:text-loss-400' => (float) $movement->qty_change < 0,
                                        'text-gain-700 dark:text-gain-400' => (float) $movement->qty_change > 0,
                                    ])>
                                        {{ (float) $movement->qty_change > 0 ? '+' : '' }}{{ \App\Models\Item::trimNumber((float) $movement->qty_change, 3) }} {{ $movement->item?->unit ?: ($movement->item?->kind?->value === 'piece' ? 'pc' : '') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
</x-app-layout>
