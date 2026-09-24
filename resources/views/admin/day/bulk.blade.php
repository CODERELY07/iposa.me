<x-app-layout :title="'Closing count · '.$day->format('M j')">
    <div class="mx-auto max-w-6xl space-y-6 px-4 py-8 sm:px-8">
        @include('admin.day.header')

        <section class="surface overflow-hidden">
            <div class="px-6 pt-6">
                <h2 class="font-semibold">Closing count</h2>
                @if ($data['audit'])
                    <p class="text-xs text-ink-500">Counted by {{ $data['audit']->counted_by }}{{ $data['audit']->submitted_at ? ' at '.$data['audit']->submitted_at->format('g:i A') : '' }}. Only what the recipes don't explain is charged here, so nothing is counted twice.</p>
                @endif
            </div>
            @if (! $data['audit'])
                <div class="px-6 py-8 text-sm text-ink-500">
                    No closing count for this day yet, so profit doesn't include what was used from the shelf.
                    @if ($day->isToday())
                        <a href="{{ route('audit') }}" class="font-medium text-brand-600 hover:underline dark:text-brand-300">Start the closing audit</a>.
                    @endif
                </div>
            @elseif ($data['lines']->isEmpty())
                <p class="px-6 py-8 text-sm text-ink-500">Nothing was on the count list.</p>
            @else
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full min-w-[720px] text-sm">
                        <thead class="table-head">
                            <tr class="border-b border-ink-200 dark:border-white/[0.07]">
                                <th class="px-6 py-2 font-semibold">Item</th>
                                <th class="px-3 py-2 text-right font-semibold">Expected</th>
                                <th class="px-3 py-2 text-right font-semibold">Counted</th>
                                <th class="px-3 py-2 font-semibold">What it means</th>
                                <th class="px-6 py-2 text-right font-semibold">Cost</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ink-100 dark:divide-white/[0.05]">
                            @foreach ($data['lines'] as $row)
                                @php($qty = fn (float $value) => \App\Models\Item::trimNumber($value, 3).' '.$row['unit'])
                                <tr class="align-top">
                                    <td class="px-6 py-2.5 font-medium">{{ $row['name'] }}</td>
                                    <td class="num px-3 py-2.5 text-right text-ink-500">{{ $qty($row['expected']) }}</td>
                                    <td class="num px-3 py-2.5 text-right">{{ $qty($row['counted']) }}</td>
                                    <td class="px-3 py-2.5 text-xs text-ink-500">
                                        @if ($row['used'] > 0)
                                            <span class="block">{{ $qty($row['used']) }} used or missing beyond the recipes, at ₱{{ \App\Models\Item::formatUnitCost($row['unit_cost']) }} each.</span>
                                        @endif
                                        @if ($row['recipe_surplus'] > 0)
                                            <span class="block text-gain-700 dark:text-gain-400">Recipes took {{ $qty($row['recipe_surplus']) }} too much{{ $row['credit'] > 0 ? '; ₱'.number_format($row['credit'], 2).' given back' : '' }}.</span>
                                        @endif
                                        @if ($row['restocked'] > 0)
                                            <span class="block">{{ $qty($row['restocked']) }} more than expected: an unrecorded restock.</span>
                                        @endif
                                        @if ($row['used'] <= 0 && $row['recipe_surplus'] <= 0 && $row['restocked'] <= 0)
                                            <span class="block">Matches what sales left.</span>
                                        @endif
                                    </td>
                                    <td class="num px-6 py-2.5 text-right font-medium">{{ $row['cost'] < 0 ? '+' : '' }}₱{{ number_format(abs($row['cost']), 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="border-t border-ink-200 font-semibold dark:border-white/[0.07]">
                                <td class="px-6 py-3" colspan="4">Total</td>
                                <td class="num px-6 py-3 text-right">{{ $data['total'] < 0 ? '+' : '' }}₱{{ number_format(abs($data['total']), 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif
        </section>
    </div>
</x-app-layout>
