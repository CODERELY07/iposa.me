@php
    $isEditing = request()->route('item') !== null;

    // Static demo record: Cheeseburger when editing, blank when creating.
    $product = $product ?? ($isEditing
        ? ['name' => 'Cheeseburger', 'category' => 'Burgers', 'sellable' => true, 'countedAs' => 'pieces',
            'variants' => [['label' => 'Regular', 'cost' => 46, 'price' => 109]],
            'recipe' => [['piece' => 'Burger bun', 'qty' => 1], ['piece' => 'Beef patty', 'qty' => 1], ['piece' => 'Cheese slice', 'qty' => 1]]]
        : ['name' => '', 'category' => 'Burgers', 'sellable' => true, 'countedAs' => 'pieces',
            'variants' => [['label' => 'Regular', 'cost' => null, 'price' => null]], 'recipe' => []]);

    $categories = ['Burgers', 'Rice meals', 'Sides', 'Drinks', 'Add-ons'];
    $pieceCosts = ['Burger bun' => 7.5, 'Beef patty' => 24, 'Cheese slice' => 6, 'Chicken thigh' => 38, 'Egg' => 8.5, 'Cups 16oz' => 3.2, 'Cups 22oz' => 4.1, 'Bacon strip' => 11];
@endphp

<x-app-layout :title="$isEditing ? 'Edit item' : 'New item'">
    <form x-data="{
            name: @js($product['name']),
            sellable: @js($product['sellable']),
            countedAs: @js($product['countedAs']),
            variants: @js($product['variants']),
            recipe: @js($product['recipe']),
            pieceCosts: @js($pieceCosts),
            margin(variant) {
                return variant.price > 0 ? ((variant.price - (variant.cost || 0)) / variant.price) * 100 : null;
            },
            get recipeCost() {
                return this.recipe.reduce((total, line) => total + (this.pieceCosts[line.piece] || 0) * (line.qty || 0), 0);
            },
        }"
        @submit.prevent class="mx-auto max-w-6xl px-4 py-8 sm:px-8">

        <a href="{{ route('admin.inventory') }}" class="inline-flex items-center gap-1 text-sm text-ink-500 hover:text-ink-900 dark:hover:text-white">
            <x-icon name="chevron-right" class="size-4 rotate-180" /> Inventory
        </a>

        <x-page-header class="mt-3" :title="$isEditing ? $product['name'] : 'Add an item'">
            <x-slot:actions>
                @if ($isEditing)
                    <button type="button" class="btn-quiet text-loss-600 dark:text-loss-400"><x-icon name="trash" class="size-4" /> Archive</button>
                @endif
                <a href="{{ route('admin.inventory') }}" class="btn-ghost">Cancel</a>
                <button type="submit" class="btn-primary">Save item</button>
            </x-slot:actions>
        </x-page-header>

        <div class="mt-8 grid gap-6 lg:grid-cols-[1fr_320px]">
            <div class="space-y-6">
                {{-- Basics --}}
                <section class="surface space-y-5 p-6">
                    <div class="grid gap-5 sm:grid-cols-[1fr_200px]">
                        <div>
                            <label for="name" class="field-label">Name</label>
                            <input id="name" x-model="name" type="text" class="field" placeholder="e.g. Cheeseburger, Cooking oil">
                        </div>
                        <div>
                            <label for="category" class="field-label">Category</label>
                            <select id="category" class="field">
                                @foreach ($categories as $category)
                                    <option @selected($category === $product['category'])>{{ $category }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <fieldset>
                        <legend class="field-label">Sell this on the register?</legend>
                        <div class="grid gap-2 sm:grid-cols-2">
                            <button type="button" @click="sellable = true" :class="sellable ? 'border-brand-400 bg-brand-400/10' : 'border-ink-200 dark:border-white/10'" class="rounded-xl border p-4 text-left transition">
                                <p class="text-sm font-semibold">Yes, customers buy it</p>
                                <p class="mt-0.5 text-xs text-ink-500">Gets a tile on the register, with price and margin.</p>
                            </button>
                            <button type="button" @click="sellable = false" :class="! sellable ? 'border-brand-400 bg-brand-400/10' : 'border-ink-200 dark:border-white/10'" class="rounded-xl border p-4 text-left transition">
                                <p class="text-sm font-semibold">No, it's an ingredient or supply</p>
                                <p class="mt-0.5 text-xs text-ink-500">Buns, patties, oil, mayo. Tracked in the back only.</p>
                            </button>
                        </div>
                    </fieldset>

                    <fieldset x-show="! sellable" x-cloak>
                        <legend class="field-label">How do you count it?</legend>
                        <div class="grid gap-2 sm:grid-cols-2">
                            <label class="flex cursor-pointer gap-3 rounded-xl border border-ink-200 p-4 has-[:checked]:border-brand-400 dark:border-white/10">
                                <input type="radio" value="pieces" x-model="countedAs" class="mt-0.5 text-brand-500 focus:ring-brand-400">
                                <span><span class="block text-sm font-semibold">By the piece</span><span class="text-xs text-ink-500">Deducted automatically when a linked menu item sells.</span></span>
                            </label>
                            <label class="flex cursor-pointer gap-3 rounded-xl border border-ink-200 p-4 has-[:checked]:border-brand-400 dark:border-white/10">
                                <input type="radio" value="bulk" x-model="countedAs" class="mt-0.5 text-brand-500 focus:ring-brand-400">
                                <span><span class="block text-sm font-semibold">By eye, at closing</span><span class="text-xs text-ink-500">Bottles and tubs. Staff type 4.5 in the closing audit.</span></span>
                            </label>
                        </div>
                    </fieldset>
                </section>

                {{-- Sizes & pricing --}}
                <section x-show="sellable" class="surface p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="font-semibold">Sizes & pricing</h2>
                            <p class="text-xs text-ink-500">One row per size, same as your Excel matrix. Each size becomes its own tap on the register.</p>
                        </div>
                    </div>

                    <div class="mt-5 space-y-2">
                        <div class="hidden grid-cols-[1fr_120px_120px_96px_36px] gap-3 px-1 text-[11px] font-semibold uppercase tracking-wider text-ink-500 sm:grid">
                            <span>Size</span><span class="text-right">Cost</span><span class="text-right">Price</span><span class="text-right">Margin</span><span></span>
                        </div>
                        <template x-for="(variant, index) in variants" :key="index">
                            <div class="grid grid-cols-2 gap-3 rounded-xl bg-ink-50 p-3 sm:grid-cols-[1fr_120px_120px_96px_36px] sm:items-center sm:bg-transparent sm:p-1 dark:bg-white/[0.03] sm:dark:bg-transparent">
                                <input x-model="variant.label" type="text" class="field col-span-2 sm:col-span-1" placeholder="16oz, Large, Regular" aria-label="Size">
                                <div class="relative">
                                    <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-ink-400">₱</span>
                                    <input x-model.number="variant.cost" type="number" step="0.01" min="0" class="field num pl-7 text-right" placeholder="0.00" aria-label="Cost">
                                </div>
                                <div class="relative">
                                    <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-ink-400">₱</span>
                                    <input x-model.number="variant.price" type="number" step="0.01" min="0" class="field num pl-7 text-right" placeholder="0.00" aria-label="Selling price">
                                </div>
                                <p class="num text-right text-sm font-semibold"
                                    :class="margin(variant) === null ? 'text-ink-400' : (margin(variant) >= 50 ? 'text-gain-600 dark:text-gain-400' : (margin(variant) >= 25 ? 'text-brand-600 dark:text-brand-300' : 'text-loss-600 dark:text-loss-400'))"
                                    x-text="margin(variant) === null ? '—' : margin(variant).toFixed(1) + '%'"></p>
                                <button type="button" x-show="variants.length > 1" @click="variants.splice(index, 1)" class="btn-quiet size-9 justify-self-end !px-0" aria-label="Remove size"><x-icon name="x" class="size-4" /></button>
                            </div>
                        </template>
                    </div>
                    <button type="button" @click="variants.push({ label: '', cost: null, price: null })" class="btn-quiet mt-3 text-brand-600 dark:text-brand-300">
                        <x-icon name="plus" class="size-4" /> Add a size
                    </button>
                </section>

                {{-- Ingredient link --}}
                <section x-show="sellable" class="surface p-6">
                    <h2 class="font-semibold">What one sale uses <span class="text-xs font-normal text-ink-500">(optional)</span></h2>
                    <p class="text-xs text-ink-500">Each sale deducts these pieces from stock. Leave empty for items you count as themselves, like bottled water.</p>

                    <div class="mt-5 space-y-2">
                        <template x-for="(line, index) in recipe" :key="index">
                            <div class="flex items-center gap-2">
                                <input x-model.number="line.qty" type="number" min="0" step="0.5" class="field num w-20 text-center" aria-label="Quantity">
                                <span class="text-sm text-ink-400">×</span>
                                <select x-model="line.piece" class="field flex-1" aria-label="Piece">
                                    @foreach (array_keys($pieceCosts) as $piece)
                                        <option>{{ $piece }}</option>
                                    @endforeach
                                </select>
                                <span class="num hidden w-20 text-right text-sm text-ink-500 sm:block" x-text="formatPeso((pieceCosts[line.piece] || 0) * (line.qty || 0))"></span>
                                <button type="button" @click="recipe.splice(index, 1)" class="btn-quiet size-9 !px-0" aria-label="Remove ingredient"><x-icon name="x" class="size-4" /></button>
                            </div>
                        </template>
                    </div>

                    <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                        <button type="button" @click="recipe.push({ piece: 'Burger bun', qty: 1 })" class="btn-quiet text-brand-600 dark:text-brand-300">
                            <x-icon name="plus" class="size-4" /> Link a piece
                        </button>
                        <p x-show="recipe.length" class="text-xs text-ink-500">
                            Pieces cost <span class="num font-semibold text-ink-900 dark:text-white" x-text="formatPeso(recipeCost)"></span> per sale.
                            <button type="button" @click="variants[0].cost = Math.round(recipeCost * 100) / 100" class="font-semibold text-brand-600 hover:underline dark:text-brand-300">Use as cost</button>
                        </p>
                    </div>
                </section>

                {{-- Stock --}}
                <section class="surface grid gap-5 p-6 sm:grid-cols-3">
                    <div>
                        <label class="field-label" for="on_hand">On hand</label>
                        <input id="on_hand" type="number" step="0.25" class="field num" placeholder="0">
                    </div>
                    <div>
                        <label class="field-label" for="threshold">Alert me below</label>
                        <input id="threshold" type="number" step="0.25" class="field num" placeholder="e.g. 40">
                    </div>
                    <div x-show="! sellable">
                        <label class="field-label" for="unit_cost">Cost per unit</label>
                        <input id="unit_cost" type="number" step="0.01" class="field num" placeholder="₱0.00">
                    </div>
                    <p x-show="sellable" class="self-end text-xs text-ink-500 sm:pb-3">Items with linked pieces don't need their own stock count.</p>
                </section>
            </div>

            {{-- Live preview --}}
            <aside class="space-y-4 lg:sticky lg:top-8 lg:self-start">
                <div class="surface p-5">
                    <p class="eyebrow">On the register</p>
                    <template x-if="sellable">
                        <div class="mt-4 rounded-2xl border-l-4 border-l-brand-400 bg-brand-400/[0.08] p-3.5">
                            <p class="text-[15px] font-semibold leading-tight" x-text="name || 'Item name'"></p>
                            <div class="mt-3 grid grid-cols-2 gap-1.5">
                                <template x-for="(variant, index) in variants" :key="index">
                                    <div class="rounded-xl bg-white/70 px-2 py-2 ring-1 ring-ink-900/5 dark:bg-ink-950/40 dark:ring-white/10">
                                        <span class="block text-[11px] text-ink-500" x-text="variant.label || 'Size'"></span>
                                        <span class="num block text-sm font-semibold" x-text="formatPeso(variant.price || 0)"></span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                    <template x-if="! sellable">
                        <p class="mt-3 text-sm text-ink-500">Hidden. Cashiers won't see this item.</p>
                    </template>
                </div>

                <div x-show="sellable" class="surface p-5 text-sm">
                    <p class="eyebrow">Every sale earns</p>
                    <ul class="mt-3 space-y-2">
                        <template x-for="(variant, index) in variants" :key="index">
                            <li class="flex justify-between">
                                <span class="text-ink-500" x-text="variant.label || 'Size'"></span>
                                <span class="num font-semibold" x-text="formatPeso((variant.price || 0) - (variant.cost || 0))"></span>
                            </li>
                        </template>
                    </ul>
                </div>
            </aside>
        </div>
    </form>
</x-app-layout>
