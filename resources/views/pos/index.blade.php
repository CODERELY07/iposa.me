@php
    // Category color → tile classes. Written out in full so Tailwind keeps them.
    $tones = [
        'brand' => ['tile' => 'border-l-brand-400 bg-brand-400/[0.08] hover:bg-brand-400/[0.16]', 'dot' => 'bg-brand-400'],
        'rose' => ['tile' => 'border-l-rose-400 bg-rose-400/[0.08] hover:bg-rose-400/[0.16]', 'dot' => 'bg-rose-400'],
        'yellow' => ['tile' => 'border-l-yellow-300 bg-yellow-300/[0.08] hover:bg-yellow-300/[0.16]', 'dot' => 'bg-yellow-300'],
        'sky' => ['tile' => 'border-l-sky-400 bg-sky-400/[0.08] hover:bg-sky-400/[0.16]', 'dot' => 'bg-sky-400'],
        'emerald' => ['tile' => 'border-l-emerald-400 bg-emerald-400/[0.08] hover:bg-emerald-400/[0.16]', 'dot' => 'bg-emerald-400'],
        'violet' => ['tile' => 'border-l-violet-400 bg-violet-400/[0.08] hover:bg-violet-400/[0.16]', 'dot' => 'bg-violet-400'],
        'ink' => ['tile' => 'border-l-ink-400 bg-ink-400/[0.08] hover:bg-ink-400/[0.16]', 'dot' => 'bg-ink-400'],
    ];

    $terminal = [
        'menu' => collect($menu)->map(fn (array $item) => [
            ...$item,
            'tone' => ($tones[$item['color']] ?? $tones['ink'])['dot'],
            'tile' => ($tones[$item['color']] ?? $tones['ink'])['tile'],
        ])->all(),
        'paymentMethods' => $paymentMethods,
        'nextOrderNumber' => $nextOrderNumber,
        'storeUrl' => route('pos.orders.store', absolute: false),
    ];
@endphp

<x-app-layout title="Register" focus>
    <div x-data="posTerminal(@js($terminal))"
        @keydown.window.slash="if (! ['INPUT', 'TEXTAREA'].includes($event.target.tagName)) { $event.preventDefault(); $refs.search.focus() }"
        class="lg:flex lg:h-dvh">

        {{-- Menu: search and categories stay put, only the tiles scroll.
             Below lg the height leaves room for the app's top bar (h-14), which
             full screen hides, so the section then takes the whole screen. --}}
        <section x-bind:data-fullscreen="$store.fullscreen.active ? 'true' : 'false'"
            class="flex h-[calc(100dvh-3.5rem)] min-w-0 flex-1 flex-col data-[fullscreen=true]:h-dvh lg:h-dvh">
            <div class="shrink-0 space-y-3 border-b border-ink-200 px-4 py-4 sm:px-6 dark:border-white/[0.06]">
                <div class="flex items-center gap-3">
                    <div class="relative flex-1">
                        <x-icon name="search" class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-ink-400" />
                        <input x-ref="search" x-model="search" type="search" placeholder="Search menu" class="field py-3 pl-10 pr-10" aria-label="Search menu">
                        <span class="kbd absolute right-3 top-1/2 hidden -translate-y-1/2 sm:inline-flex">/</span>
                    </div>
                    <button type="button" x-data x-show="$store.fullscreen.supported" x-cloak
                        @click="$store.fullscreen.toggle()"
                        :aria-label="$store.fullscreen.active ? 'Exit full screen' : 'Full screen'"
                        :title="$store.fullscreen.active ? 'Exit full screen' : 'Full screen'"
                        :aria-pressed="$store.fullscreen.active ? 'true' : 'false'"
                        class="btn-ghost h-12 shrink-0 gap-2 px-3 sm:px-4">
                        <x-icon name="expand" class="size-5" x-show="! $store.fullscreen.active" />
                        <x-icon name="collapse" class="size-5" x-show="$store.fullscreen.active" x-cloak />
                        <span class="hidden text-sm font-medium sm:inline" x-text="$store.fullscreen.active ? 'Exit full screen' : 'Full screen'">Full screen</span>
                    </button>

                    <div class="hidden text-right xl:block">
                        <p class="text-xs text-ink-500">{{ $cashierName }}</p>
                        <p class="num text-sm font-medium">Order <span x-text="'#' + orderNumber"></span></p>
                    </div>
                </div>

                <div class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 sm:-mx-6 sm:px-6">
                    <template x-for="name in categories" :key="name">
                        <button type="button" @click="category = name"
                            :class="category === name ? 'bg-ink-900 text-white dark:bg-white dark:text-ink-950' : 'bg-ink-100 text-ink-600 hover:bg-ink-200 dark:bg-white/[0.05] dark:text-ink-300 dark:hover:bg-white/10'"
                            class="shrink-0 rounded-full px-4 py-2 text-sm font-medium transition" x-text="name"></button>
                    </template>
                </div>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain p-4 pb-28 sm:p-6 lg:pb-6">
                @if (empty($menu))
                    <div class="mx-auto max-w-sm py-20 text-center">
                        <p class="font-semibold">No menu items yet</p>
                        <p class="mt-1 text-sm text-ink-500">
                            @if (auth()->user()->isAdmin())
                                Add your burgers, drinks and sides in <a href="{{ route('admin.inventory.create') }}" class="font-medium text-brand-600 hover:underline dark:text-brand-300">Inventory</a>, and they show up here as tiles.
                            @else
                                Ask the owner to add the menu in Inventory.
                            @endif
                        </p>
                    </div>
                @endif

                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5">
                    <template x-for="item in visibleItems" :key="item.id">
                        <div :class="[item.tile, flashItemId === item.id ? 'ring-2 ring-brand-400 animate-[tap-pop_.25s_ease-out]' : '']" class="flex min-h-[7.5rem] flex-col rounded-2xl border-l-4 transition">
                            {{-- Single-size items: whole tile is the button. --}}
                            <template x-if="item.variants.length === 1">
                                <button type="button" @click="add(item, item.variants[0])" class="flex flex-1 flex-col justify-between p-3.5 text-left active:scale-[0.98]">
                                    <span>
                                        <span class="block text-[15px] font-semibold leading-tight" x-text="item.name"></span>
                                        <span class="mt-0.5 block text-xs text-ink-500 dark:text-ink-400" x-text="item.variants[0].label"></span>
                                    </span>
                                    <span class="flex items-end justify-between gap-2">
                                        <span class="num text-base font-semibold" x-text="formatPeso(item.variants[0].price)"></span>
                                        <span x-show="item.stockLeft !== null" class="pill bg-loss-500/15 text-loss-600 dark:text-loss-300" x-text="item.stockLeft + ' left'"></span>
                                    </span>
                                </button>
                            </template>

                            {{-- Sized items: one tap per size, no pop-up. --}}
                            <template x-if="item.variants.length > 1">
                                <div class="flex flex-1 flex-col justify-between p-3.5">
                                    <span class="flex items-start justify-between gap-2">
                                        <span class="block text-[15px] font-semibold leading-tight" x-text="item.name"></span>
                                        <span x-show="item.stockLeft !== null" class="pill shrink-0 bg-loss-500/15 text-loss-600 dark:text-loss-300" x-text="item.stockLeft + ' left'"></span>
                                    </span>
                                    <div class="mt-3 grid grid-cols-2 gap-1.5">
                                        <template x-for="variant in item.variants" :key="variant.id">
                                            <button type="button" @click="add(item, variant)" class="rounded-xl bg-white/70 px-2 py-2 text-left ring-1 ring-ink-900/5 transition hover:bg-white active:scale-[0.97] dark:bg-ink-950/40 dark:ring-white/10 dark:hover:bg-ink-950/70">
                                                <span class="block text-[11px] font-medium text-ink-500 dark:text-ink-400" x-text="variant.label"></span>
                                                <span class="num block text-sm font-semibold" x-text="formatPeso(variant.price)"></span>
                                            </button>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>

                <p x-show="menu.length && ! visibleItems.length" x-cloak class="py-16 text-center text-sm text-ink-500">
                    Nothing matches "<span x-text="search"></span>".
                </p>
            </div>
        </section>

        {{-- "Added" confirmation after every tap --}}
        <div x-show="toast" x-cloak x-transition.opacity.duration.150ms role="status" aria-live="polite"
            class="pointer-events-none fixed inset-x-0 bottom-24 z-30 flex justify-center px-4 lg:bottom-6 lg:left-[76px] lg:right-[380px]">
            <div class="flex items-center gap-2 rounded-full bg-ink-900 px-4 py-2 text-sm font-medium text-white shadow-xl dark:bg-white dark:text-ink-950">
                <span class="flex size-5 items-center justify-center rounded-full bg-gain-500 text-white"><x-icon name="check" class="size-3" /></span>
                Added <span class="font-semibold" x-text="toast"></span>
            </div>
        </div>

        {{-- Mobile order bar --}}
        <div x-show="cart.length" x-cloak class="fixed inset-x-0 bottom-0 z-20 p-3 lg:hidden" style="padding-bottom: max(0.75rem, env(safe-area-inset-bottom))">
            <button type="button" @click="cartOpen = true" :class="flashItemId ? 'animate-[tap-pop_.25s_ease-out]' : ''" class="btn-primary w-full justify-between rounded-2xl py-4 text-base shadow-xl shadow-black/30">
                <span><span x-text="itemCount"></span> items · Review order</span>
                <span class="num" x-text="formatPeso(subtotal)"></span>
            </button>
        </div>
        <div x-show="cartOpen" x-cloak x-transition.opacity class="fixed inset-0 z-30 bg-ink-950/60 lg:hidden" @click="cartOpen = false"></div>

        {{-- Order panel: side column on desktop, bottom sheet on phones --}}
        <aside x-bind:data-open="cartOpen ? 'true' : 'false'" x-bind:aria-hidden="cartOpen ? 'false' : null"
            class="fixed inset-x-0 bottom-0 z-40 flex max-h-[88dvh] translate-y-full flex-col rounded-t-3xl border-t border-ink-200 bg-white transition-transform duration-200 data-[open=true]:translate-y-0 lg:static lg:z-auto lg:h-dvh lg:max-h-none lg:w-[380px] lg:translate-y-0 lg:rounded-none lg:border-l lg:border-t-0 dark:border-white/[0.07] dark:bg-ink-900">
            {{-- Offline sales waiting to sync / refused by the server --}}
            <div x-data x-show="$store.offlineQueue.total > 0 || $store.offlineQueue.notice" x-cloak class="border-b border-brand-400/30 bg-brand-400/10 px-5 py-3 text-xs">
                <div class="flex items-center justify-between gap-2">
                    <p class="font-medium text-brand-800 dark:text-brand-200">
                        <span x-show="$store.offlineQueue.pending > 0"><span class="num" x-text="$store.offlineQueue.pending"></span> offline sale(s) waiting to sync</span>
                        <span x-show="$store.offlineQueue.pending === 0 && $store.offlineQueue.failed.length > 0">Offline sales need your attention</span>
                    </p>
                    <button type="button" x-show="$store.offlineQueue.pending > 0" @click="$store.offlineQueue.flush()" :disabled="$store.offlineQueue.syncing || ! navigator.onLine"
                        class="font-semibold text-brand-700 hover:underline disabled:opacity-50 dark:text-brand-300"
                        x-text="$store.offlineQueue.syncing ? 'Syncing…' : 'Sync now'"></button>
                </div>
                <p x-show="$store.offlineQueue.notice" class="mt-1 text-ink-600 dark:text-ink-300" x-text="$store.offlineQueue.notice"></p>
                <template x-for="entry in $store.offlineQueue.failed" :key="entry.uuid">
                    <div class="mt-2 rounded-lg bg-white/70 p-2 dark:bg-ink-950/40">
                        <p class="text-ink-700 dark:text-ink-200"><span class="num" x-text="formatPeso(entry.summary.total)"></span> · <span x-text="entry.summary.lines.join(', ')"></span></p>
                        <p class="mt-0.5 text-loss-600 dark:text-loss-400" x-text="entry.error"></p>
                        <div class="mt-1 flex gap-3">
                            <button type="button" @click="$store.offlineQueue.retry(entry)" class="font-semibold hover:underline">Retry</button>
                            <button type="button" @click="$store.confirm.ask({ title: 'Discard this offline sale?', message: 'It was refused by the server and will never be recorded.', action: 'Discard', danger: true }).then((ok) => ok !== false && $store.offlineQueue.discard(entry.uuid))" class="font-semibold text-loss-600 hover:underline dark:text-loss-400">Discard</button>
                        </div>
                    </div>
                </template>
            </div>

            <div class="flex items-center justify-between px-5 py-4">
                <div>
                    <p class="eyebrow">Current order</p>
                    <p class="num mt-0.5 text-lg font-semibold" x-text="'#' + orderNumber"></p>
                </div>
                <div class="flex items-center gap-1">
                    <button type="button" x-show="cart.length" @click="clearCart()" class="btn-quiet text-xs">Clear</button>
                    <button type="button" @click="cartOpen = false" class="btn-quiet size-9 !px-0 lg:hidden" aria-label="Close order"><x-icon name="x" /></button>
                </div>
            </div>

            <div class="flex-1 overflow-y-auto border-y border-ink-100 px-5 dark:border-white/[0.06]">
                <template x-if="! cart.length">
                    <div class="flex h-full min-h-[12rem] flex-col items-center justify-center text-center">
                        <p class="text-sm font-medium">Tap an item to start</p>
                        <p class="mt-1 text-xs text-ink-500">Sizes are separate taps, e.g. Iced Tea 16oz or 22oz.</p>
                    </div>
                </template>

                <ul class="divide-y divide-ink-100 dark:divide-white/[0.06]">
                    <template x-for="line in cart" :key="line.key">
                        <li :class="flashLineKey === line.key ? 'bg-brand-400/15' : ''" class="-mx-2 flex items-center gap-3 rounded-xl px-2 py-3 transition-colors duration-300">
                            <span :class="line.tone" class="size-2 shrink-0 rounded-full"></span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium" x-text="line.name"></p>
                                <p class="text-xs text-ink-500"><span x-text="line.variant"></span> · <span class="num" x-text="formatPeso(line.price)"></span></p>
                            </div>
                            <div class="flex items-center rounded-xl bg-ink-100 dark:bg-white/[0.06]">
                                <button type="button" @click="decrement(line)" class="flex size-9 items-center justify-center" aria-label="Remove one"><x-icon name="minus" class="size-4" /></button>
                                <span class="num w-6 text-center text-sm font-semibold" x-text="line.qty"></span>
                                <button type="button" @click="increment(line)" class="flex size-9 items-center justify-center" aria-label="Add one"><x-icon name="plus" class="size-4" /></button>
                            </div>
                            <p class="num w-20 text-right text-sm font-semibold" x-text="formatPeso(line.qty * line.price)"></p>
                        </li>
                    </template>
                </ul>
            </div>

            <div class="space-y-4 p-5" style="padding-bottom: max(1.25rem, env(safe-area-inset-bottom))">
                <div class="flex items-end justify-between">
                    <span class="text-sm text-ink-500"><span x-text="itemCount"></span> items</span>
                    <span class="num text-3xl font-semibold tracking-tight" x-text="formatPeso(subtotal)"></span>
                </div>

                <div class="grid gap-1 rounded-xl bg-ink-100 p-1 dark:bg-white/[0.05]" style="grid-template-columns: repeat({{ max(1, count($paymentMethods)) }}, minmax(0, 1fr))">
                    <template x-for="method in paymentMethods" :key="method.value">
                        <button type="button" @click="payment = method.value" :class="payment === method.value ? 'tab-active' : ''" class="tab" x-text="method.label"></button>
                    </template>
                </div>

                <button type="button" @click="openCheckout()" :disabled="! cart.length" class="btn-primary w-full rounded-2xl py-4 text-base">
                    Charge <span class="num" x-text="formatPeso(subtotal)"></span>
                </button>
            </div>
        </aside>

        {{-- Checkout --}}
        <div x-show="checkoutOpen" x-cloak class="fixed inset-0 z-50 flex items-end justify-center bg-ink-950/70 p-0 sm:items-center sm:p-6" @keydown.escape.window="if (! processing) checkoutOpen = false">
            <div @click.outside="if (! completed && ! processing) checkoutOpen = false" class="w-full max-w-md rounded-t-3xl border border-ink-200 bg-white p-6 sm:rounded-3xl dark:border-white/10 dark:bg-ink-900">
                <template x-if="! completed">
                    <div class="space-y-5">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="eyebrow">Amount due · <span x-text="paymentLabel"></span></p>
                                <p class="num mt-1 text-4xl font-semibold" x-text="formatPeso(subtotal)"></p>
                            </div>
                            <button type="button" @click="checkoutOpen = false" :disabled="processing" class="btn-quiet size-9 !px-0" aria-label="Back to order"><x-icon name="x" /></button>
                        </div>

                        <template x-if="isCash">
                            <div class="space-y-3">
                                <label class="field-label" for="tendered">Cash received</label>
                                <input id="tendered" x-model="tendered" type="number" inputmode="decimal" min="0" step="0.01" class="field num py-3 text-xl" placeholder="0.00" x-init="$nextTick(() => $el.focus())" @keydown.enter="complete()">
                                <div class="grid grid-cols-4 gap-2">
                                    <button type="button" @click="quickCash(subtotal)" class="btn-ghost px-2">Exact</button>
                                    <template x-for="bill in [100, 500, 1000]" :key="bill">
                                        <button type="button" @click="quickCash(bill)" class="btn-ghost num px-2" x-text="'₱' + bill"></button>
                                    </template>
                                </div>
                                <div class="flex items-center justify-between rounded-2xl bg-gain-500/10 px-4 py-3">
                                    <span class="text-sm font-medium text-gain-700 dark:text-gain-300">Change</span>
                                    <span class="num text-2xl font-semibold text-gain-700 dark:text-gain-300" x-text="formatPeso(change)"></span>
                                </div>
                            </div>
                        </template>

                        <template x-if="! isCash">
                            <p class="rounded-2xl bg-sky-500/10 px-4 py-3 text-sm text-sky-800 dark:text-sky-200">
                                Confirm the <span x-text="paymentLabel"></span> payment on the customer's phone, then complete the sale.
                            </p>
                        </template>

                        <p x-show="error" x-cloak role="alert" class="flex gap-2 rounded-2xl bg-loss-500/10 px-4 py-3 text-sm text-loss-700 dark:text-loss-300">
                            <x-icon name="alert" class="mt-0.5 size-4" /> <span x-text="error"></span>
                        </p>

                        <button type="button" @click="complete()" :disabled="! canComplete || processing" :aria-busy="processing.toString()"
                            :class="processing ? '!opacity-100' : ''" class="btn-primary w-full rounded-2xl py-4 text-base">
                            <template x-if="! processing"><span x-text="error ? 'Try again' : 'Complete sale'"></span></template>
                            <template x-if="processing">
                                <span class="inline-flex items-center gap-2"><x-spinner /> Processing sale, please wait…</span>
                            </template>
                        </button>
                        <p x-show="! canComplete && isCash" class="-mt-2 text-center text-xs text-ink-500">Enter the cash received to complete the sale.</p>
                    </div>
                </template>

                <template x-if="completed">
                    <div class="py-4 text-center">
                        <div :class="lastOrder.offline ? 'bg-brand-400/15 text-brand-600 dark:text-brand-300' : 'bg-gain-500/15 text-gain-600 dark:text-gain-300'"
                            class="mx-auto flex size-14 items-center justify-center rounded-full">
                            <x-icon name="check" class="size-7" />
                        </div>
                        <template x-if="! lastOrder.offline">
                            <p class="mt-4 text-lg font-semibold">Order <span class="num" x-text="'#' + lastOrder.number"></span> paid</p>
                        </template>
                        <template x-if="lastOrder.offline">
                            <p class="mt-4 text-lg font-semibold">Sale saved offline · <span class="num" x-text="formatPeso(lastOrder.total)"></span></p>
                        </template>
                        <p class="mt-1 text-sm text-ink-500" x-show="lastOrder.change !== null">Give change: <span class="num font-semibold text-ink-900 dark:text-white" x-text="formatPeso(lastOrder.change)"></span></p>
                        <p class="mt-1 text-xs text-ink-500" x-show="! lastOrder.offline">Stock for linked ingredients was deducted.</p>
                        <p class="mx-auto mt-2 max-w-xs text-xs text-brand-700 dark:text-brand-300" x-show="lastOrder.offline">No internet right now. It's stored on this device and syncs by itself when the connection is back. Receipt prints after it syncs.</p>
                        <div class="mt-6 grid grid-cols-2 gap-2">
                            <button type="button" @click="printReceipt()" :disabled="! lastOrder.receipt_url" class="btn-ghost"><x-icon name="printer" class="size-4" /> Receipt</button>
                            <button type="button" @click="newOrder()" x-init="$nextTick(() => $el.focus())" class="btn-primary">New order</button>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>
</x-app-layout>
