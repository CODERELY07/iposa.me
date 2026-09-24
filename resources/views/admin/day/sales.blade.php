<x-app-layout :title="'Sales · '.$day->format('M j')">
    <div class="mx-auto max-w-6xl space-y-6 px-4 py-8 sm:px-8">
        @include('admin.day.header')

        <section class="surface p-6">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 class="font-semibold">Sales by payment</h2>
                <p class="text-xs text-ink-500"><span class="num">{{ $data['count'] }}</span> {{ \Illuminate\Support\Str::plural('order', $data['count']) }} counted · voided orders are left out</p>
            </div>
            @if ($data['byMethod']->isEmpty())
                <p class="mt-3 text-sm text-ink-500">No sales on this day.</p>
            @else
                <dl class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                    @foreach ($data['byMethod'] as $method)
                        <div class="rounded-xl bg-ink-100/70 p-3 dark:bg-white/[0.04]">
                            <dt class="text-xs text-ink-500">{{ $method['label'] }} · <span class="num">{{ $method['orders'] }}</span></dt>
                            <dd class="num mt-1 font-semibold">₱{{ number_format($method['amount'], 2) }}</dd>
                        </div>
                    @endforeach
                </dl>
                <p class="mt-3 text-sm">
                    Total <span class="num font-semibold">₱{{ number_format($data['total'], 2) }}</span>
                    · ingredients <span class="num">₱{{ number_format($data['cost'], 2) }}</span>
                    · left after ingredients <span class="num font-semibold">₱{{ number_format($data['total'] - $data['cost'], 2) }}</span>
                </p>
            @endif
        </section>

        <section class="surface overflow-hidden">
            <div class="px-6 pt-6">
                <h2 class="font-semibold">Every order</h2>
                <p class="text-xs text-ink-500">Newest first. Profit here is after ingredients only; bulk and expenses come off the whole day.</p>
            </div>
            @if ($data['orders']->isEmpty())
                <p class="px-6 py-8 text-sm text-ink-500">No orders on this day.</p>
            @else
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full min-w-[760px] text-sm">
                        <thead class="table-head">
                            <tr class="border-b border-ink-200 dark:border-white/[0.07]">
                                <th class="px-6 py-2 font-semibold">Order</th>
                                <th class="px-3 py-2 font-semibold">Items</th>
                                <th class="px-3 py-2 font-semibold">Cashier</th>
                                <th class="px-3 py-2 font-semibold">Paid by</th>
                                <th class="px-3 py-2 text-right font-semibold">Total</th>
                                <th class="px-3 py-2 text-right font-semibold">Cost</th>
                                <th class="px-6 py-2 text-right font-semibold">Profit</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ink-100 dark:divide-white/[0.05]">
                            @foreach ($data['orders'] as $row)
                                @php($order = $row['order'])
                                <tr @class(['align-top', 'text-ink-400 line-through decoration-ink-300' => ! $row['counted']])>
                                    <td class="whitespace-nowrap px-6 py-3">
                                        <a href="{{ route('pos.orders.receipt', $order) }}" class="num font-semibold hover:underline">#{{ $order->number }}</a>
                                        <span class="block text-xs text-ink-500">{{ $order->paid_at->format('g:i A') }}</span>
                                        @if ($order->status !== \App\Enums\OrderStatus::Paid)
                                            <span class="pill mt-1 bg-loss-500/10 text-loss-700 no-underline dark:text-loss-300">{{ $order->status->label() }}</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-3">
                                        @foreach ($order->lines as $line)
                                            <span class="block">{{ $line->qty > 1 ? $line->qty.' × ' : '' }}{{ $line->name }}{{ $line->variant_label !== 'Regular' ? ' · '.$line->variant_label : '' }}</span>
                                        @endforeach
                                    </td>
                                    <td class="px-3 py-3">{{ $order->cashier_name }}</td>
                                    <td class="px-3 py-3">{{ $order->payment_method->label() }}</td>
                                    <td class="num px-3 py-3 text-right font-medium">₱{{ number_format((float) $order->subtotal, 2) }}</td>
                                    <td class="num px-3 py-3 text-right text-ink-500">₱{{ number_format($row['cost'], 2) }}</td>
                                    <td @class(['num px-6 py-3 text-right font-medium', 'text-loss-600 dark:text-loss-400' => $row['counted'] && $row['profit'] < 0])>{{ $row['profit'] < 0 ? '−' : '' }}₱{{ number_format(abs($row['profit']), 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
</x-app-layout>
