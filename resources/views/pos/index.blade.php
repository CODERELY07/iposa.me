@php
    // Static demo menu. Only items marked "Sellable on POS" ever reach this screen.
    $tones = [
        'Burgers' => ['tile' => 'border-l-brand-400 bg-brand-400/[0.08] hover:bg-brand-400/[0.16]', 'dot' => 'bg-brand-400'],
        'Rice meals' => ['tile' => 'border-l-rose-400 bg-rose-400/[0.08] hover:bg-rose-400/[0.16]', 'dot' => 'bg-rose-400'],
        'Sides' => ['tile' => 'border-l-yellow-300 bg-yellow-300/[0.08] hover:bg-yellow-300/[0.16]', 'dot' => 'bg-yellow-300'],
        'Drinks' => ['tile' => 'border-l-sky-400 bg-sky-400/[0.08] hover:bg-sky-400/[0.16]', 'dot' => 'bg-sky-400'],
        'Add-ons' => ['tile' => 'border-l-ink-400 bg-ink-400/[0.08] hover:bg-ink-400/[0.16]', 'dot' => 'bg-ink-400'],
    ];

    $menu = $menu ?? collect([
        ['id' => 1, 'name' => 'Classic Burger', 'category' => 'Burgers', 'variants' => [['label' => 'Regular', 'price' => 89]], 'stockLeft' => null],
        ['id' => 2, 'name' => 'Cheeseburger', 'category' => 'Burgers', 'variants' => [['label' => 'Regular', 'price' => 109]], 'stockLeft' => null],
        ['id' => 3, 'name' => 'Double Cheese', 'category' => 'Burgers', 'variants' => [['label' => 'Regular', 'price' => 159]], 'stockLeft' => null],
        ['id' => 4, 'name' => 'Bacon Burger', 'category' => 'Burgers', 'variants' => [['label' => 'Regular', 'price' => 149]], 'stockLeft' => 7],
        ['id' => 5, 'name' => 'Burger Steak', 'category' => 'Rice meals', 'variants' => [['label' => 'w/ rice', 'price' => 119]], 'stockLeft' => null],
        ['id' => 6, 'name' => 'Chicken & Rice', 'category' => 'Rice meals', 'variants' => [['label' => 'w/ rice', 'price' => 129]], 'stockLeft' => 4],
        ['id' => 7, 'name' => 'Tapsilog', 'category' => 'Rice meals', 'variants' => [['label' => 'w/ egg', 'price' => 139]], 'stockLeft' => null],
        ['id' => 8, 'name' => 'Fries', 'category' => 'Sides', 'variants' => [['label' => 'Reg', 'price' => 59], ['label' => 'Large', 'price' => 89]], 'stockLeft' => null],
        ['id' => 9, 'name' => 'Onion Rings', 'category' => 'Sides', 'variants' => [['label' => 'Regular', 'price' => 79]], 'stockLeft' => null],
        ['id' => 10, 'name' => 'Nuggets', 'category' => 'Sides', 'variants' => [['label' => '6 pc', 'price' => 99], ['label' => '10 pc', 'price' => 149]], 'stockLeft' => null],
        ['id' => 11, 'name' => 'Iced Tea', 'category' => 'Drinks', 'variants' => [['label' => '16oz', 'price' => 45], ['label' => '22oz', 'price' => 60]], 'stockLeft' => null],
        ['id' => 12, 'name' => 'Iced Coffee', 'category' => 'Drinks', 'variants' => [['label' => '16oz', 'price' => 79], ['label' => '22oz', 'price' => 99]], 'stockLeft' => null],
        ['id' => 13, 'name' => 'Calamansi Juice', 'category' => 'Drinks', 'variants' => [['label' => '16oz', 'price' => 49], ['label' => '22oz', 'price' => 65]], 'stockLeft' => null],
        ['id' => 14, 'name' => 'Bottled Water', 'category' => 'Drinks', 'variants' => [['label' => '500ml', 'price' => 25]], 'stockLeft' => null],
        ['id' => 15, 'name' => 'Extra Cheese', 'category' => 'Add-ons', 'variants' => [['label' => '1 slice', 'price' => 15]], 'stockLeft' => null],
        ['id' => 16, 'name' => 'Extra Patty', 'category' => 'Add-ons', 'variants' => [['label' => '1 pc', 'price' => 45]], 'stockLeft' => null],
        ['id' => 17, 'name' => 'Fried Egg', 'category' => 'Add-ons', 'variants' => [['label' => '1 pc', 'price' => 20]], 'stockLeft' => null],
    ])->map(fn (array $item) => [...$item, 'tone' => $tones[$item['category']]['dot'], 'tile' => $tones[$item['category']]['tile']])->all();
@endphp

<x-app-layout title="Register" focus>
    <div x-data="posTerminal(@js($menu))"
        @keydown.window.slash="if (! ['INPUT', 'TEXTAREA'].includes($event.target.tagName)) { $event.preventDefault(); $refs.search.focus() }"
        class="lg:flex lg:h-dvh">

        {{-- Menu --}}
        <section class="flex min-w-0 flex-1 flex-col lg:h-dvh">
            <div class="space-y-3 border-b border-ink-200 px-4 py-4 sm:px-6 dark:border-white/[0.06]">
                <div class="flex items-center gap-3">
                    <div class="relative flex-1">
                        <x-icon name="search" class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-ink-400" />
                        <input x-ref="search" x-model="search" type="search" placeholder="Search menu" class="field py-3 pl-10 pr-10">
                        <span class="kbd absolute right-3 top-1/2 hidden -translate-y-1/2 sm:inline-flex">/</span>
                    </div>
                    <div class="hidden text-right xl:block">
                        <p class="text-xs text-ink-500">Jessa · Counter 1</p>
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

            <div class="flex-1 overflow-y-auto p-4 pb-28 sm:p-6 lg:pb-6">
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5">
                    <template x-for="item in visibleItems" :key="item.id">
                        <div :class="item.tile" class="flex min-h-[7.5rem] flex-col rounded-2xl border-l-4 transition">
                            {{-- Single-variant items: whole tile is the button. --}}
                            <template x-if="item.variants.length === 1">
                                <button type="button" @click="add(item, item.variants[0])" class="flex flex-1 flex-col justify-between p-3.5 text-left active:scale-[0.98]">
                                    <span>
                                        <span class="block text-[15px] font-semibold leading-tight" x-text="item.name"></span>
                                        <span class="mt-0.5 block text-xs text-ink-500 dark:text-ink-400" x-text="item.variants[0].label"></span>
                                    </span>
                                    <span class="flex items-end justify-between gap-2">
                                        <span class="num text-base font-semibold" x-text="formatPeso(item.variants[0].price)"></span>
                                        <span x-show="item.stockLeft" class="pill bg-loss-500/15 text-loss-600 dark:text-loss-300" x-text="item.stockLeft + ' left'"></span>
                                    </span>
                                </button>
                            </template>

                            {{-- Sized items: one tap per size, no pop-up. --}}
                            <template x-if="item.variants.length > 1">
                                <div class="flex flex-1 flex-col justify-between p-3.5">
                                    <span class="block text-[15px] font-semibold leading-tight" x-text="item.name"></span>
                                    <div class="mt-3 grid grid-cols-2 gap-1.5">
                                        <template x-for="variant in item.variants" :key="variant.label">
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

                <p x-show="! visibleItems.length" x-cloak class="py-16 text-center text-sm text-ink-500">
                    Nothing matches "<span x-text="search"></span>". Menu items are added by the owner in Inventory.
                </p>
            </div>
        </section>

        {{-- Mobile order bar --}}
        <div x-show="cart.length" x-cloak class="fixed inset-x-0 bottom-0 z-20 p-3 lg:hidden" style="padding-bottom: max(0.75rem, env(safe-area-inset-bottom))">
            <button type="button" @click="cartOpen = true" class="btn-primary w-full justify-between rounded-2xl py-4 text-base shadow-xl shadow-black/30">
                <span><span x-text="itemCount"></span> items · Review order</span>
                <span class="num" x-text="formatPeso(subtotal)"></span>
            </button>
        </div>
        <div x-show="cartOpen" x-cloak x-transition.opacity class="fixed inset-0 z-30 bg-ink-950/60 lg:hidden" @click="cartOpen = false"></div>

        {{-- Order panel: side column on desktop, bottom sheet on phones --}}
        <aside :class="cartOpen ? 'translate-y-0' : 'translate-y-full'"
            class="fixed inset-x-0 bottom-0 z-40 flex max-h-[88dvh] translate-y-full flex-col rounded-t-3xl border-t border-ink-200 bg-white transition-transform duration-200 lg:static lg:z-auto lg:h-dvh lg:max-h-none lg:w-[380px] lg:translate-y-0 lg:rounded-none lg:border-l lg:border-t-0 dark:border-white/[0.07] dark:bg-ink-900">
            <div class="flex items-center justify-between px-5 py-4">
                <div>
                    <p class="eyebrow">Current order</p>
                    <p class="num mt-0.5 text-lg font-semibold" x-text="'#' + orderNumber"></p>
                </div>
                <div class="flex items-center gap-1">
                    <button type="button" x-show="cart.length" @click="cart = []" class="btn-quiet text-xs">Clear</button>
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
                        <li class="flex items-center gap-3 py-3">
                            <span :class="line.tone" class="size-2 shrink-0 rounded-full"></span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium" x-text="line.name"></p>
                                <p class="text-xs text-ink-500"><span x-text="line.variant"></span> · <span class="num" x-text="formatPeso(line.price)"></span></p>
                            </div>
                            <div class="flex items-center rounded-xl bg-ink-100 dark:bg-white/[0.06]">
                                <button type="button" @click="decrement(line)" class="flex size-9 items-center justify-center" aria-label="Remove one"><x-icon name="minus" class="size-4" /></button>
                                <span class="num w-6 text-center text-sm font-semibold" x-text="line.qty"></span>
                                <button type="button" @click="line.qty++" class="flex size-9 items-center justify-center" aria-label="Add one"><x-icon name="plus" class="size-4" /></button>
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

                <div class="grid grid-cols-3 gap-1 rounded-xl bg-ink-100 p-1 dark:bg-white/[0.05]">
                    <template x-for="method in ['Cash', 'GCash', 'Maya']" :key="method">
                        <button type="button" @click="payment = method" :class="payment === method ? 'tab-active' : ''" class="tab" x-text="method"></button>
                    </template>
                </div>

                <button type="button" @click="openCheckout()" :disabled="! cart.length" class="btn-primary w-full rounded-2xl py-4 text-base">
                    Charge <span class="num" x-text="formatPeso(subtotal)"></span>
                </button>
            </div>
        </aside>

        {{-- Checkout --}}
        <div x-show="checkoutOpen" x-cloak class="fixed inset-0 z-50 flex items-end justify-center bg-ink-950/70 p-0 sm:items-center sm:p-6" @keydown.escape.window="checkoutOpen = false">
            <div @click.outside="if (! completed) checkoutOpen = false" x-transition class="w-full max-w-md rounded-t-3xl border border-ink-200 bg-white p-6 sm:rounded-3xl dark:border-white/10 dark:bg-ink-900">
                <template x-if="! completed">
                    <div class="space-y-5">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="eyebrow">Amount due · <span x-text="payment"></span></p>
                                <p class="num mt-1 text-4xl font-semibold" x-text="formatPeso(subtotal)"></p>
                            </div>
                            <button type="button" @click="checkoutOpen = false" class="btn-quiet size-9 !px-0" aria-label="Back to order"><x-icon name="x" /></button>
                        </div>

                        <template x-if="payment === 'Cash'">
                            <div class="space-y-3">
                                <label class="field-label" for="tendered">Cash received</label>
                                <input id="tendered" x-model="tendered" type="number" inputmode="decimal" min="0" step="0.01" class="field num py-3 text-xl" placeholder="0.00" x-init="$nextTick(() => $el.focus())">
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

                        <template x-if="payment !== 'Cash'">
                            <p class="rounded-2xl bg-sky-500/10 px-4 py-3 text-sm text-sky-800 dark:text-sky-200">
                                Confirm the <span x-text="payment"></span> payment on the customer's phone, then complete the sale.
                            </p>
                        </template>

                        <button type="button" @click="complete()" :disabled="! canComplete" class="btn-primary w-full rounded-2xl py-4 text-base">
                            Complete sale
                        </button>
                    </div>
                </template>

                <template x-if="completed">
                    <div class="py-4 text-center">
                        <div class="mx-auto flex size-14 items-center justify-center rounded-full bg-gain-500/15 text-gain-600 dark:text-gain-300">
                            <x-icon name="check" class="size-7" />
                        </div>
                        <p class="mt-4 text-lg font-semibold">Order <span class="num" x-text="'#' + orderNumber"></span> paid</p>
                        <p class="mt-1 text-sm text-ink-500" x-show="payment === 'Cash'">Give change: <span class="num font-semibold text-ink-900 dark:text-white" x-text="formatPeso(change)"></span></p>
                        <p class="mt-1 text-xs text-ink-500">Stock for linked ingredients was deducted.</p>
                        <div class="mt-6 grid grid-cols-2 gap-2">
                            <button type="button" class="btn-ghost"><x-icon name="printer" class="size-4" /> Receipt</button>
                            <button type="button" @click="newOrder()" class="btn-primary">New order</button>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>
</x-app-layout>
