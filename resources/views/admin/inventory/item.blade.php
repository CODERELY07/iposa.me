@php
    $isEditing = $item->exists;
    $tones = [
        'brand' => 'border-l-brand-400 bg-brand-400/[0.08]',
        'rose' => 'border-l-rose-400 bg-rose-400/[0.08]',
        'yellow' => 'border-l-yellow-300 bg-yellow-300/[0.08]',
        'sky' => 'border-l-sky-400 bg-sky-400/[0.08]',
        'emerald' => 'border-l-emerald-400 bg-emerald-400/[0.08]',
        'violet' => 'border-l-violet-400 bg-violet-400/[0.08]',
        'ink' => 'border-l-ink-400 bg-ink-400/[0.08]',
    ];
    $editorState = [
        'kind' => $formState['kind'],
        'name' => $formState['name'],
        'categoryId' => (string) old('category_id', $item->category_id ?? ''),
        'categories' => $categories->map(fn ($category) => ['id' => $category->id, 'name' => $category->name, 'color' => $category->color])->values(),
        'variants' => array_values($formState['variants']),
        'recipe' => array_values($formState['recipe']),
        'pieceCosts' => $pieceCosts,
        'tones' => $tones,
        'categoryUrl' => route('admin.categories.store', absolute: false),
    ];
    $fieldError = fn (string $field) => $errors->first($field);
@endphp

<x-app-layout :title="$isEditing ? 'Edit '.$item->name : 'New item'">
    <div class="mx-auto max-w-6xl px-4 py-8 sm:px-8">
        <a href="{{ route('admin.inventory') }}" class="inline-flex items-center gap-1 text-sm text-ink-500 hover:text-ink-900 dark:hover:text-white">
            <x-icon name="chevron-right" class="size-4 rotate-180" /> Inventory
        </a>

        <form method="POST" action="{{ $isEditing ? route('admin.inventory.update', $item) : route('admin.inventory.store') }}"
            x-data="{
                ...@js($editorState),
                newCategory: '',
                addingCategory: false,
                categoryError: null,
                get isMenu() { return this.kind === 'menu' },
                get tone() {
                    const category = this.categories.find((c) => String(c.id) === String(this.categoryId));
                    return this.tones[category?.color ?? 'ink'];
                },
                margin(variant) {
                    const price = parseFloat(variant.price);
                    return price > 0 ? ((price - (parseFloat(variant.cost) || 0)) / price) * 100 : null;
                },
                recipeCostFor(variantIndex) {
                    return this.recipe
                        .filter((line) => line.variant_index === null || line.variant_index === '' || String(line.variant_index) === String(variantIndex))
                        .reduce((total, line) => total + (this.pieceCosts[line.piece_item_id] || 0) * (parseFloat(line.qty) || 0), 0);
                },
                useRecipeCosts() {
                    this.variants.forEach((variant, index) => { variant.cost = Math.round(this.recipeCostFor(index) * 100) / 100 });
                },
                async createCategory() {
                    if (! this.newCategory.trim()) return;
                    this.categoryError = null;
                    const result = await window.sendJson(this.categoryUrl, { name: this.newCategory.trim() });
                    if (! result.ok) { this.categoryError = window.errorMessage(result); return; }
                    this.categories.push(result.data.category);
                    this.categoryId = String(result.data.category.id);
                    this.newCategory = '';
                    this.addingCategory = false;
                },
            }">
            @csrf
            @if ($isEditing)
                @method('PUT')
            @endif
            <input type="hidden" name="kind" :value="kind">

            <x-page-header class="mt-3" :title="$isEditing ? $item->name : 'Add an item'">
                <x-slot:actions>
                    @if ($item->archived_at)
                        <span class="pill bg-ink-200 text-ink-600 dark:bg-white/10 dark:text-ink-300">Archived</span>
                    @endif
                    <a href="{{ route('admin.inventory') }}" class="btn-ghost">Cancel</a>
                    <button type="submit" class="btn-primary" data-loading-text="Saving…">Save item</button>
                </x-slot:actions>
            </x-page-header>

            @if ($errors->any())
                <div class="mt-6 rounded-2xl border border-loss-500/30 bg-loss-500/10 p-4 text-sm text-loss-700 dark:text-loss-300" role="alert">
                    <p class="font-semibold">Please fix these:</p>
                    <ul class="mt-1 list-inside list-disc">
                        @foreach (collect($errors->all())->unique() as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="mt-8 grid gap-6 lg:grid-cols-[1fr_320px]">
                <div class="space-y-6">
                    {{-- Basics --}}
                    <section class="surface space-y-5 p-6">
                        <div class="grid gap-5 sm:grid-cols-[1fr_220px]">
                            <div>
                                <label for="name" class="field-label">Name</label>
                                <input id="name" name="name" x-model="name" type="text" required maxlength="120" class="field" placeholder="e.g. Cheeseburger, Cooking oil">
                                @if ($fieldError('name'))<p class="mt-1 text-xs text-loss-600 dark:text-loss-400">{{ $fieldError('name') }}</p>@endif
                            </div>
                            <div>
                                <label for="category" class="field-label">Category</label>
                                <div x-show="! addingCategory" class="flex gap-2">
                                    <select id="category" name="category_id" x-model="categoryId" class="field">
                                        <option value="">No category</option>
                                        <template x-for="category in categories" :key="category.id">
                                            <option :value="String(category.id)" x-text="category.name" :selected="String(category.id) === categoryId"></option>
                                        </template>
                                    </select>
                                    <button type="button" @click="addingCategory = true; $nextTick(() => $refs.newCategory.focus())" class="btn-ghost shrink-0 !px-3" title="New category" aria-label="New category"><x-icon name="plus" class="size-4" /></button>
                                </div>
                                <div x-show="addingCategory" x-cloak class="flex gap-2">
                                    <input x-ref="newCategory" x-model="newCategory" type="text" maxlength="40" class="field" placeholder="New category" @keydown.enter.prevent="createCategory()" @keydown.escape="addingCategory = false">
                                    <button type="button" @click="createCategory()" class="btn-primary shrink-0 !px-3">Add</button>
                                </div>
                                <p x-show="categoryError" x-cloak class="mt-1 text-xs text-loss-600 dark:text-loss-400" x-text="categoryError"></p>
                            </div>
                        </div>

                        <fieldset>
                            <legend class="field-label">Sell this on the register?</legend>
                            <div class="grid gap-2 sm:grid-cols-2">
                                <button type="button" @click="kind = 'menu'" :class="isMenu ? 'border-brand-400 bg-brand-400/10' : 'border-ink-200 dark:border-white/10'" class="rounded-xl border p-4 text-left transition">
                                    <p class="text-sm font-semibold">Yes, customers buy it</p>
                                    <p class="mt-0.5 text-xs text-ink-500">Gets a tile on the register, with price and margin.</p>
                                </button>
                                <button type="button" @click="if (isMenu) kind = 'piece'" :class="! isMenu ? 'border-brand-400 bg-brand-400/10' : 'border-ink-200 dark:border-white/10'" class="rounded-xl border p-4 text-left transition">
                                    <p class="text-sm font-semibold">No, it's an ingredient or supply</p>
                                    <p class="mt-0.5 text-xs text-ink-500">Buns, patties, oil, mayo. Tracked in the back only.</p>
                                </button>
                            </div>
                        </fieldset>

                        <fieldset x-show="! isMenu" x-cloak>
                            <legend class="field-label">How do you count it?</legend>
                            <div class="grid gap-2 sm:grid-cols-2">
                                <label class="flex cursor-pointer gap-3 rounded-xl border border-ink-200 p-4 has-[:checked]:border-brand-400 dark:border-white/10">
                                    <input type="radio" value="piece" x-model="kind" class="mt-0.5 text-brand-500 focus:ring-brand-400">
                                    <span><span class="block text-sm font-semibold">By the piece</span><span class="text-xs text-ink-500">Deducted automatically when a linked menu item sells.</span></span>
                                </label>
                                <label class="flex cursor-pointer gap-3 rounded-xl border border-ink-200 p-4 has-[:checked]:border-brand-400 dark:border-white/10">
                                    <input type="radio" value="bulk" x-model="kind" class="mt-0.5 text-brand-500 focus:ring-brand-400">
                                    <span><span class="block text-sm font-semibold">By eye, at closing</span><span class="text-xs text-ink-500">Bottles and tubs. Staff type 4.5 in the closing audit.</span></span>
                                </label>
                            </div>
                        </fieldset>
                    </section>

                    {{-- Sizes & pricing --}}
                    <section x-show="isMenu" class="surface p-6">
                        <h2 class="font-semibold">Sizes & pricing</h2>
                        <p class="text-xs text-ink-500">One row per size, same as your Excel matrix. Each size becomes its own tap on the register.</p>

                        <div class="mt-5 space-y-2">
                            <div class="hidden grid-cols-[1fr_120px_120px_96px_36px] gap-3 px-1 text-[11px] font-semibold uppercase tracking-wider text-ink-500 sm:grid">
                                <span>Size</span><span class="text-right">Cost</span><span class="text-right">Price</span><span class="text-right">Margin</span><span></span>
                            </div>
                            <template x-for="(variant, index) in variants" :key="index">
                                <div class="grid grid-cols-2 gap-3 rounded-xl bg-ink-50 p-3 sm:grid-cols-[1fr_120px_120px_96px_36px] sm:items-center sm:bg-transparent sm:p-1 dark:bg-white/[0.03] sm:dark:bg-transparent">
                                    <input type="hidden" :name="`variants[${index}][id]`" :value="variant.id ?? ''" :disabled="! isMenu">
                                    <input x-model="variant.label" :name="`variants[${index}][label]`" :disabled="! isMenu" type="text" maxlength="40" class="field col-span-2 sm:col-span-1" placeholder="16oz, Large, Regular" aria-label="Size">
                                    <div class="relative">
                                        <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-ink-400">₱</span>
                                        <input x-model="variant.cost" :name="`variants[${index}][cost]`" :disabled="! isMenu" type="number" step="0.01" min="0" class="field num pl-7 text-right" placeholder="0.00" aria-label="Cost">
                                    </div>
                                    <div class="relative">
                                        <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-ink-400">₱</span>
                                        <input x-model="variant.price" :name="`variants[${index}][price]`" :disabled="! isMenu" type="number" step="0.01" min="0" class="field num pl-7 text-right" placeholder="0.00" aria-label="Selling price">
                                    </div>
                                    <p class="num text-right text-sm font-semibold"
                                        :class="margin(variant) === null ? 'text-ink-400' : (margin(variant) >= 50 ? 'text-gain-600 dark:text-gain-400' : (margin(variant) >= 25 ? 'text-brand-600 dark:text-brand-300' : 'text-loss-600 dark:text-loss-400'))"
                                        x-text="margin(variant) === null ? '—' : margin(variant).toFixed(1) + '%'"></p>
                                    <button type="button" x-show="variants.length > 1" @click="variants.splice(index, 1); recipe.forEach((line) => { if (line.variant_index === index) line.variant_index = null; else if (line.variant_index > index) line.variant_index-- })" class="btn-quiet size-9 justify-self-end !px-0" aria-label="Remove size"><x-icon name="x" class="size-4" /></button>
                                </div>
                            </template>
                        </div>
                        <button type="button" @click="variants.push({ id: null, label: '', cost: null, price: null })" class="btn-quiet mt-3 text-brand-600 dark:text-brand-300">
                            <x-icon name="plus" class="size-4" /> Add a size
                        </button>
                    </section>

                    {{-- Ingredient links --}}
                    @if ($recipesEnabled)
                        <section x-show="isMenu" class="surface p-6">
                            <h2 class="font-semibold">What one sale uses <span class="text-xs font-normal text-ink-500">(optional)</span></h2>
                            <p class="text-xs text-ink-500">Each sale deducts these pieces from stock. Leave empty for items you count as themselves, like bottled water.</p>

                            @if ($pieces->isEmpty())
                                <p class="mt-4 rounded-xl bg-ink-100 p-3 text-sm text-ink-600 dark:bg-white/[0.05] dark:text-ink-300">
                                    No pieces yet. <a href="{{ route('admin.inventory.create', ['kind' => 'piece']) }}" class="font-medium text-brand-600 hover:underline dark:text-brand-300">Add buns, patties or cups</a> first, then link them here.
                                </p>
                            @else
                                <div class="mt-5 space-y-2">
                                    <template x-for="(line, index) in recipe" :key="index">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <input x-model="line.qty" :name="`recipe[${index}][qty]`" :disabled="! isMenu" type="number" min="0.001" step="0.5" class="field num w-20 text-center" aria-label="Quantity">
                                            <span class="text-sm text-ink-400">×</span>
                                            <select x-model="line.piece_item_id" :name="`recipe[${index}][piece_item_id]`" :disabled="! isMenu" class="field min-w-[10rem] flex-1" aria-label="Piece">
                                                @foreach ($pieces as $piece)
                                                    <option value="{{ $piece->id }}">{{ $piece->name }}</option>
                                                @endforeach
                                            </select>
                                            <select x-show="variants.length > 1" x-model="line.variant_index" :name="`recipe[${index}][variant_index]`" :disabled="! isMenu || variants.length < 2" class="field w-36" aria-label="Which size">
                                                <option value="">All sizes</option>
                                                <template x-for="(variant, variantIndex) in variants" :key="variantIndex">
                                                    <option :value="variantIndex" x-text="'Only ' + (variant.label || 'size ' + (variantIndex + 1))" :selected="String(line.variant_index) === String(variantIndex)"></option>
                                                </template>
                                            </select>
                                            <span class="num hidden w-20 text-right text-sm text-ink-500 sm:block" x-text="formatPeso((pieceCosts[line.piece_item_id] || 0) * (parseFloat(line.qty) || 0))"></span>
                                            <button type="button" @click="recipe.splice(index, 1)" class="btn-quiet size-9 !px-0" aria-label="Remove ingredient"><x-icon name="x" class="size-4" /></button>
                                        </div>
                                    </template>
                                </div>

                                <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                                    <button type="button" @click="recipe.push({ piece_item_id: {{ $pieces->first()->id }}, qty: 1, variant_index: null })" class="btn-quiet text-brand-600 dark:text-brand-300">
                                        <x-icon name="plus" class="size-4" /> Link a piece
                                    </button>
                                    <p x-show="recipe.length" class="text-xs text-ink-500">
                                        Pieces cost per sale:
                                        <template x-for="(variant, index) in variants" :key="index">
                                            <span><span x-show="variants.length > 1" x-text="(variant.label || 'Size ' + (index + 1)) + ' '"></span><span class="num font-semibold text-ink-900 dark:text-white" x-text="formatPeso(recipeCostFor(index))"></span><span x-show="index < variants.length - 1"> · </span></span>
                                        </template>
                                        <button type="button" @click="useRecipeCosts()" class="ml-1 font-semibold text-brand-600 hover:underline dark:text-brand-300">Use as cost</button>
                                    </p>
                                </div>
                            @endif
                        </section>
                    @else
                        <section x-show="isMenu" class="surface p-5 text-sm text-ink-500">
                            Ingredient links (sell a burger, buns go down) are on the Negosyo plan.
                            <a href="{{ route('admin.settings') }}#billing" class="font-medium text-brand-600 hover:underline dark:text-brand-300">See plans</a>
                        </section>
                    @endif

                    {{-- Stock --}}
                    <section class="surface grid gap-5 p-6 sm:grid-cols-2">
                        <div x-show="! isMenu" x-cloak>
                            <label class="field-label" for="unit">Counted per</label>
                            <input id="unit" name="unit" type="text" value="{{ old('unit', $item->unit) }}" maxlength="60" class="field" :placeholder="kind === 'bulk' ? 'e.g. 1L bottle, 5kg tub' : 'e.g. pc, box of 50'">
                        </div>
                        <div x-show="! isMenu" x-cloak>
                            <label class="field-label" for="unit_cost">Cost per unit</label>
                            <div class="relative">
                                <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-ink-400">₱</span>
                                <input id="unit_cost" name="unit_cost" type="number" step="0.01" min="0" value="{{ old('unit_cost', $item->unit_cost) }}" class="field num pl-7" placeholder="0.00">
                            </div>
                        </div>
                        <div>
                            <label class="field-label" for="on_hand" x-text="isMenu ? 'Count this item itself (optional)' : 'On hand now'">On hand now</label>
                            <input id="on_hand" name="on_hand" type="number" step="0.25" value="{{ old('on_hand', $item->on_hand !== null ? (float) $item->on_hand : null) }}" class="field num" placeholder="{{ $item->kind?->value === 'menu' ? 'Leave empty if made to order' : '0' }}">
                            <p class="mt-1 text-xs text-ink-500" x-text="isMenu ? 'Only for ready-made items with no linked pieces, like bottled water.' : 'Changing this logs an adjustment in the stock history.'"></p>
                        </div>
                        <div>
                            <label class="field-label" for="low_threshold">Alert me at or below</label>
                            <input id="low_threshold" name="low_threshold" type="number" step="0.25" min="0" value="{{ old('low_threshold', $item->low_threshold !== null ? (float) $item->low_threshold : null) }}" class="field num" placeholder="Default from Settings">
                        </div>
                    </section>
                </div>

                {{-- Live preview --}}
                <aside class="space-y-4 lg:sticky lg:top-8 lg:self-start">
                    <div class="surface p-5">
                        <p class="eyebrow">On the register</p>
                        <template x-if="isMenu">
                            <div :class="tone" class="mt-4 rounded-2xl border-l-4 p-3.5">
                                <p class="text-[15px] font-semibold leading-tight" x-text="name || 'Item name'"></p>
                                <div class="mt-3 grid grid-cols-2 gap-1.5">
                                    <template x-for="(variant, index) in variants" :key="index">
                                        <div class="rounded-xl bg-white/70 px-2 py-2 ring-1 ring-ink-900/5 dark:bg-ink-950/40 dark:ring-white/10">
                                            <span class="block text-[11px] text-ink-500" x-text="variant.label || 'Size'"></span>
                                            <span class="num block text-sm font-semibold" x-text="formatPeso(parseFloat(variant.price) || 0)"></span>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>
                        <template x-if="! isMenu">
                            <p class="mt-3 text-sm text-ink-500">Hidden. Cashiers won't see this item.</p>
                        </template>
                    </div>

                    <div x-show="isMenu" class="surface p-5 text-sm">
                        <p class="eyebrow">Every sale earns</p>
                        <ul class="mt-3 space-y-2">
                            <template x-for="(variant, index) in variants" :key="index">
                                <li class="flex justify-between">
                                    <span class="text-ink-500" x-text="variant.label || 'Size'"></span>
                                    <span class="num font-semibold" x-text="formatPeso((parseFloat(variant.price) || 0) - (parseFloat(variant.cost) || 0))"></span>
                                </li>
                            </template>
                        </ul>
                    </div>
                </aside>
            </div>
        </form>

        @if ($isEditing)
            <div class="mt-8 flex justify-end border-t border-ink-200 pt-6 dark:border-white/[0.06]">
                @if ($item->archived_at)
                    <form method="POST" action="{{ route('admin.inventory.restore', $item) }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="btn-ghost" data-loading-text="Restoring…">Restore item</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('admin.inventory.archive', $item) }}" onsubmit="return confirm('Archive {{ e(addslashes($item->name)) }}? It disappears from the register and lists. Past sales keep it.')">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="btn-quiet text-loss-600 dark:text-loss-400" data-loading-text="Archiving…"><x-icon name="trash" class="size-4" /> Archive item</button>
                    </form>
                @endif
            </div>
        @endif
    </div>
</x-app-layout>
