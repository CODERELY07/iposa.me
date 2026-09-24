<x-app-layout :title="'Ingredients · '.$day->format('M j')">
    <div class="mx-auto max-w-6xl space-y-6 px-4 py-8 sm:px-8">
        @include('admin.day.header')

        <section class="surface overflow-hidden">
            <div class="px-6 pt-6">
                <h2 class="font-semibold">What the sales cost</h2>
                <p class="text-xs text-ink-500">Each size sold, at the cost it had when it was rung up. These add up to the Ingredients number.</p>
            </div>
            @if ($data['sold']->isEmpty())
                <p class="px-6 py-8 text-sm text-ink-500">Nothing sold on this day.</p>
            @else
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full min-w-[560px] text-sm">
                        <thead class="table-head">
                            <tr class="border-b border-ink-200 dark:border-white/[0.07]">
                                <th class="px-6 py-2 font-semibold">Item</th>
                                <th class="px-3 py-2 text-right font-semibold">Sold</th>
                                <th class="px-3 py-2 text-right font-semibold">Cost each</th>
                                <th class="px-3 py-2 text-right font-semibold">Sales</th>
                                <th class="px-6 py-2 text-right font-semibold">Cost</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ink-100 dark:divide-white/[0.05]">
                            @foreach ($data['sold'] as $row)
                                <tr>
                                    <td class="px-6 py-2.5 font-medium">{{ $row['name'] }}</td>
                                    <td class="num px-3 py-2.5 text-right">{{ $row['qty'] }}</td>
                                    <td class="num px-3 py-2.5 text-right text-ink-500">₱{{ number_format($row['cost_each'], 2) }}</td>
                                    <td class="num px-3 py-2.5 text-right text-ink-500">₱{{ number_format($row['sales'], 2) }}</td>
                                    <td class="num px-6 py-2.5 text-right font-medium">₱{{ number_format($row['cost'], 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="border-t border-ink-200 font-semibold dark:border-white/[0.07]">
                                <td class="px-6 py-3" colspan="4">Ingredients</td>
                                <td class="num px-6 py-3 text-right">₱{{ number_format($data['total'], 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif
        </section>

        <section class="surface overflow-hidden">
            <div class="px-6 pt-6">
                <h2 class="font-semibold">What the sales took off the shelf</h2>
                <p class="text-xs text-ink-500">Pieces and liquids deducted through links, and items that count themselves. Voided orders are already put back.</p>
            </div>
            @if ($data['taken']->isEmpty())
                <p class="px-6 py-8 text-sm text-ink-500">No stock was deducted by sales on this day.</p>
            @else
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full min-w-[560px] text-sm">
                        <thead class="table-head">
                            <tr class="border-b border-ink-200 dark:border-white/[0.07]">
                                <th class="px-6 py-2 font-semibold">Stock</th>
                                <th class="px-3 py-2 text-right font-semibold">Taken off</th>
                                <th class="px-6 py-2 font-semibold">Its cost</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ink-100 dark:divide-white/[0.05]">
                            @foreach ($data['taken'] as $row)
                                <tr>
                                    <td class="px-6 py-2.5 font-medium">{{ $row['name'] }}</td>
                                    <td class="num px-3 py-2.5 text-right">{{ \App\Models\Item::trimNumber($row['qty'], 3) }} {{ $row['unit'] }}</td>
                                    <td class="px-6 py-2.5 text-xs">
                                        @if ($row['kind'] === \App\Enums\ItemKind::Menu)
                                            <span class="text-ink-500">Sold as itself: its cost is in the item's cost above.</span>
                                        @elseif ($row['uncosted'] <= 0)
                                            <span class="text-gain-700 dark:text-gain-400">Counted in Ingredients (Include in cost is on).</span>
                                        @elseif ($row['costed'] <= 0)
                                            <span class="text-loss-700 dark:text-loss-300">Not added by the app. Only right if the cost you typed for these items already includes it.</span>
                                        @else
                                            <span class="text-loss-700 dark:text-loss-300">{{ \App\Models\Item::trimNumber($row['costed'], 3) }} {{ $row['unit'] }} counted in Ingredients; {{ \App\Models\Item::trimNumber($row['uncosted'], 3) }} {{ $row['unit'] }} only if your typed cost includes it.</span>
                                        @endif
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
