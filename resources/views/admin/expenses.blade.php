@use('App\Enums\ExpenseCategory')

@php
    $categoryColors = [
        ExpenseCategory::Utilities->value => 'bg-sky-400',
        ExpenseCategory::Rent->value => 'bg-violet-400',
        ExpenseCategory::Wages->value => 'bg-brand-400',
        ExpenseCategory::Supplies->value => 'bg-yellow-300',
        ExpenseCategory::StockPurchase->value => 'bg-rose-400',
        ExpenseCategory::Payables->value => 'bg-emerald-400',
        ExpenseCategory::Misc->value => 'bg-ink-400',
    ];
    $monthStart = $month->copy()->startOfMonth();
    $monthEnd = $month->copy()->endOfMonth()->min(today());
    $openTab = request('tab') === 'assets' || $errors->hasAny(['name', 'price', 'terms', 'installment_amount', 'first_due_on']) ? 'assets' : 'operating';
@endphp

<x-app-layout title="Expenses">
    <div x-data="{ tab: @js($openTab) }" class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-8">
        <x-page-header :eyebrow="$month->format('F Y')" title="Expenses"
            description="Log it the way you did in Excel: date, category, amount. It flows straight into your profit.">
            <x-slot:actions>
                <form method="GET" action="{{ route('admin.expenses') }}">
                    <select name="month" class="field w-auto py-2" aria-label="Month" onchange="this.form.requestSubmit ? this.form.requestSubmit() : this.form.submit()">
                        @foreach ($monthOptions as $option)
                            <option value="{{ $option['value'] }}" @selected($option['value'] === $month->format('Y-m'))>{{ $option['label'] }}</option>
                        @endforeach
                    </select>
                </form>
                <a href="{{ route('admin.exports.download', ['dataset' => 'expenses', 'from' => $monthStart->toDateString(), 'to' => $monthEnd->toDateString()]) }}" download class="btn-ghost"><x-icon name="download" class="size-4" /> Export CSV</a>
            </x-slot:actions>
        </x-page-header>

        <div class="inline-flex gap-1 rounded-xl bg-ink-100 p-1 dark:bg-white/[0.05]">
            <button type="button" @click="tab = 'operating'" :class="tab === 'operating' ? 'tab-active' : ''" class="tab">Daily expenses</button>
            <button type="button" @click="tab = 'assets'" :class="tab === 'assets' ? 'tab-active' : ''" class="tab">Equipment & payables</button>
        </div>

        <div x-show="tab === 'operating'" class="grid gap-6 lg:grid-cols-[1fr_300px]">
            <div class="space-y-4">
                {{-- Quick add: one spreadsheet row --}}
                <form method="POST" action="{{ route('expenses.store') }}" class="surface grid gap-3 p-4 sm:grid-cols-[140px_170px_1fr_140px_auto] sm:items-end">
                    @csrf
                    <div>
                        <label class="field-label" for="expense_date">Date</label>
                        <input id="expense_date" name="date" type="date" value="{{ old('date', today()->toDateString()) }}" max="{{ today()->toDateString() }}" required class="field num">
                    </div>
                    <div>
                        <label class="field-label" for="expense_category">Category</label>
                        <select id="expense_category" name="category" class="field">
                            @foreach ($categories as $category)
                                <option value="{{ $category->value }}" @selected(old('category') === $category->value)>{{ $category->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="field-label" for="expense_description">What for</label>
                        <input id="expense_description" name="description" type="text" value="{{ old('description') }}" maxlength="160" required class="field" placeholder="e.g. Ice, 3 sacks">
                    </div>
                    <div>
                        <label class="field-label" for="expense_amount">Amount</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-ink-400">₱</span>
                            <input id="expense_amount" name="amount" type="number" step="0.01" min="0.01" value="{{ old('amount') }}" required class="field num pl-7 text-right" placeholder="0.00">
                        </div>
                    </div>
                    <button type="submit" class="btn-primary" data-loading-text="Adding…"><x-icon name="plus" class="size-4" /> Add</button>
                </form>

                <div class="surface overflow-hidden">
                    @if ($expenses->isEmpty())
                        <div class="px-6 py-14 text-center">
                            <p class="font-medium">No expenses in {{ $month->format('F') }}</p>
                            <p class="mt-1 text-sm text-ink-500">Add rent, Meralco, wages, ice and supplies above. Ingredient costs are counted automatically.</p>
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[680px] text-sm">
                                <thead class="table-head">
                                    <tr class="border-b border-ink-200 dark:border-white/[0.07]">
                                        <th class="px-5 py-3 font-semibold">Date</th>
                                        <th class="px-3 py-3 font-semibold">Category</th>
                                        <th class="px-3 py-3 font-semibold">Description</th>
                                        <th class="px-3 py-3 font-semibold">Type</th>
                                        <th class="px-3 py-3 text-right font-semibold">Amount</th>
                                        <th class="px-3 py-3"><span class="sr-only">Actions</span></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-ink-100 dark:divide-white/[0.05]">
                                    @foreach ($expenses as $expense)
                                        <tr class="group hover:bg-ink-50 dark:hover:bg-white/[0.02]">
                                            <td class="num whitespace-nowrap px-5 py-3 text-ink-500">{{ $expense->date->format('D j M') }}</td>
                                            <td class="px-3 py-3">
                                                <span class="inline-flex items-center gap-2">
                                                    <span class="size-2 rounded-full {{ $categoryColors[$expense->category->value] }}"></span>{{ $expense->category->label() }}
                                                </span>
                                            </td>
                                            <td class="px-3 py-3">
                                                {{ $expense->description }}
                                                <span class="text-xs text-ink-400">· {{ $expense->logged_by }}</span>
                                            </td>
                                            <td class="px-3 py-3 text-xs text-ink-500">{{ $expense->kind->label() }}</td>
                                            <td class="num px-3 py-3 text-right font-medium">₱{{ number_format((float) $expense->amount, 2) }}</td>
                                            <td class="px-3 py-3 text-right">
                                                <form method="POST" action="{{ route('admin.expenses.destroy', $expense) }}" data-confirm-title="Delete this expense?" data-confirm="It disappears from this month's total and your P&amp;L." data-confirm-action="Delete" data-confirm-danger>
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn-quiet size-8 !px-0 opacity-0 transition group-hover:opacity-100 focus:opacity-100" aria-label="Delete expense" data-loading-text="">
                                                        <x-icon name="trash" class="size-4" />
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr class="border-t border-ink-200 dark:border-white/[0.07]">
                                        <td colspan="4" class="px-5 py-3 text-sm font-semibold">{{ $month->isSameMonth(today()) ? $month->format('F').' so far' : $month->format('F').' total' }}</td>
                                        <td class="num px-3 py-3 text-right text-base font-semibold">₱{{ number_format($monthTotal, 2) }}</td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            <aside class="surface h-fit p-5">
                <p class="eyebrow">Where it went</p>
                <p class="num mt-2 text-2xl font-semibold">₱{{ number_format($monthTotal, 2) }}</p>
                @if ($monthTotal > 0)
                    <div class="mt-4 flex h-2 overflow-hidden rounded-full">
                        @foreach ($byCategory as $slice)
                            <div class="{{ $categoryColors[$slice['category']->value] }}" style="width: {{ $slice['amount'] / $monthTotal * 100 }}%"></div>
                        @endforeach
                    </div>
                    <ul class="mt-5 space-y-3 text-sm">
                        @foreach ($byCategory as $slice)
                            <li class="flex items-center justify-between gap-3">
                                <span class="flex items-center gap-2"><span class="size-2 rounded-full {{ $categoryColors[$slice['category']->value] }}"></span>{{ $slice['category']->label() }}</span>
                                <span class="num text-ink-600 dark:text-ink-300">₱{{ number_format($slice['amount'], 2) }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
                <p class="mt-5 border-t border-ink-100 pt-4 text-xs text-ink-500 dark:border-white/[0.06]">Ingredient costs aren't logged here. They're counted automatically from sales and the closing audit.</p>
            </aside>
        </div>

        <div x-show="tab === 'assets'" x-cloak class="space-y-4">
            <div class="grid gap-4 sm:grid-cols-3">
                <div class="surface p-5">
                    <p class="text-xs text-ink-500">Equipment value</p>
                    <p class="num mt-1 text-xl font-semibold">₱{{ number_format($assets->sum(fn ($asset) => (float) $asset->price), 2) }}</p>
                </div>
                <div class="surface p-5">
                    <p class="text-xs text-ink-500">Payables due this month</p>
                    <p class="num mt-1 text-xl font-semibold">₱{{ number_format($payablesThisMonth, 2) }}</p>
                </div>
                <div class="surface p-5">
                    <p class="text-xs text-ink-500">Next due</p>
                    @if ($nextDueAsset)
                        <p class="mt-1 text-xl font-semibold">{{ $nextDueAsset->nextDueOn()->format('M j') }} <span class="text-sm font-normal text-ink-500">· {{ $nextDueAsset->name }}</span></p>
                    @else
                        <p class="mt-1 text-xl font-semibold text-ink-400">Nothing due</p>
                    @endif
                </div>
            </div>

            <div class="surface overflow-hidden">
                @if ($assets->isEmpty())
                    <div class="px-6 py-10 text-center text-sm text-ink-500">No equipment yet. Add your espresso machine, freezer or blenders below.</div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[760px] text-sm">
                            <thead class="table-head">
                                <tr class="border-b border-ink-200 dark:border-white/[0.07]">
                                    <th class="px-5 py-3 font-semibold">Equipment</th>
                                    <th class="px-3 py-3 text-right font-semibold">Price</th>
                                    <th class="px-3 py-3 text-right font-semibold">Monthly</th>
                                    <th class="px-3 py-3 font-semibold">Paid</th>
                                    <th class="px-3 py-3 font-semibold">Next due</th>
                                    <th class="px-5 py-3"><span class="sr-only">Actions</span></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-ink-100 dark:divide-white/[0.05]">
                                @foreach ($assets as $asset)
                                    @php($nextDue = $asset->nextDueOn())
                                    <tr class="hover:bg-ink-50 dark:hover:bg-white/[0.02]">
                                        <td class="px-5 py-3">
                                            <p class="font-medium">{{ $asset->name }}</p>
                                            <p class="text-xs text-ink-500">{{ $asset->vendor ?? 'No vendor' }}</p>
                                        </td>
                                        <td class="num px-3 py-3 text-right">₱{{ number_format((float) $asset->price, 2) }}</td>
                                        <td class="num px-3 py-3 text-right text-ink-500">{{ $asset->installment_amount ? '₱'.number_format((float) $asset->installment_amount, 2) : 'Cash' }}</td>
                                        <td class="px-3 py-3">
                                            <div class="flex items-center gap-3">
                                                <div class="flex flex-wrap gap-0.5">
                                                    @for ($installment = 1; $installment <= min($asset->terms, 24); $installment++)
                                                        <span @class(['h-3 w-1.5 rounded-sm', 'bg-gain-500' => $installment <= $asset->paid_count, 'bg-ink-200 dark:bg-white/10' => $installment > $asset->paid_count])></span>
                                                    @endfor
                                                </div>
                                                <span class="num text-xs text-ink-500">{{ $asset->paid_count }}/{{ $asset->terms }}</span>
                                            </div>
                                        </td>
                                        <td class="px-3 py-3">
                                            @if ($nextDue)
                                                <span @class(['pill', 'bg-loss-500/15 text-loss-700 dark:text-loss-300' => $nextDue->isPast(), 'bg-brand-400/15 text-brand-700 dark:text-brand-300' => ! $nextDue->isPast()])>{{ $nextDue->format('M j, Y') }}</span>
                                            @else
                                                <span class="pill bg-gain-500/15 text-gain-700 dark:text-gain-300">Fully paid</span>
                                            @endif
                                        </td>
                                        <td class="px-5 py-3">
                                            <div class="flex justify-end gap-1">
                                                @if ($nextDue)
                                                    <form method="POST" action="{{ route('admin.assets.pay', $asset) }}">
                                                        @csrf
                                                        <button type="submit" class="btn-ghost px-3 py-1.5 text-xs" data-loading-text="Saving…">Mark ₱{{ number_format($asset->paymentAmount(), 2) }} paid</button>
                                                    </form>
                                                @endif
                                                <form method="POST" action="{{ route('admin.assets.destroy', $asset) }}" data-confirm-title="Remove {{ $asset->name }}?" data-confirm="Payments already logged stay in your expenses." data-confirm-action="Remove" data-confirm-danger>
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn-quiet size-8 !px-0" aria-label="Remove {{ $asset->name }}" data-loading-text=""><x-icon name="trash" class="size-4" /></button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <form method="POST" action="{{ route('admin.assets.store') }}" x-data="{ terms: {{ (int) old('terms', 12) }} }" class="surface grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-4">
                @csrf
                <input type="hidden" name="tab" value="assets">
                <p class="font-semibold sm:col-span-2 lg:col-span-4">Add equipment</p>
                <div class="sm:col-span-2">
                    <label class="field-label" for="asset_name">Equipment</label>
                    <input id="asset_name" name="name" type="text" value="{{ old('name') }}" required maxlength="120" class="field" placeholder="e.g. Espresso machine">
                </div>
                <div class="sm:col-span-2">
                    <label class="field-label" for="asset_vendor">Bought from</label>
                    <input id="asset_vendor" name="vendor" type="text" value="{{ old('vendor') }}" maxlength="120" class="field" placeholder="e.g. Abenson, Home Credit">
                </div>
                <div>
                    <label class="field-label" for="asset_price">Full price</label>
                    <input id="asset_price" name="price" type="number" step="0.01" min="0.01" value="{{ old('price') }}" required class="field num" placeholder="0.00">
                </div>
                <div>
                    <label class="field-label" for="asset_terms">Months to pay</label>
                    <input id="asset_terms" name="terms" type="number" min="1" max="60" x-model.number="terms" required class="field num">
                    <p class="mt-1 text-xs text-ink-500">1 = paid in cash</p>
                </div>
                <div x-show="terms > 1">
                    <label class="field-label" for="asset_installment">Monthly amount</label>
                    <input id="asset_installment" name="installment_amount" type="number" step="0.01" min="0.01" value="{{ old('installment_amount') }}" :disabled="terms <= 1" class="field num" placeholder="0.00">
                </div>
                <div>
                    <label class="field-label" for="asset_first_due" x-text="terms > 1 ? 'First payment due' : 'Bought on'">First payment due</label>
                    <input id="asset_first_due" name="first_due_on" type="date" value="{{ old('first_due_on', today()->toDateString()) }}" required class="field num">
                </div>
                <div x-show="terms > 1">
                    <label class="field-label" for="asset_paid_count">Already paid (months)</label>
                    <input id="asset_paid_count" name="paid_count" type="number" min="0" value="{{ old('paid_count', 0) }}" :disabled="terms <= 1" class="field num">
                </div>
                <div class="flex items-end sm:col-span-2 lg:col-span-1 lg:col-start-4">
                    <button type="submit" class="btn-primary w-full" data-loading-text="Adding…"><x-icon name="plus" class="size-4" /> Add equipment</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
