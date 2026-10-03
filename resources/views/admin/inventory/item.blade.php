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
        'categoryQuery' => '',
        'categoryOpen' => false,
        'categories' => $categories->map(fn ($category) => ['id' => $category->id, 'name' => $category->name, 'color' => $category->color])->values(),
        'variants' => array_values($formState['variants']),
        'recipe' => array_values($formState['recipe']),
        'costingMethod' => $recipesEnabled ? $formState['costingMethod'] : 'manual_only',
        'pieceCosts' => $pieceCosts,
        'pieceUnits' => $pieceUnits,
        'pieceStock' => $pieceStock,
        'pieceUrls' => $pieceUrls,
        'unit' => $formState['unit'],
        'containers' => $formState['containers'],
        'measures' => $measures,
        'useContainers' => $formState['containers'] !== [] || (! $isEditing && $formState['kind'] === 'bulk'),
        'wasLegacy' => false,
        'isExistingLegacy' => $isEditing && $item->kind?->value === 'bulk' && $item->containers->isEmpty(),
        'onHand' => old('on_hand', $item->on_hand !== null ? (float) $item->on_hand : ''),
        'sharedOnHand' => $item->on_hand !== null ? (float) $item->on_hand : null,
        'lowThreshold' => old('low_threshold', $item->low_threshold !== null ? (float) $item->low_threshold : ''),
        'tones' => $tones,
        'categoryUrl' => route('admin.categories.store', absolute: false),
    ];
    $pieceOptions = $pieces->map(fn ($piece) => [
        'value' => $piece->id,
        'label' => $piece->name.($piece->unit ? ' ('.$piece->unit.')' : ''),
    ])->values();
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
                    this.recipe = this.groupRecipe(this.recipe);
                    this.variants = this.variants.map((variant) => this.withCostHelper(variant));
                    this.$watch('kind', () => this.containerModeChanged());
                    this.containerModeChanged();
                },
                // Server/old-input rows are one per size (variant_index: int|null). Group same piece+qty rows
                // into one editable line with the sizes it applies to, so the UI can offer checkboxes instead
                // of a whole duplicate row per size.
                groupRecipe(rows) {
                    const groups = [];
                    (rows || []).forEach((row) => {
                        const key = row.piece_item_id + '|' + row.qty + '|' + (row.order_type || '');
                        const isAll = row.variant_index === null || row.variant_index === '' || row.variant_index === undefined;
                        let group = groups.find((g) => g._key === key);
                        if (! group) {
                            group = { _key: key, piece_item_id: row.piece_item_id, qty: row.qty, order_type: row.order_type || '', variant_indexes: isAll ? null : [] };
                            groups.push(group);
                        }
                        if (isAll) {
                            group.variant_indexes = null;
                        } else if (group.variant_indexes !== null) {
                            group.variant_indexes.push(parseInt(row.variant_index));
                        }
                    });
                    groups.forEach((g) => {
                        if (g.variant_indexes && g.variant_indexes.length === this.variants.length) g.variant_indexes = null;
                    });
                    return groups.map(({ _key, ...rest }) => rest);
                },
                // Expands each editable line back into one row per selected size, matching what the backend expects.
                submissionRecipe() {
                    const rows = [];
                    this.recipe.forEach((line) => {
                        if (line.variant_indexes === null || this.variants.length < 2) {
                            rows.push({ piece_item_id: line.piece_item_id, qty: line.qty, variant_index: '', order_type: line.order_type || '' });
                        } else {
                            line.variant_indexes.forEach((variantIndex) => {
                                rows.push({ piece_item_id: line.piece_item_id, qty: line.qty, variant_index: variantIndex, order_type: line.order_type || '' });
                            });
                        }
                    });
                    return rows;
                },
                isRecipeSizeChecked(line, variantIndex) {
                    return line.variant_indexes === null || line.variant_indexes.includes(variantIndex);
                },
                toggleRecipeSize(line, variantIndex) {
                    const arr = line.variant_indexes === null ? this.variants.map((_, i) => i) : [...line.variant_indexes];
                    const pos = arr.indexOf(variantIndex);
                    if (pos === -1) {
                        arr.push(variantIndex);
                    } else if (arr.length > 1) {
                        arr.splice(pos, 1);
                    }
                    line.variant_indexes = arr.length === this.variants.length ? null : arr.sort((a, b) => a - b);
                },
                removeVariant(index) {
                    this.variants.splice(index, 1);
                    this.recipe.forEach((line) => {
                        if (line.variant_indexes === null) return;
                        line.variant_indexes = line.variant_indexes.filter((i) => i !== index).map((i) => (i > index ? i - 1 : i));
                        if (line.variant_indexes.length === 0 || line.variant_indexes.length === this.variants.length) {
                            line.variant_indexes = null;
                        }
                    });
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
                // Per-size helper state for the bought-how-many calculator. Never submitted, only fills the cost box.
                withCostHelper(variant) {
                    return { ...variant, on_hand: variant.on_hand ?? '', helperOpen: false, helperQty: null, helperPaid: null };
                },
                // A menu item that counts itself keeps one count per size once it has several sizes (or a size already counts).
                splitSizes: false,
                get countPerSize() {
                    return this.isMenu && (this.splitSizes || this.variants.some((variant) => variant.on_hand !== '' && variant.on_hand !== null) || (this.variants.length > 1 && this.sharedOnHand === null));
                },
                helperCost(variant) {
                    return variant.helperQty > 0 && variant.helperPaid >= 0 ? Math.round((variant.helperPaid / variant.helperQty) * 100) / 100 : null;
                },
                // null means not typed, not zero -- the cost box being blank must not read as a free item.
                manualCostFor(index) {
                    const raw = this.variants[index].cost;
                    return raw === '' || raw === null || raw === undefined || isNaN(parseFloat(raw)) ? null : parseFloat(raw);
                },
                // null means nothing linked to this size, not zero.
                linkedCostFor(variantIndex) {
                    const lines = this.recipe.filter((line) => this.isRecipeSizeChecked(line, variantIndex));
                    if (lines.length === 0) return null;
                    return lines.reduce((total, line) => total + (this.pieceCosts[line.piece_item_id] || 0) * (parseFloat(line.qty) || 0), 0);
                },
                costPerSale(index) {
                    const manual = this.manualCostFor(index);
                    const linked = this.linkedCostFor(index);
                    let cost = null;
                    if (this.costingMethod === 'manual_only') cost = manual;
                    else if (this.costingMethod === 'linked_only') cost = linked;
                    else cost = (manual !== null && linked !== null) ? manual + linked : null;
                    return cost === null ? null : Math.round(cost * 100) / 100;
                },
                margin(index) {
                    const price = parseFloat(this.variants[index].price);
                    const cost = this.costPerSale(index);
                    return (price > 0 && cost !== null) ? ((price - cost) / price) * 100 : null;
                },
                // What's actually on the shelf for a linked piece, so a wrong pick or an
                // empty shelf shows up while setting up the link, not after the first sale fails.
                stockLabel(pieceId) {
                    const unit = this.pieceUnits[pieceId] || 'unit';
                    const cost = this.pieceCosts[pieceId] || 0;
                    const costPart = cost > 0 ? ' · ' + this.formatUnitCost(cost) + '/' + unit : '';
                    const stock = this.pieceStock[pieceId];

                    if (! stock) return 'not counted' + costPart;
                    if (stock.onHand <= 0) return 'out of stock' + costPart;

                    return this.trim(stock.onHand) + ' ' + unit + ' on hand' + costPart;
                },
                isPieceLow(pieceId) {
                    return this.pieceStock[pieceId]?.low ?? false;
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
                                    <x-combo-select id="category" model="categoryId" query="categoryQuery" open="categoryOpen" name="category_id" placeholder="Search categories…" class="flex-1"
                                        options-expr="[{ value: '', label: 'No category' }].concat(categories.map(c => ({ value: String(c.id), label: c.name })))" />
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
                                        <p x-show="variant.costStale" class="mt-1 text-right text-[11px] text-ink-400" title="Not reviewed in over 90 days — still used as typed, just a nudge to double check it.">not reviewed in a while</p>
                                    </div>
                                    <div class="relative">
                                        <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-ink-400">₱</span>
                                        <input x-model="variant.price" :name="`variants[${index}][price]`" :disabled="! isMenu" type="number" step="0.01" min="0" class="field num pl-7 text-right" placeholder="0.00" aria-label="Selling price">
                                    </div>
                                    <p class="num text-right text-sm font-semibold"
                                        :class="margin(index) === null ? 'text-ink-400' : (margin(index) >= 50 ? 'text-gain-600 dark:text-gain-400' : (margin(index) >= 25 ? 'text-brand-600 dark:text-brand-300' : 'text-loss-600 dark:text-loss-400'))"
                                        x-text="margin(index) === null ? '—' : margin(index).toFixed(1) + '%'"></p>
                                    <button type="button" x-show="variants.length > 1" @click="removeVariant(index)" class="btn-quiet size-9 justify-self-end !px-0" aria-label="Remove size"><x-icon name="x" class="size-4" /></button>

                                    {{-- Bought in bulk? Same helper as pieces and liquids: it only fills the Cost box. --}}
                                    <div class="col-span-2 sm:col-span-5 sm:pl-1">
                                        <button type="button" @click="variant.helperOpen = ! variant.helperOpen" :disabled="! isMenu" class="text-xs font-medium text-brand-600 hover:underline dark:text-brand-300" x-text="variant.helperOpen ? 'Hide cost helper' : 'Bought in bulk? Work out the cost'"></button>
                                        <div x-show="variant.helperOpen" x-cloak class="mt-2 flex flex-wrap items-end gap-2">
                                            <div>
                                                <label class="text-xs text-ink-500" :for="`cost_helper_qty_${index}`">Bought how many?</label>
                                                <input :id="`cost_helper_qty_${index}`" type="number" step="any" min="0" x-model.number="variant.helperQty" :disabled="! isMenu" class="field num mt-0.5 w-24 px-2 py-1 text-sm" placeholder="qty">
                                            </div>
                                            <span class="pb-2 text-xs text-ink-400">for ₱</span>
                                            <div>
                                                <label class="text-xs text-ink-500" :for="`cost_helper_paid_${index}`">Total paid</label>
                                                <input :id="`cost_helper_paid_${index}`" type="number" step="any" min="0" x-model.number="variant.helperPaid" :disabled="! isMenu" class="field num mt-0.5 w-28 px-2 py-1 text-sm" placeholder="amount">
                                            </div>
                                            <button type="button" class="btn-quiet px-3 py-1.5 text-xs" :disabled="helperCost(variant) === null" @click="variant.cost = helperCost(variant)">Use this</button>
                                            <p x-show="helperCost(variant) !== null" class="w-full text-xs text-ink-500">
                                                = <span class="num font-medium text-ink-700 dark:text-ink-200" x-text="helperCost(variant) === null ? '' : formatPeso(helperCost(variant))"></span> each
                                                <span x-text="`(₱${variant.helperPaid} ÷ ${variant.helperQty})`"></span> — not saved, just fills the Cost box
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                        <button type="button" @click="variants.push(withCostHelper({ id: null, label: '', cost: null, price: null }))" class="btn-quiet mt-3 text-brand-600 dark:text-brand-300">
                            <x-icon name="plus" class="size-4" /> Add a size
                        </button>
                    </section>

                    {{-- Ingredient links --}}
                    @if ($recipesEnabled)
                        <section x-show="isMenu" class="surface p-6">
                            <h2 class="font-semibold">What one sale uses <span class="text-xs font-normal text-ink-500">(optional)</span></h2>
                            <p class="text-xs text-ink-500">Each sale deducts these from stock: pieces (1 bun) and liquids (15 ml ketchup). For liquids, the closing audit then corrects the count to what is really left. Leave empty for items you count as themselves, like bottled water. Mark a link dine-in or take-out only for things like wax paper vs. a plastic bag.</p>

                            @if ($pieces->isEmpty())
                                <p class="mt-4 rounded-xl bg-ink-100 p-3 text-sm text-ink-600 dark:bg-white/[0.05] dark:text-ink-300">
                                    Nothing to link yet. <a href="{{ route('admin.inventory.create', ['kind' => 'piece']) }}" class="font-medium text-brand-600 hover:underline dark:text-brand-300">Add buns, patties, cups or sauces</a> first, then link them here.
                                </p>
                            @else
                                <div class="mt-5 space-y-3">
                                    <template x-for="(line, index) in recipe" :key="index">
                                        <div class="rounded-xl border border-ink-100 p-3 dark:border-white/[0.06]" :class="variants.length > 1 ? 'bg-ink-50 dark:bg-white/[0.03]' : ''">
                                            {{-- What, and how much --}}
                                            <div class="flex items-center gap-2">
                                                <input x-model="line.qty" :disabled="! isMenu" type="number" min="0.001" step="any" class="field num w-16 shrink-0 text-center" aria-label="Quantity">
                                                <x-combo-select :options="$pieceOptions" model="line.piece_item_id" query="line._pieceQuery" open="line._pieceOpen"
                                                    placeholder="Search pieces & liquids…" disabled="! isMenu" aria-label="Piece" class="min-w-0 flex-1" />
                                                <button type="button" @click="recipe.splice(index, 1)" class="btn-quiet size-9 shrink-0 !px-0" aria-label="Remove ingredient"><x-icon name="x" class="size-4" /></button>
                                            </div>

                                            {{-- How it applies, and what it costs --}}
                                            <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1.5 pl-1">
                                                <span class="shrink-0 truncate text-xs text-ink-400" style="max-width: 8rem" :title="pieceUnits[line.piece_item_id]" x-text="'per ' + (pieceUnits[line.piece_item_id] || 'unit')"></span>
                                                <select x-model="line.order_type" :disabled="! isMenu" class="field h-9 w-40 shrink-0 py-1 text-sm" aria-label="For dine-in or take-out">
                                                    <option value="">Dine-in & take-out</option>
                                                    <option value="dine_in">Dine-in only</option>
                                                    <option value="take_out">Take-out only</option>
                                                </select>
                                                <span class="num ml-auto text-sm text-ink-500" x-text="formatPeso((pieceCosts[line.piece_item_id] || 0) * (parseFloat(line.qty) || 0))"></span>
                                                <a :href="pieceUrls[line.piece_item_id]" target="_blank" rel="noopener" class="btn-quiet size-8 shrink-0 !px-0" aria-label="Open this ingredient's own page" title="Edit this ingredient's stock, cost or unit"><x-icon name="arrow-up-right" class="size-3.5" /></a>
                                            </div>

                                            <p class="mt-2 pl-1 text-xs" :class="isPieceLow(line.piece_item_id) ? 'text-loss-600 dark:text-loss-400' : 'text-ink-400'" x-text="stockLabel(line.piece_item_id)"></p>

                                            <div x-show="variants.length > 1" class="mt-2 border-t border-ink-200/70 pt-2 dark:border-white/[0.06]">
                                                <p class="pl-1 text-xs font-medium text-ink-400">Sizes this applies to:</p>
                                                <div class="mt-1 grid grid-cols-2 gap-x-3 gap-y-1 pl-1 text-xs text-ink-600 sm:grid-cols-3 dark:text-ink-300">
                                                    <template x-for="(variant, variantIndex) in variants" :key="variantIndex">
                                                        <label class="flex cursor-pointer items-center gap-1.5">
                                                            <input type="checkbox" :disabled="! isMenu" :checked="isRecipeSizeChecked(line, variantIndex)" @change="toggleRecipeSize(line, variantIndex)" class="size-3.5 shrink-0 rounded border-ink-300 text-brand-600 focus:ring-brand-500">
                                                            <span class="truncate" x-text="variant.label || 'Size ' + (variantIndex + 1)"></span>
                                                        </label>
                                                    </template>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                </div>

                                {{-- The backend still expects one row per (piece, size); expand each editable line here. --}}
                                <template x-for="(row, rowIndex) in submissionRecipe()" :key="rowIndex">
                                    <span>
                                        <input type="hidden" :name="`recipe[${rowIndex}][piece_item_id]`" :value="row.piece_item_id" :disabled="! isMenu">
                                        <input type="hidden" :name="`recipe[${rowIndex}][qty]`" :value="row.qty" :disabled="! isMenu">
                                        <input type="hidden" :name="`recipe[${rowIndex}][variant_index]`" :value="row.variant_index" :disabled="! isMenu">
                                        <input type="hidden" :name="`recipe[${rowIndex}][order_type]`" :value="row.order_type" :disabled="! isMenu">
                                    </span>
                                </template>

                                <button type="button" @click="recipe.push({ piece_item_id: {{ $pieces->first()->id }}, qty: 1, variant_indexes: null, order_type: '' })" class="btn-quiet mt-3 text-brand-600 dark:text-brand-300">
                                    <x-icon name="plus" class="size-4" /> Link a piece or liquid
                                </button>

                                {{-- Stock always leaves the shelf either way; this only decides what counts toward cost. --}}
                                <div x-show="recipe.length" class="mt-4 rounded-xl bg-ink-100/70 p-4 dark:bg-white/[0.04]">
                                    <label class="field-label" for="costing-method-select">How is this size's cost worked out?</label>
                                    <select x-model="costingMethod" name="costing_method" id="costing-method-select" :disabled="! isMenu" class="field mt-1.5 max-w-sm">
                                        <option value="manual_only">Manual cost only</option>
                                        <option value="linked_only">Linked pieces & liquids only</option>
                                        <option value="manual_plus_linked">Manual cost + linked pieces & liquids</option>
                                    </select>
                                    <p x-show="costingMethod === 'linked_only'" class="mt-3 flex gap-2 rounded-lg bg-ink-200/60 px-3 py-2 text-xs text-ink-600 dark:bg-white/[0.06] dark:text-ink-300">
                                        <x-icon name="alert" class="mt-0.5 size-3.5 shrink-0" />
                                        <span>The cost you typed above is ignored for this size. Only the linked cost below counts.</span>
                                    </p>
                                    <ul class="mt-3 space-y-1 text-xs text-ink-500">
                                        <template x-for="(variant, index) in variants" :key="index">
                                            <li class="num">
                                                <span x-show="variants.length > 1" class="font-medium text-ink-700 dark:text-ink-200" x-text="(variant.label || 'Size ' + (index + 1)) + ': '"></span>
                                                <template x-if="costingMethod === 'manual_plus_linked'">
                                                    <span>
                                                        Your cost <span x-text="manualCostFor(index) === null ? 'not set' : formatPeso(manualCostFor(index))"></span>
                                                        + Linked <span x-text="linkedCostFor(index) === null ? 'nothing linked' : formatPeso(linkedCostFor(index))"></span>
                                                        = <span :class="costPerSale(index) === null ? 'font-semibold text-loss-600 dark:text-loss-400' : 'font-semibold text-ink-900 dark:text-white'" x-text="costPerSale(index) === null ? 'cost not configured' : formatPeso(costPerSale(index)) + ' per sale'"></span>
                                                    </span>
                                                </template>
                                                <template x-if="costingMethod === 'linked_only'">
                                                    <span>Linked cost <span :class="costPerSale(index) === null ? 'font-semibold text-loss-600 dark:text-loss-400' : 'font-semibold text-ink-900 dark:text-white'" x-text="costPerSale(index) === null ? 'not configured' : formatPeso(costPerSale(index)) + ' per sale'"></span></span>
                                                </template>
                                                <template x-if="costingMethod === 'manual_only'">
                                                    <span>Linked items cost <span x-text="linkedCostFor(index) === null ? 'nothing' : formatPeso(linkedCostFor(index))"></span>, not counted — your typed cost is used instead.</span>
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
                                <span class="block text-ink-700 dark:text-ink-200">Each sale still uses: {{ $savedLinks->map(fn ($line) => \App\Models\Item::trimNumber((float) $line->qty, 3).' '.($line->piece?->unit ?: 'pc').' '.($line->piece?->name ?? '?').($line->variant ? ' ('.$line->variant->label.')' : '').($line->order_type ? ' ('.$line->order_type->label().')' : ''))->join(', ') }}.</span>
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
                                        <input id="unit_cost" name="unit_cost" type="number" step="any" min="0" x-ref="unitCost" value="{{ old('unit_cost', $item->unit_cost !== null ? (float) $item->unit_cost : null) }}" class="field num pl-7" placeholder="0.00">
                                    </div>
                                    <div class="mt-2 flex flex-wrap items-end gap-2" x-data="{ qty: null, amount: null }">
                                        <div>
                                            <label class="text-xs text-ink-500" for="unit_cost_calc_qty">Bought how many?</label>
                                            <input id="unit_cost_calc_qty" type="number" step="any" min="0" x-model.number="qty" class="field num mt-0.5 w-24 px-2 py-1 text-sm" placeholder="qty">
                                        </div>
                                        <span class="pb-2 text-xs text-ink-400">for ₱</span>
                                        <div>
                                            <label class="text-xs text-ink-500" for="unit_cost_calc_amount">Total paid</label>
                                            <input id="unit_cost_calc_amount" type="number" step="any" min="0" x-model.number="amount" class="field num mt-0.5 w-28 px-2 py-1 text-sm" placeholder="amount">
                                        </div>
                                        <button type="button" class="btn-quiet px-3 py-1.5 text-xs" :disabled="! (qty > 0 && amount >= 0)"
                                            @click="$refs.unitCost.value = Math.round((amount / qty) * 1e6) / 1e6">
                                            Use this
                                        </button>
                                        <p x-show="qty > 0 && amount >= 0" class="w-full text-xs text-ink-500">
                                            = <span class="num font-medium text-ink-700 dark:text-ink-200" x-text="qty > 0 ? formatUnitCost(amount / qty) : ''"></span> per <span x-text="unitLabel"></span>
                                            <span x-text="`(₱${amount ?? 0} ÷ ${qty ?? 0})`"></span> — not saved, just fills the field above
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <div class="grid gap-5 sm:grid-cols-2">
                            <div>
                                <label class="field-label" for="on_hand" x-text="isMenu ? 'Count this item itself (optional)' : 'On hand now'">On hand now</label>
                                <div x-show="countPerSize" x-cloak class="space-y-2">
                                    <p class="text-xs text-ink-500">Each size keeps its own count. Leave a size empty if it's made to order.</p>
                                    <template x-for="(variant, index) in variants" :key="index">
                                        <div class="flex items-center gap-3">
                                            <span class="w-28 shrink-0 truncate text-sm text-ink-600 dark:text-ink-300" x-text="variant.label || 'Size ' + (index + 1)"></span>
                                            <input x-model="variant.on_hand" :name="`variants[${index}][on_hand]`" :disabled="! isMenu || ! countPerSize" type="number" step="any" class="field num w-32" placeholder="Not counted" :aria-label="'On hand, ' + (variant.label || 'size ' + (index + 1))">
                                        </div>
                                    </template>
                                    <p x-show="sharedOnHand !== null" class="rounded-lg bg-brand-400/10 px-3 py-2 text-xs text-brand-800 dark:text-brand-200">
                                        This item has one shared count of <span class="num font-semibold" x-text="trim(sharedOnHand)"></span> right now. Entering a count for a size replaces it, and the change is logged in the stock history.
                                    </p>
                                </div>
                                <button type="button" x-show="isMenu && ! countPerSize && variants.length > 1" x-cloak @click="splitSizes = true" class="mb-2 text-xs font-medium text-brand-600 hover:underline dark:text-brand-300">Count each size separately</button>
                                <div x-show="! countPerSize" class="relative">
                                    <input id="on_hand" name="on_hand" type="number" step="any" x-model="onHand" :disabled="countPerSize" class="field num" :class="containerMode ? 'pr-12' : ''"
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
                                    <span class="num font-semibold" x-text="costPerSale(index) === null ? '—' : formatPeso((parseFloat(variant.price) || 0) - costPerSale(index))"></span>
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
                $stockedSizes = $item->variants->filter->tracksStock()->map(fn ($size) => ['id' => $size->id, 'label' => $size->label, 'onHand' => (float) $size->on_hand])->values();
                $restockState = [
                    'sizes' => $stockedSizes,
                    'sizeId' => (string) old('item_variant_id', $stockedSizes->first()['id'] ?? ''),
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
                    get currentOnHand() { const size = this.sizes.find((s) => String(s.id) === String(this.sizeId)); return size ? size.onHand : this.onHand },
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
                        <template x-if="sizes.length">
                            <div>
                                <label class="field-label" for="restock_size">Which size?</label>
                                <select id="restock_size" name="item_variant_id" x-model="sizeId" class="field w-40">
                                    <template x-for="size in sizes" :key="size.id">
                                        <option :value="size.id" x-text="size.label" :selected="String(size.id) === String(sizeId)"></option>
                                    </template>
                                </select>
                            </div>
                        </template>
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
                        <div>
                            <label class="field-label" for="restock_date">Bought on</label>
                            <input id="restock_date" name="date" type="date" value="{{ old('date', today()->toDateString()) }}" max="{{ today()->toDateString() }}" class="field num w-40">
                        </div>
                    </div>

                    <p class="rounded-xl bg-ink-100/70 px-4 py-3 text-sm dark:bg-white/[0.04]">
                        + <span class="num font-semibold" x-text="fmt(added)"></span> <span x-text="unit"></span>
                        → on hand <span class="num font-semibold" x-text="fmt(currentOnHand + added)"></span> <span x-text="unit"></span>
                        <template x-if="perUnit !== null">
                            <span class="text-ink-500"> · this purchase costs <span class="num font-medium text-ink-900 dark:text-white" x-text="'₱' + Number(perUnit.toFixed(6)).toLocaleString(undefined, { maximumFractionDigits: 6 })"></span> per <span x-text="unit"></span></span>
                        </template>
                    </p>

                    @if ($expensesEnabled)
                        <label class="flex items-center gap-2 text-sm">
                            <input type="hidden" name="log_expense" value="0">
                            {{-- Off by default: the owner ticks it only when this purchase should also show in Expenses. --}}
                            <input type="checkbox" name="log_expense" value="1" @checked(old('log_expense', false)) class="size-4 rounded border-ink-300 text-brand-500 focus:ring-brand-400 dark:border-white/20 dark:bg-white/[0.06]">
                            @if ($item->isCostedWhenUsed(auth()->user()->business))
                                Log what I paid as a <span class="font-medium">Stock purchase</span>
                            @else
                                Log what I paid as a <span class="font-medium">Supplies</span> expense
                            @endif
                        </label>
                        <p class="-mt-2 pl-6 text-xs text-ink-500">
                            @if ($item->isCostedWhenUsed(auth()->user()->business))
                                A cash outflow today (Money movement), not a profit expense — it counts against profit through COGS once {{ $item->name }} sells.
                            @else
                                {{ $item->name }} isn't linked to a sale or counted at closing, so what you pay for it is logged as an Expense today.
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

        @if ($isEditing && $item->kind?->value !== 'menu' && $usedInRecipes->isNotEmpty())
            <section class="surface mt-8 p-6">
                <h2 class="font-semibold">Linked into {{ $usedInRecipes->count() }} {{ \Illuminate\Support\Str::plural('menu item', $usedInRecipes->count()) }}</h2>
                <p class="text-xs text-ink-500">{{ $item->name }} comes off the shelf when any of these sell. Visit one to edit its links, unlink it from just that one, or unlink from all of them at once.</p>
                <ul class="mt-4 divide-y divide-ink-200 dark:divide-white/[0.06]">
                    @foreach ($usedInRecipes as $menuItem)
                        <li class="flex items-center justify-between gap-3 py-2">
                            <a href="{{ route('admin.inventory.edit', $menuItem) }}" class="text-sm font-medium text-ink-900 hover:underline dark:text-white">{{ $menuItem->name }}</a>
                            <form method="POST" action="{{ route('admin.inventory.unlink-recipe', [$item, $menuItem]) }}"
                                data-confirm-title="Unlink {{ $item->name }} from {{ $menuItem->name }}?"
                                data-confirm="{{ $menuItem->name }} keeps selling, just without taking {{ $item->name }} off the shelf any more."
                                data-confirm-action="Unlink" data-confirm-danger>
                                @csrf
                                <button type="submit" class="btn-quiet px-3 py-1.5 text-xs text-loss-600 dark:text-loss-400" data-loading-text="Unlinking…">
                                    <x-icon name="link" class="size-4" /> Unlink
                                </button>
                            </form>
                        </li>
                    @endforeach
                </ul>
                <form method="POST" action="{{ route('admin.inventory.unlink-recipes', $item) }}" class="mt-4"
                    data-confirm-title="Unlink {{ $item->name }} from every recipe?"
                    data-confirm="Removes it from {{ $usedInRecipes->count() }} {{ \Illuminate\Support\Str::plural('menu item', $usedInRecipes->count()) }}: {{ $usedInRecipes->pluck('name')->join(', ') }}. Each one keeps selling, just without taking this off the shelf any more."
                    data-confirm-action="Unlink from all" data-confirm-danger>
                    @csrf
                    <button type="submit" class="btn-quiet text-loss-600 dark:text-loss-400" data-loading-text="Unlinking…">
                        <x-icon name="link" class="size-4" /> Unlink from all recipes
                    </button>
                </form>
            </section>
        @endif

        @if ($isEditing)
            <div class="mt-8 flex flex-wrap items-center justify-end gap-2 border-t border-ink-200 pt-6 dark:border-white/[0.06]">
                <a href="{{ route('admin.inventory.history', $item) }}" class="btn-quiet px-3 py-1.5 text-xs">Stock history</a>

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
