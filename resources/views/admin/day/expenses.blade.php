<x-app-layout :title="'Expenses · '.$day->format('M j')">
    <div class="mx-auto max-w-6xl space-y-6 px-4 py-8 sm:px-8">
        @include('admin.day.header')

        <section class="surface overflow-hidden">
            <div class="flex flex-wrap items-start justify-between gap-3 px-6 pt-6">
                <div>
                    <h2 class="font-semibold">Expenses</h2>
                    <p class="text-xs text-ink-500">Operating expenses count against profit today. Restock costs are a cash outflow only — they count against profit once that stock is sold, through the Ingredients line.</p>
                </div>
                @if ($business->hasFeature('expenses'))
                    <a href="{{ route('admin.expenses') }}" class="btn-ghost py-2">Add or edit expenses</a>
                @endif
            </div>
            @if ($data['entries']->isEmpty())
                <p class="px-6 py-8 text-sm text-ink-500">No expenses on this day.</p>
            @else
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full min-w-[560px] text-sm">
                        <thead class="table-head">
                            <tr class="border-b border-ink-200 dark:border-white/[0.07]">
                                <th class="px-6 py-2 font-semibold">What</th>
                                <th class="px-3 py-2 font-semibold">Category</th>
                                <th class="px-3 py-2 font-semibold">Logged by</th>
                                <th class="px-6 py-2 text-right font-semibold">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ink-100 dark:divide-white/[0.05]">
                            @foreach ($data['entries'] as $expense)
                                @php($isRestock = $expense->category->value === 'stock_purchase')
                                <tr>
                                    <td class="px-6 py-2.5">{{ $expense->description ?: '—' }}</td>
                                    <td class="px-3 py-2.5">
                                        {{ $expense->category->label() }}
                                        @if ($isRestock)
                                            <span class="pill ml-1 bg-ink-100 text-ink-600 dark:bg-white/[0.06] dark:text-ink-300">restock costs</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2.5 text-ink-500">{{ $expense->logged_by }}</td>
                                    <td class="num px-6 py-2.5 text-right font-medium">₱{{ number_format((float) $expense->amount, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="text-sm">
                            <tr class="border-t border-ink-200 font-semibold dark:border-white/[0.07]">
                                <td class="px-6 py-3" colspan="3">Operating expenses</td>
                                <td class="num px-6 py-3 text-right">₱{{ number_format($data['total'], 2) }}</td>
                            </tr>
                            @if ($data['stockPurchases'] > 0)
                                <tr class="font-semibold text-ink-500">
                                    <td class="px-6 pb-3" colspan="3">Restock costs <span class="text-xs font-normal">(not counted in profit yet)</span></td>
                                    <td class="num px-6 pb-3 text-right">₱{{ number_format($data['stockPurchases'], 2) }}</td>
                                </tr>
                            @endif
                        </tfoot>
                    </table>
                </div>
            @endif
        </section>
    </div>
</x-app-layout>
