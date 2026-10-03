<x-app-layout :title="'Waste · '.$day->format('M j')">
    <div class="mx-auto max-w-6xl space-y-6 px-4 py-8 sm:px-8">
        @include('admin.day.header')

        <section class="surface overflow-hidden">
            <div class="flex flex-wrap items-start justify-between gap-3 px-6 pt-6">
                <div>
                    <h2 class="font-semibold">Waste</h2>
                    <p class="text-xs text-ink-500">Stock spilled, expired or thrown away, valued at what it cost the day it was logged. It counts against profit, never as cash out. Log it on the item's page, under Restock.</p>
                </div>
                <a href="{{ route('admin.inventory', ['tab' => 'pieces']) }}" class="btn-ghost py-2">Open inventory</a>
            </div>

            @if ($data['unpriced'] > 0)
                <p class="mx-6 mt-4 flex gap-2 rounded-xl bg-loss-500/10 px-4 py-3 text-xs text-loss-700 dark:text-loss-300">
                    <x-icon name="alert" class="mt-0.5 size-3.5 shrink-0" />
                    <span>{{ $data['unpriced'] }} {{ \Illuminate\Support\Str::plural('entry', $data['unpriced']) }} below {{ $data['unpriced'] === 1 ? 'has' : 'have' }} no cost set, so {{ $data['unpriced'] === 1 ? 'it isn\'t' : 'they aren\'t' }} counted in profit. Set the item's cost to value future waste.</span>
                </p>
            @endif

            @if ($data['entries']->isEmpty())
                <p class="px-6 py-8 text-sm text-ink-500">No waste logged on this day.</p>
            @else
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full min-w-[640px] text-sm">
                        <thead class="table-head">
                            <tr class="border-b border-ink-200 dark:border-white/[0.07]">
                                <th class="px-6 py-2 font-semibold">Item</th>
                                <th class="px-3 py-2 text-right font-semibold">Qty</th>
                                <th class="px-3 py-2 text-right font-semibold">Cost each</th>
                                <th class="px-3 py-2 text-right font-semibold">Loss</th>
                                <th class="px-3 py-2 font-semibold">What happened</th>
                                <th class="px-6 py-2"><span class="sr-only">Undo</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ink-100 dark:divide-white/[0.05]">
                            @foreach ($data['entries'] as $entry)
                                <tr @class(['text-ink-400' => $entry['undone']])>
                                    <td class="px-6 py-2.5">
                                        <a href="{{ route('admin.inventory.history', ['item' => $entry['movement']->item_id] + ($entry['movement']->item_variant_id ? ['size' => $entry['movement']->item_variant_id] : [])) }}" @class(['font-medium hover:underline', 'line-through' => $entry['undone']])>{{ $entry['name'] }}{{ $entry['size'] ? ' · '.$entry['size'] : '' }}</a>
                                        <p class="text-xs text-ink-500">{{ $entry['movement']->created_at->format('g:i A') }}{{ $entry['by'] ? ' · '.$entry['by'] : '' }}</p>
                                    </td>
                                    <td class="num px-3 py-2.5 text-right">{{ $entry['qty'] > 0 ? '+' : '' }}{{ \App\Models\Item::trimNumber($entry['qty'], 3) }} {{ $entry['unit'] }}</td>
                                    <td class="num px-3 py-2.5 text-right text-ink-500">{{ $entry['unit_cost'] !== null ? '₱'.\App\Models\Item::formatUnitCost($entry['unit_cost']) : '—' }}</td>
                                    <td @class(['num px-3 py-2.5 text-right font-medium', 'line-through' => $entry['undone']])>
                                        @if ($entry['cost'] === null)
                                            <span class="text-loss-600 dark:text-loss-400">no cost</span>
                                        @else
                                            {{ $entry['cost'] < 0 ? '+' : '−' }}₱{{ number_format(abs($entry['cost']), 2) }}
                                        @endif
                                    </td>
                                    <td class="px-3 py-2.5 text-ink-600 dark:text-ink-300">{{ $entry['note'] ?: '—' }}{{ $entry['undone'] ? ' (undone)' : '' }}</td>
                                    <td class="px-6 py-2.5 text-right">
                                        @if ($entry['undoable'])
                                            <form method="POST" action="{{ route('admin.inventory.waste.undo', $entry['movement']) }}"
                                                data-confirm-title="Undo this waste?" data-confirm="The stock goes back on the shelf and the loss leaves today's profit." data-confirm-action="Undo waste">
                                                @csrf
                                                <button type="submit" class="btn-quiet px-3 py-1.5 text-xs" data-loading-text="…">Undo</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="text-sm">
                            <tr class="border-t border-ink-200 font-semibold dark:border-white/[0.07]">
                                <td class="px-6 py-3" colspan="3">Waste counted against profit</td>
                                <td class="num px-3 py-3 text-right">₱{{ number_format($data['total'], 2) }}</td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif
        </section>
    </div>
</x-app-layout>
