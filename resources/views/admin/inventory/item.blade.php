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
        'includeRecipeCost' => $recipesEnabled && $formState['includeRecipeCost'],
        'pieceCosts' => $pieceCosts,
        'pieceUnits' => $pieceUnits,
        'unit' => $formState['unit'],
        'containers' => $formState['containers'],
        'measures' => $measures,
        'useContainers' => $formState['containers'] !== [] || (! $isEditing && $formState['kind'] === 'bulk'),
        'wasLegacy' => false,
        'isExistingLegacy' => $isEditing && $item->kind?->value === 'bulk' && $item->containers->isEmpty(),
        'onHand' => old('on_hand', $item->on_hand !== null ? (float) $item->on_hand : ''),
        'lowThreshold' => old('low_threshold', $item->low_threshold !== null ? (float) $item->low_threshold : ''),
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
                fill: { full: 0, open: '0' },
                originalStock: null,
                init() {
                    this.originalStock = { onHand: this.onHand, lowThreshold: this.lowThreshold };
                    this.$watch('kind', () => this.containerModeChanged());
                    this.containerModeChanged();
                },
                get isMenu() { return this.kind === 'menu' },
                get containerMode() { return this.kind === 'bulk' && this.useContainers },
                get primary() { return this.containers[0] ?? null },
                get unitLabel() { return this.measures[this.unit] ?? this.unit },
                get costPreview() {
                    const container = this.containers.find((c) => parseFloat(c.price) > 0 && parseFloat(c.size) > 0);
                    return container ? { perUnit: parseFloat(container.price) / parseFloat(container.size), container } : null;
                },
                plural(label, count) {
                    if (Math.abs(count - 1) < 0.0001) return label;
                    return /(s|x|ch|sh)$/i.test(label) ? label + 'es' : label + 's';
                },
                trim(value) { return (Math.round(value * 100) / 100).toLocaleString(undefined, { maximumFractionDigits: 2 }) },
                formatUnitCost(value) { return '₱' + Number(value.toFixed(6)).toLocaleString(undefined, { maximumFractionDigits: 6 }) },
                inContainers(quantity) {
                    const size = parseFloat(this.primary?.size);
                    if (! size || quantity === '' || quantity === null || isNaN(parseFloat(quantity))) return '';
                    const count = parseFloat(quantity) / size;
                    return '= ' + this.trim(count) + ' ' + this.plural(this.primary.label || 'container', count);
                },
                applyFill() {
                    const size = parseFloat(this.primary?.size) || 0;
                    this.onHand = Math.round(((parseInt(this.fill.full) || 0) + parseFloat(this.fill.open)) * size * 1000) / 1000;
                },
                containerModeChanged() {
                    if (! this.containerMode) {
                        // Back to counting in its own unit: an existing item keeps the count it had.
                        if (this.wasLegacy) { this.onHand = this.originalStock.onHand; this.lowThreshold = this.originalStock.lowThreshold; }
                        this.wasLegacy = false;
                        return;
                    }
                    if (! this.measures[this.unit]) this.unit = 'ml';
                    if (! this.containers.length) this.containers.push({ id: null, label: 'bottle', size: '', price: '' });
                    // An existing item counted in bottles has to be re-entered in ml, never silently reused.
                    if (this.isExistingLegacy && ! this.wasLegacy) { this.wasLegacy = true; this.onHand = ''; this.lowThreshold = ''; }
                },
                get tone() {
                    const category = this.categories.find((c) => String(c.id) === String(this.categoryId));
                    return this.tones[category?.color ?? 'ink'];
                },
                costPerSale(index) {
                    const own = parseFloat(this.variants[index].cost) || 0;
                    return Math.round((own + (this.includeRecipeCost ? this.recipeCostFor(index) : 0)) * 100) / 100;
                },
                margin(index) {
                    const price = parseFloat(this.variants[index].price);
                    return price > 0 ? ((price - this.costPerSale(index)) / price) * 100 : null;
                },
                recipeCostFor(variantIndex) {
                    return this.recipe
                        .filter((line) => line.variant_index === null || line.variant_index === '' || String(line.variant_index) === String(variantIndex))
                        .reduce((total, line) => total + (this.pieceCosts[line.piece_item_id] || 0) * (parseFloat(line.qty) || 0), 0);
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
                                    <span><span class="block text-sm font-semibold">By eye, at closing</span><span class="text-xs text-ink-500">Oil, sauces, LPG. Staff count bottles and jugs at closing; recipes can use them too.</span></span>
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
                                        :class="margin(index) === null ? 'text-ink-400' : (margin(index) >= 50 ? 'text-gain-600 dark:text-gain-400' : (margin(index) >= 25 ? 'text-brand-600 dark:text-brand-300' : 'text-loss-600 dark:text-loss-400'))"
                                        x-text="margin(index) === null ? '—' : margin(index).toFixed(1) + '%'"></p>
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
                            <p class="text-xs text-ink-500">Each sale deducts these from stock: pieces (1 bun) and liquids (15 ml ketchup). For liquids, the closing audit then corrects the count to what is really left. Leave empty for items you count as themselves, like bottled water.</p>

                            @if ($pieces->isEmpty())
                                <p class="mt-4 rounded-xl bg-ink-100 p-3 text-sm text-ink-600 dark:bg-white/[0.05] dark:text-ink-300">
                                    Nothing to link yet. <a href="{{ route('admin.inventory.create', ['kind' => 'piece']) }}" class="font-medium text-brand-600 hover:underline dark:text-brand-300">Add buns, patties, cups or sauces</a> first, then link them here.
                                </p>
                            @else
                                <div class="mt-5 space-y-2">
                                    <template x-for="(line, index) in recipe" :key="index">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <input x-model="line.qty" :name="`recipe[${index}][qty]`" :disabled="! isMenu" type="number" min="0.001" step="any" class="field num w-20 text-center" aria-label="Quantity">
                                            <span class="w-8 text-sm text-ink-400" x-text="pieceUnits[line.piece_item_id] || '×'"></span>
                                            <select x-model="line.piece_item_id" :name="`recipe[${index}][piece_item_id]`" :disabled="! isMenu" class="field min-w-[10rem] flex-1" aria-label="Piece">
                                                @foreach ($pieces->groupBy(fn ($piece) => $piece->kind->value) as $kindKey => $group)
                                                    <optgroup label="{{ $kindKey === 'bulk' ? 'Liquids & bulk' : 'Pieces' }}">
                                                        @foreach ($group as $piece)
                                                            <option value="{{ $piece->id }}">{{ $piece->name }}{{ $piece->unit ? ' ('.$piece->unit.')' : '' }}</option>
                                                        @endforeach
                                                    </optgroup>
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

                                <button type="button" @click="recipe.push({ piece_item_id: {{ $pieces->first()->id }}, qty: 1, variant_index: null })" class="btn-quiet mt-3 text-brand-600 dark:text-brand-300">
                                    <x-icon name="plus" class="size-4" /> Link a piece or liquid
                                </button>

                                {{-- Added at every sale, never copied into the Cost box: it can't be counted twice and follows price changes. --}}
                                <div x-show="recipe.length" class="mt-4 rounded-xl bg-ink-100/70 p-4 dark:bg-white/[0.04]">
                                    <input type="hidden" name="include_recipe_cost" value="0" :disabled="! isMenu">
                                    <label class="flex cursor-pointer items-start gap-3">
                                        <input x-model="includeRecipeCost" type="checkbox" name="include_recipe_cost" value="1" :disabled="! isMenu" class="mt-0.5 size-4 rounded border-ink-300 text-brand-600 focus:ring-brand-500">
                                        <span>
                                            <span class="block text-sm font-semibold">Include linked pieces & liquids in cost</span>
                                            <span class="text-xs text-ink-500">Type only your own cost above. The app adds what the links cost at today's prices to every sale.</span>
                                        </span>
                                    </label>
                                    <p x-show="! includeRecipeCost" class="mt-3 flex gap-2 rounded-lg bg-loss-500/10 px-3 py-2 text-xs text-loss-700 dark:text-loss-300">
                                        <x-icon name="alert" class="mt-0.5 size-3.5 shrink-0" />
                                        <span>Sales will take these off the shelf but won't count what they cost. Tick the box, or make sure the cost you typed above already includes them.</span>
                                    </p>
                                    <ul class="mt-3 space-y-1 text-xs text-ink-500">
                                        <template x-for="(variant, index) in variants" :key="index">
                                            <li class="num">
                                                <span x-show="variants.length > 1" class="font-medium text-ink-700 dark:text-ink-200" x-text="(variant.label || 'Size ' + (index + 1)) + ': '"></span>
                                                <template x-if="includeRecipeCost">
                                                    <span>
                                                        Your cost <span x-text="formatPeso(parseFloat(variant.cost) || 0)"></span>
                                                        + Linked <span x-text="formatPeso(recipeCostFor(index))"></span>
                                                        = <span class="font-semibold text-ink-900 dark:text-white" x-text="formatPeso(costPerSale(index)) + ' per sale'"></span>
                                                    </span>
                                                </template>
                                                <template x-if="! includeRecipeCost">
                                                    <span>Linked items cost <span class="font-semibold text-ink-900 dark:text-white" x-text="formatPeso(recipeCostFor(index))"></span>, not counted in cost.</span>
                                                </template>
                                            </li>
                                        </template>
                                    </ul>
                                </div>
                            @endif
                        </section>
                    @else
                        <section x-show="isMenu" class="surface p-5 text-sm text-ink-500">
                            @if ($savedLinks->isNotEmpty())
                                <span class="block text-ink-700 dark:text-ink-200">Each sale still uses: {{ $savedLinks->map(fn ($line) => \App\Models\Item::trimNumber((float) $line->qty, 3).' '.($line->piece?->unit ?: 'pc').' '.($line->piece?->name ?? '?').($line->variant ? ' ('.$line->variant->label.')' : ''))->join(', ') }}.</span>
                                These links are kept and keep working. Changing them needs ingredient links on your plan.
                            @endif
                            Ingredient links (sell a burger, buns go down) are on the Negosyo plan.
                            <a href="{{ route('admin.settings') }}#billing" class="font-medium text-brand-600 hover:underline dark:text-brand-300">See plans</a>
                        </section>
                    @endif

                    {{-- Stock --}}
                    <section class="surface space-y-5 p-6">
                        {{-- Liquids and bulk: bought in containers, stored in exact ml or grams. --}}
                        <div x-show="kind === 'bulk'" x-cloak class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-ink-100/70 p-4 dark:bg-white/[0.04]">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold">Bought in bottles, jugs or tins?</p>
                                <p class="text-xs text-ink-500">Staff count containers (“2 full + ½”), and the app keeps the exact ml or grams so the pesos come out right.</p>
                            </div>
                            <label class="inline-flex shrink-0 cursor-pointer items-center gap-2 text-sm font-medium">
                                <input type="checkbox" x-model="useContainers" @change="containerModeChanged()" class="size-4 rounded border-ink-300 text-brand-500 focus:ring-brand-400 dark:border-white/20 dark:bg-white/[0.06]">
                                Yes, in containers
                            </label>
                        </div>

                        <template x-if="containerMode">
                            <div class="space-y-5">
                                <div class="grid gap-5 sm:grid-cols-2">
                                    <div>
                                        <label class="field-label" for="unit_measure">Measured in</label>
                                        <select id="unit_measure" name="unit" x-model="unit" class="field">
                                            @foreach ($measures as $value => $label)
                                                <option value="{{ $value }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div>
                                    <p class="field-label">How do you buy it?</p>
                                    <div class="space-y-2">
                                        <template x-for="(container, index) in containers" :key="index">
                                            <div class="flex flex-wrap items-center gap-2 rounded-xl border border-ink-200 p-3 dark:border-white/10">
                                                <input type="hidden" :name="`containers[${index}][id]`" :value="container.id ?? ''">
                                                <span class="text-sm text-ink-500">1</span>
                                                <input x-model="container.label" :name="`containers[${index}][label]`" type="text" maxlength="40" required class="field w-28" placeholder="bottle" aria-label="Container name">
                                                <span class="text-sm text-ink-500">holds</span>
                                                <input x-model="container.size" :name="`containers[${index}][size]`" type="number" min="0" step="any" required class="field num w-28 text-right" placeholder="1000" aria-label="How much one holds">
                                                <span class="text-sm text-ink-500" x-text="unitLabel"></span>
                                                <span class="text-sm text-ink-500">· you pay</span>
                                                <div class="relative">
                                                    <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-ink-400">₱</span>
                                                    <input x-model="container.price" :name="`containers[${index}][price]`" type="number" min="0" step="0.01" class="field num w-32 pl-7" placeholder="145.00" aria-label="Price per container">
                                                </div>
                                                <button type="button" x-show="containers.length > 1" @click="containers.splice(index, 1)" class="btn-quiet ms-auto size-9 !px-0" aria-label="Remove this size"><x-icon name="x" class="size-4" /></button>
                                            </div>
                                        </template>
                                    </div>
                                    <div class="mt-2 flex flex-wrap items-center justify-between gap-2">
                                        <button type="button" x-show="containers.length < 5" @click="containers.push({ id: null, label: '', size: '', price: '' })" class="btn-quiet text-brand-600 dark:text-brand-300">
                                            <x-icon name="plus" class="size-4" /> Add another size
                                        </button>
                                        <p x-show="costPreview" class="text-sm">
                                            = <span class="num font-semibold" x-text="costPreview ? formatUnitCost(costPreview.perUnit) : ''"></span> per <span x-text="unitLabel"></span>
                                            <span class="text-xs text-ink-500" x-text="costPreview ? `(₱${Number(costPreview.container.price).toLocaleString()} ÷ ${Number(costPreview.container.size).toLocaleString()} ${unitLabel})` : ''"></span>
                                        </p>
                                    </div>
                                    <p class="mt-1 text-xs text-ink-500">A second size (like an 18 L tin) counts as the same stock. The cost follows the latest price you enter or restock at.</p>
                                </div>

                                <p x-show="wasLegacy" class="rounded-xl bg-brand-400/10 px-4 py-3 text-sm text-brand-800 dark:text-brand-200">
                                    Your stock was counted in <span class="font-semibold">{{ $item->unit ?: 'its own unit' }}</span>. Enter what is on the shelf again, now in <span x-text="unitLabel"></span> — the helper below does the maths.
                                </p>
                            </div>
                        </template>

                        {{-- Pieces, and liquids counted in their own unit --}}
                        <template x-if="! isMenu && ! containerMode">
                            <div class="grid gap-5 sm:grid-cols-2">
                                <div>
                                    <label class="field-label" for="unit">Counted per</label>
                                    <input id="unit" name="unit" type="text" x-model="unit" maxlength="60" class="field" :placeholder="kind === 'bulk' ? 'e.g. 1L bottle, 5kg tub' : 'e.g. pc, box of 50'">
                                </div>
                                <div>
                                    <label class="field-label" for="unit_cost">Cost per unit</label>
                                    <div class="relative">
                                        <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-ink-400">₱</span>
                                        <input id="unit_cost" name="unit_cost" type="number" step="any" min="0" value="{{ old('unit_cost', $item->unit_cost !== null ? (float) $item->unit_cost : null) }}" class="field num pl-7" placeholder="0.00">
                                    </div>
                                </div>
                            </div>
                        </template>

                        <div class="grid gap-5 sm:grid-cols-2">
                            <div>
                                <label class="field-label" for="on_hand" x-text="isMenu ? 'Count this item itself (optional)' : 'On hand now'">On hand now</label>
                                <div class="relative">
                                    <input id="on_hand" name="on_hand" type="number" step="any" x-model="onHand" class="field num" :class="containerMode ? 'pr-12' : ''"
                                        :required="containerMode && wasLegacy" placeholder="{{ $item->kind?->value === 'menu' ? 'Leave empty if made to order' : '0' }}">
                                    <span x-show="containerMode" class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-sm text-ink-400" x-text="unitLabel"></span>
                                </div>
                                <p x-show="containerMode && inContainers(onHand)" class="num mt-1 text-xs font-medium text-ink-600 dark:text-ink-300" x-text="inContainers(onHand)"></p>
                                <div x-show="containerMode && primary && parseFloat(primary.size) > 0" class="mt-2 flex flex-wrap items-center gap-2 text-xs text-ink-500">
                                    <span>Fill from the shelf:</span>
                                    <input x-model="fill.full" type="number" min="0" step="1" class="field num h-9 w-16 py-1 text-center" aria-label="Full containers">
                                    <span x-text="'full ' + plural(primary?.label || 'container', 2) + ' +'"></span>
                                    <select x-model="fill.open" class="field h-9 w-32 py-1" aria-label="Open container">
                                        <option value="0">none open</option>
                                        <option value="0.25">¼ open</option>
                                        <option value="0.5">½ open</option>
                                        <option value="0.75">¾ open</option>
                                    </select>
                                    <button type="button" @click="applyFill()" class="btn-ghost h-9 px-3 py-1 text-xs">Use</button>
                                </div>
                                <p class="mt-1 text-xs text-ink-500" x-text="isMenu ? 'Only for ready-made items with no linked pieces, like bottled water.' : 'Changing this logs an adjustment in the stock history. Bought more? Use Restock instead.'"></p>
                            </div>
                            <div>
                                <label class="field-label" for="low_threshold">Alert me at or below</label>
                                <div class="relative">
                                    <input id="low_threshold" name="low_threshold" type="number" step="any" min="0" x-model="lowThreshold" class="field num" :class="containerMode ? 'pr-12' : ''" placeholder="Default from Settings">
                                    <span x-show="containerMode" class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-sm text-ink-400" x-text="unitLabel"></span>
                                </div>
                                <p x-show="containerMode && inContainers(lowThreshold)" class="num mt-1 text-xs text-ink-500" x-text="inContainers(lowThreshold)"></p>
                            </div>
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
                                    <span class="num font-semibold" x-text="formatPeso((parseFloat(variant.price) || 0) - costPerSale(index))"></span>
                                </li>
                            </template>
                        </ul>
                    </div>
                </aside>
            </div>
        </form>

        @if ($canRestock)
            @php
                $restockContainers = $item->containers->map(fn ($container) => [
                    'id' => $container->id,
                    'label' => $container->label,
                    'size' => (float) $container->size,
                    'price' => $container->price !== null ? (float) $container->price : null,
                ])->values();
                $restockState = [
                    'containers' => $restockContainers,
                    'containerId' => (string) old('container_id', $restockContainers->first()['id'] ?? ''),
                    'quantity' => old('quantity', 1),
                    'paid' => old('paid', ''),
                    'unit' => $item->unit ?: 'unit',
                    'onHand' => (float) ($item->on_hand ?? 0),
                ];
            @endphp
            <section id="restock" class="surface mt-8 scroll-mt-8 p-6"
                x-data="{
                    ...@js($restockState),
                    get container() { return this.containers.find((c) => String(c.id) === String(this.containerId)) ?? null },
                    get added() { const qty = parseFloat(this.quantity) || 0; return this.container ? qty * this.container.size : qty },
                    get perUnit() { const paid = parseFloat(this.paid); return paid > 0 && this.added > 0 ? paid / this.added : null },
                    suggestPrice() { if (this.container?.price && ! this.paid) this.paid = Math.round(this.container.price * (parseFloat(this.quantity) || 0) * 100) / 100 },
                    fmt(value) { return Number(value.toFixed(2)).toLocaleString() },
                }" x-init="suggestPrice()">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="font-semibold">Restock</h2>
                        <p class="text-xs text-ink-500">Bought more? Add it here instead of editing the count: it is logged as a restock, and the cost follows what you paid.</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('admin.inventory.restock', $item) }}" class="mt-5 space-y-4">
                    @csrf
                    <div class="flex flex-wrap items-end gap-3">
                        <div>
                            <label class="field-label" for="restock_quantity">How many?</label>
                            <input id="restock_quantity" name="quantity" type="number" min="0" step="any" required x-model="quantity" @input="paid = ''; suggestPrice()" class="field num w-24 text-center">
                        </div>
                        <template x-if="containers.length">
                            <div>
                                <label class="field-label" for="restock_container">Of</label>
                                <select id="restock_container" name="container_id" x-model="containerId" @change="paid = ''; suggestPrice()" class="field w-48">
                                    <template x-for="option in containers" :key="option.id">
                                        <option :value="option.id" x-text="`${option.label} (${Number(option.size).toLocaleString()} ${unit})`" :selected="String(option.id) === String(containerId)"></option>
                                    </template>
                                </select>
                            </div>
                        </template>
                        <template x-if="! containers.length">
                            <p class="pb-3 text-sm text-ink-500" x-text="unit"></p>
                        </template>
                        <div>
                            <label class="field-label" for="restock_paid">You paid (total)</label>
                            <div class="relative">
                                <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-ink-400">₱</span>
                                <input id="restock_paid" name="paid" type="number" min="0" step="0.01" x-model="paid" class="field num w-36 pl-7" placeholder="0.00">
                            </div>
                        </div>
                    </div>

                    <p class="rounded-xl bg-ink-100/70 px-4 py-3 text-sm dark:bg-white/[0.04]">
                        + <span class="num font-semibold" x-text="fmt(added)"></span> <span x-text="unit"></span>
                        → on hand <span class="num font-semibold" x-text="fmt(onHand + added)"></span> <span x-text="unit"></span>
                        <template x-if="perUnit !== null">
                            <span class="text-ink-500"> · this purchase costs <span class="num font-medium text-ink-900 dark:text-white" x-text="'₱' + Number(perUnit.toFixed(6)).toLocaleString(undefined, { maximumFractionDigits: 6 })"></span> per <span x-text="unit"></span></span>
                        </template>
                    </p>

                    @if ($expensesEnabled)
                        <label class="flex items-center gap-2 text-sm">
                            <input type="hidden" name="log_expense" value="0">
                            <input type="checkbox" name="log_expense" value="1" @checked(old('log_expense', true)) class="size-4 rounded border-ink-300 text-brand-500 focus:ring-brand-400 dark:border-white/20 dark:bg-white/[0.06]">
                            @if ($item->isCostedWhenUsed(auth()->user()->business))
                                Log what I paid as a <span class="font-medium">Stock purchase</span>
                            @else
                                Log what I paid as a <span class="font-medium">Supplies</span> expense
                            @endif
                        </label>
                        <p class="-mt-2 pl-6 text-xs text-ink-500">
                            @if ($item->isCostedWhenUsed(auth()->user()->business))
                                Stock purchases are listed in Expenses but don't lower profit: {{ $item->name }} is counted when it's used.
                            @else
                                {{ $item->name }} isn't linked to a sale or counted at closing, so what you pay for it lowers profit now.
                            @endif
                        </p>
                    @endif

                    <div class="flex justify-end">
                        <button type="submit" class="btn-primary" data-loading-text="Restocking…">Restock</button>
                    </div>
                </form>
            </section>
        @endif

        @if ($recipeChanges->isNotEmpty())
            <section class="surface mt-8 p-6">
                <h2 class="font-semibold">Link history</h2>
                <p class="text-xs text-ink-500">Who changed what one sale uses, and when. The last 10 changes.</p>
                <ul class="mt-4 divide-y divide-ink-100 dark:divide-white/[0.06]">
                    @foreach ($recipeChanges as $change)
                        <li class="flex flex-wrap items-start justify-between gap-3 py-3">
                            <div class="min-w-0">
                                <p class="text-sm">{{ implode(' · ', $change->summary()) ?: 'No change' }}</p>
                                <p class="text-xs text-ink-500">
                                    {{ $change->requested_by }} · {{ $change->created_at->format('M j, g:i A') }}
                                    · <span @class(['font-medium', 'text-brand-700 dark:text-brand-300' => $change->isPending(), 'text-loss-600 dark:text-loss-400' => $change->status === 'rejected'])>{{ $change->statusLabel() }}</span>
                                    @if ($change->decided_by_name && $change->status !== 'saved')
                                        by {{ $change->decided_by_name }}
                                    @endif
                                </p>
                            </div>
                            @if ($change->isPending())
                                <div class="flex gap-2">
                                    <form method="POST" action="{{ route('admin.recipe-changes.approve', $change) }}">
                                        @csrf
                                        <button type="submit" class="btn-ghost px-3 py-1.5 text-xs" data-loading-text="Saving…">Approve</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.recipe-changes.reject', $change) }}">
                                        @csrf
                                        <button type="submit" class="btn-quiet px-3 py-1.5 text-xs" data-loading-text="…">Reject</button>
                                    </form>
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($isEditing)
            <div class="mt-8 flex flex-wrap items-center justify-end gap-2 border-t border-ink-200 pt-6 dark:border-white/[0.06]">
                @if ($canDelete)
                    <form method="POST" action="{{ route('admin.inventory.destroy', $item) }}" class="me-auto"
                        data-confirm-title="Delete {{ $item->name }} for good?" data-confirm="It was never sold or counted, so nothing in your reports changes. This can't be undone." data-confirm-action="Delete for good" data-confirm-danger>
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn-quiet text-loss-600 dark:text-loss-400" data-loading-text="Deleting…"><x-icon name="trash" class="size-4" /> Delete for good</button>
                    </form>
                @else
                    <p class="me-auto max-w-sm text-xs text-ink-500">This item is part of your history (sold, counted or linked to a recipe), so it can only be archived.</p>
                @endif

                @if ($item->archived_at)
                    <form method="POST" action="{{ route('admin.inventory.restore', $item) }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="btn-ghost" data-loading-text="Restoring…">Restore item</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('admin.inventory.archive', $item) }}" data-confirm-title="Archive {{ $item->name }}?" data-confirm="It disappears from the register and your lists. Past sales keep it, and you can restore it any time." data-confirm-action="Archive">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="btn-quiet text-loss-600 dark:text-loss-400" data-loading-text="Archiving…"><x-icon name="trash" class="size-4" /> Archive item</button>
                    </form>
                @endif
            </div>
        @endif
    </div>
</x-app-layout>
