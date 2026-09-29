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

        <section class="surface p-6">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 class="font-semibold">Cash drawer</h2>
                <p class="text-xs text-ink-500">Starting cash + cash sales − expenses logged today.</p>
            </div>

            <dl class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div class="rounded-xl bg-ink-100/70 p-3 dark:bg-white/[0.04]">
                    <dt class="text-xs text-ink-500">Starting cash</dt>
                    <dd class="num mt-1 font-semibold">₱{{ number_format($data['cashFloat']['starting'], 2) }}</dd>
                </div>
                <div class="rounded-xl bg-ink-100/70 p-3 dark:bg-white/[0.04]">
                    <dt class="text-xs text-ink-500">Cash sales</dt>
                    <dd class="num mt-1 font-semibold">₱{{ number_format($data['cashFloat']['cashSales'], 2) }}</dd>
                </div>
                <div class="rounded-xl bg-ink-100/70 p-3 dark:bg-white/[0.04]">
                    <dt class="text-xs text-ink-500">Paid out (expenses)</dt>
                    <dd class="num mt-1 font-semibold">−₱{{ number_format($data['cashFloat']['cashOut'], 2) }}</dd>
                </div>
                <div class="rounded-xl bg-brand-400/10 p-3">
                    <dt class="text-xs text-ink-500">Should be in drawer</dt>
                    <dd class="num mt-1 font-semibold">₱{{ number_format($data['cashFloat']['expected'], 2) }}</dd>
                </div>
            </dl>

            @if ($data['cashFloat']['counted'] !== null)
                @php($variance = $data['cashFloat']['variance'])
                <p class="mt-3 text-sm">
                    Counted <span class="num font-semibold">₱{{ number_format($data['cashFloat']['counted'], 2) }}</span>
                    @if (abs($variance) < 0.005)
                        <span class="text-gain-700 dark:text-gain-400">— matches exactly.</span>
                    @else
                        <span @class(['num font-semibold', 'text-loss-600 dark:text-loss-400' => $variance < 0, 'text-gain-700 dark:text-gain-400' => $variance > 0])>— {{ $variance > 0 ? 'over' : 'short' }} by ₱{{ number_format(abs($variance), 2) }}</span>
                    @endif
                </p>
            @endif

            @if ($data['cashFloat']['isToday'])
                <form method="POST" action="{{ route('admin.cash-float.store') }}" class="mt-4 grid gap-4 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
                    @csrf
                    <div>
                        <label class="field-label" for="starting_amount">Starting cash</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-ink-400">₱</span>
                            <input id="starting_amount" name="starting_amount" type="number" step="any" min="0" value="{{ old('starting_amount', $data['cashFloat']['starting']) }}" required class="field num pl-7">
                        </div>
                    </div>
                    <div>
                        <label class="field-label" for="counted_amount">Counted (end of day)</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-ink-400">₱</span>
                            <input id="counted_amount" name="counted_amount" type="number" step="any" min="0" value="{{ old('counted_amount', $data['cashFloat']['counted']) }}" class="field num pl-7" placeholder="Not counted yet">
                        </div>
                    </div>
                    <button type="submit" class="btn-primary" data-loading-text="Saving…">Save</button>
                </form>
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
                                        <a href="{{ route('admin.orders.show', $order) }}" class="num font-semibold hover:underline">#{{ $order->number }}</a>
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
