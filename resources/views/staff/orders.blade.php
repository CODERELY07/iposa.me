@use('App\Enums\OrderStatus')

<x-app-layout title="My orders">
    <div x-data="myOrders(@js(['expected' => $cashFloat['expected'], 'loadedAt' => $loadedAt, 'receipts' => $receipts]))" class="mx-auto max-w-4xl space-y-8 px-4 py-8 sm:px-8">
        <p x-show="! online" x-cloak role="status" class="rounded-2xl border border-brand-400/30 bg-brand-400/10 px-4 py-3 text-sm text-brand-800 dark:text-brand-200">
            You're offline. This list is as of {{ $loadedAt }}. Sales and expenses you save now are kept on this device and sync by themselves.
        </p>

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
                    <dt class="text-xs text-ink-500">{{ $total['label'] }}</dt>
                    <dd class="num mt-1 text-xl font-semibold">₱{{ number_format($total['amount'], 2) }}</dd>
                </div>
            @endforeach
        </dl>

        <div class="surface flex flex-wrap items-center justify-between gap-2 p-4">
            <p class="text-sm text-ink-500">Should be in the drawer <span class="text-xs">(whole shop, not just your sales)</span></p>
            <p class="num text-xl font-semibold" x-text="formatPeso(drawerNow)">₱{{ number_format($cashFloat['expected'], 2) }}</p>
        </div>
        <p x-show="hasWaiting" x-cloak class="-mt-6 text-xs text-ink-500">Includes the cash sales and expenses still waiting to sync.</p>

        {{-- Saved on this device, not on the server yet --}}
        <section x-data x-show="$store.offlineQueue.items.length" x-cloak class="surface overflow-hidden">
            <div class="border-b border-ink-100 px-4 py-3 sm:px-5 dark:border-white/[0.06]">
                <h2 class="text-sm font-semibold">Saved on this device</h2>
                <p class="text-xs text-ink-500">These sync by themselves when the internet is back. The real order number shows here afterwards.</p>
            </div>
            <ul class="divide-y divide-ink-100 dark:divide-white/[0.06]">
                <template x-for="entry in $store.offlineQueue.items" :key="entry.uuid">
                    <li class="flex flex-wrap items-center gap-x-4 gap-y-1 px-4 py-3 sm:px-5">
                        <span class="num w-14 shrink-0 text-sm font-semibold" x-text="entry.kind === 'expense' ? 'Expense' : '#' + entry.summary.ticket"></span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm" x-text="entry.summary.lines.join(', ')"></p>
                            <p class="text-xs text-ink-500">
                                <span x-text="new Date(entry.createdAt).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' })"></span>
                                <span x-show="entry.kind !== 'expense'"> · <span x-text="entry.payload.payment_method"></span></span>
                                · <span :class="entry.status === 'failed' ? 'text-loss-600 dark:text-loss-400' : ''" x-text="entry.status === 'failed' ? entry.error : 'Waiting to sync'"></span>
                            </p>
                        </div>
                        <span class="num w-24 text-right text-sm font-semibold" x-text="formatPeso(entry.summary.total)"></span>
                        <div class="flex items-center gap-1">
                            <button type="button" x-show="entry.receipt" @click="printEntry(entry)" class="btn-quiet size-9 !px-0" title="Print receipt" aria-label="Print receipt"><x-icon name="printer" class="size-4" /></button>
                            <button type="button" x-show="entry.status === 'failed'" @click="$store.offlineQueue.retry(entry)" class="btn-quiet px-2 text-xs">Retry</button>
                            <button type="button" x-show="entry.status === 'failed'" @click="$store.confirm.ask({ title: 'Discard this?', message: 'It was refused by the server and will never be recorded.', action: 'Discard', danger: true }).then((ok) => ok !== false && $store.offlineQueue.discard(entry.uuid))" class="btn-quiet px-2 text-xs text-loss-600 dark:text-loss-400">Discard</button>
                        </div>
                    </li>
                </template>
            </ul>
        </section>

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
                            <a href="{{ route('pos.orders.receipt', $order) }}" target="_blank" @click="if (! online) { $event.preventDefault(); printSaved({{ $order->id }}) }" class="btn-quiet size-9 !px-0" title="Reprint receipt" aria-label="Reprint receipt for order {{ $order->number }}">
                                <x-icon name="printer" class="size-4" />
                            </a>
                            @if ($order->status === OrderStatus::Paid)
                                <form method="POST" action="{{ route('pos.orders.void', $order) }}"
                                    data-confirm-title="{{ $canVoid ? 'Void' : 'Ask the owner to void' }} order #{{ $order->number }}?" data-confirm="{{ $canVoid ? 'The sale leaves your reports and the stock goes back.' : 'The owner sees the request on their Today screen.' }}" data-confirm-action="{{ $canVoid ? 'Void order' : 'Send request' }}" data-confirm-danger>
                                    @csrf
                                    <button type="submit" :disabled="! online" :title="online ? '' : 'Needs internet'" class="btn-quiet px-2 text-xs text-loss-600 disabled:opacity-40 dark:text-loss-400" data-loading-text="…">
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
                <form @submit.prevent="addExpense()" class="mt-4 grid gap-3 sm:grid-cols-[150px_1fr_130px_auto] sm:items-end">
                    <div>
                        <label class="field-label" for="expense_category">Category</label>
                        <select id="expense_category" x-model="form.category" class="field">
                            @foreach ($expenseCategories as $category)
                                <option value="{{ $category->value }}">{{ $category->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="field-label" for="expense_description">What for</label>
                        <input id="expense_description" x-model="form.description" type="text" required maxlength="160" class="field" placeholder="e.g. Ice, 3 sacks">
                    </div>
                    <div>
                        <label class="field-label" for="expense_amount">Amount</label>
                        <input id="expense_amount" x-model="form.amount" type="number" step="0.01" min="0.01" required class="field num text-right" placeholder="0.00">
                    </div>
                    <button type="submit" :disabled="saving" class="btn-primary"><x-icon name="plus" class="size-4" /> <span x-text="saving ? 'Adding…' : 'Add'">Add</span></button>
                </form>
                <p x-show="savedOffline" x-cloak class="mt-3 text-xs text-brand-700 dark:text-brand-300">Saved on this device. It syncs by itself when the internet is back.</p>
                <p x-show="error" x-cloak role="alert" class="mt-3 text-xs text-loss-600 dark:text-loss-400" x-text="error"></p>
            </section>
        @endif
    </div>
</x-app-layout>
