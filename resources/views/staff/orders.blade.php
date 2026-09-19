@use('App\Enums\OrderStatus')

<x-app-layout title="My orders">
    <div class="mx-auto max-w-4xl space-y-8 px-4 py-8 sm:px-8">
        <x-page-header
            :eyebrow="$shift['started'] ? 'Shift since '.$shift['started']->format('g:i A') : 'No sales yet today'"
            title="My orders today"
            description="Count your drawer against the cash total before you hand over.">
            @can('run-audit')
                <x-slot:actions>
                    <a href="{{ route('audit') }}" class="btn-primary"><x-icon name="audit" class="size-4" /> Start closing audit</a>
                </x-slot:actions>
            @endcan
        </x-page-header>

        <dl class="grid grid-cols-2 gap-px overflow-hidden rounded-2xl border border-ink-200 bg-ink-200 sm:grid-cols-4 dark:border-white/[0.07] dark:bg-white/[0.07]">
            <div class="bg-white p-4 dark:bg-ink-900">
                <dt class="text-xs text-ink-500">Orders</dt>
                <dd class="num mt-1 text-xl font-semibold">{{ $shift['orders'] }}</dd>
            </div>
            @foreach ($shift['totals'] as $total)
                <div class="bg-white p-4 dark:bg-ink-900">
                    <dt class="text-xs text-ink-500">{{ $total['label'] === 'Cash' ? 'Cash in drawer' : $total['label'] }}</dt>
                    <dd class="num mt-1 text-xl font-semibold">₱{{ number_format($total['amount'], 2) }}</dd>
                </div>
            @endforeach
        </dl>

        @if ($orders->isEmpty())
            <div class="surface px-6 py-14 text-center">
                <p class="font-medium">No orders yet today</p>
                <p class="mt-1 text-sm text-ink-500">Sales you ring up on the <a href="{{ route('pos') }}" class="font-medium text-brand-600 hover:underline dark:text-brand-300">register</a> show up here.</p>
            </div>
        @else
            <ul class="surface divide-y divide-ink-100 dark:divide-white/[0.06]">
                @foreach ($orders as $order)
                    <li class="flex flex-wrap items-center gap-x-4 gap-y-2 px-4 py-3.5 sm:px-5">
                        <span class="num w-14 shrink-0 text-sm font-semibold">#{{ $order->number }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm">{{ $order->lines->map(fn ($line) => $line->name.($line->variant_label !== 'Regular' ? ' '.$line->variant_label : '').($line->qty > 1 ? ' ×'.$line->qty : ''))->join(', ') }}</p>
                            <p class="text-xs text-ink-500">{{ $order->paid_at->format('g:i A') }} · {{ $order->payment_method->label() }}</p>
                        </div>
                        @if ($order->status !== OrderStatus::Paid)
                            <span @class(['pill', 'bg-brand-400/15 text-brand-700 dark:text-brand-300' => $order->status === OrderStatus::VoidRequested, 'bg-ink-200 text-ink-600 dark:bg-white/10 dark:text-ink-300' => $order->isVoided()])>{{ $order->status->label() }}</span>
                        @endif
                        <span @class(['num w-24 text-right text-sm font-semibold', 'text-ink-400 line-through' => $order->isVoided()])>₱{{ number_format((float) $order->subtotal, 2) }}</span>
                        <div class="flex items-center gap-1">
                            <a href="{{ route('pos.orders.receipt', $order) }}" target="_blank" class="btn-quiet size-9 !px-0" title="Reprint receipt" aria-label="Reprint receipt for order {{ $order->number }}">
                                <x-icon name="printer" class="size-4" />
                            </a>
                            @if ($order->status === OrderStatus::Paid)
                                <form method="POST" action="{{ route('pos.orders.void', $order) }}"
                                    onsubmit="return confirm('{{ $canVoid ? 'Void' : 'Ask the owner to void' }} order #{{ $order->number }}?')">
                                    @csrf
                                    <button type="submit" class="btn-quiet px-2 text-xs text-loss-600 dark:text-loss-400" data-loading-text="…">
                                        {{ $canVoid ? 'Void' : 'Request void' }}
                                    </button>
                                </form>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($canLogExpenses)
            <section class="surface p-5">
                <h2 class="font-semibold">Log a small expense</h2>
                <p class="text-xs text-ink-500">Ice, LPG, paper bags bought with cash from the drawer.</p>
                <form method="POST" action="{{ route('expenses.store') }}" class="mt-4 grid gap-3 sm:grid-cols-[150px_1fr_130px_auto] sm:items-end">
                    @csrf
                    <input type="hidden" name="date" value="{{ today()->toDateString() }}">
                    <div>
                        <label class="field-label" for="expense_category">Category</label>
                        <select id="expense_category" name="category" class="field">
                            @foreach ($expenseCategories as $category)
                                <option value="{{ $category->value }}" @selected(old('category', 'supplies') === $category->value)>{{ $category->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="field-label" for="expense_description">What for</label>
                        <input id="expense_description" name="description" type="text" value="{{ old('description') }}" required maxlength="160" class="field" placeholder="e.g. Ice, 3 sacks">
                    </div>
                    <div>
                        <label class="field-label" for="expense_amount">Amount</label>
                        <input id="expense_amount" name="amount" type="number" step="0.01" min="0.01" value="{{ old('amount') }}" required class="field num text-right" placeholder="0.00">
                    </div>
                    <button type="submit" class="btn-primary" data-loading-text="Adding…"><x-icon name="plus" class="size-4" /> Add</button>
                </form>
            </section>
        @endif
    </div>
</x-app-layout>
