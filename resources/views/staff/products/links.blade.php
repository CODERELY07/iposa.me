<x-app-layout :title="'Links · '.$item->name">
    <div class="mx-auto max-w-3xl space-y-6 px-4 py-8 sm:px-8">
        <a href="{{ route('staff.products') }}" class="inline-flex items-center gap-1 text-sm text-ink-500 hover:text-ink-900 dark:hover:text-white">
            <x-icon name="chevron-right" class="size-4 rotate-180" /> Products
        </a>

        <x-page-header :title="$item->name" description="What one sale uses. Each sale takes these off the shelf." />

        <form method="POST" action="{{ route('staff.products.links.update', $item) }}" class="surface p-6"
            x-data="{ recipe: @js(array_values($recipe)), variants: @js($variants), pieceUnits: @js($pieceUnits) }">
            @csrf
            @method('PUT')

            @if ($errors->any())
                <p class="mb-4 rounded-xl bg-loss-400/10 p-3 text-sm text-loss-600 dark:text-loss-400">{{ $errors->first() }}</p>
            @endif

            @if ($pieces->isEmpty())
                <p class="rounded-xl bg-ink-100 p-3 text-sm text-ink-600 dark:bg-white/[0.05] dark:text-ink-300">
                    There are no pieces or liquids to link yet. Ask the owner to add them to the inventory.
                </p>
            @else
                <div class="space-y-2">
                    <template x-for="(line, index) in recipe" :key="index">
                        <div class="flex flex-wrap items-center gap-2">
                            <input x-model="line.qty" :name="`recipe[${index}][qty]`" type="number" min="0.001" step="any" class="field num w-20 text-center" aria-label="Quantity">
                            <span class="w-8 text-sm text-ink-400" x-text="pieceUnits[line.piece_item_id] || '×'"></span>
                            <select x-model="line.piece_item_id" :name="`recipe[${index}][piece_item_id]`" class="field min-w-[10rem] flex-1" aria-label="Piece">
                                @foreach ($pieces->groupBy(fn ($piece) => $piece->kind->value) as $kindKey => $group)
                                    <optgroup label="{{ $kindKey === 'bulk' ? 'Liquids & bulk' : 'Pieces' }}">
                                        @foreach ($group as $piece)
                                            <option value="{{ $piece->id }}">{{ $piece->name }}{{ $piece->unit ? ' ('.$piece->unit.')' : '' }}</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                            <select x-show="variants.length > 1" x-model="line.variant_index" :name="`recipe[${index}][variant_index]`" :disabled="variants.length < 2" class="field w-36" aria-label="Which size">
                                <option value="">All sizes</option>
                                <template x-for="(variant, variantIndex) in variants" :key="variantIndex">
                                    <option :value="variantIndex" x-text="'Only ' + variant.label" :selected="String(line.variant_index) === String(variantIndex)"></option>
                                </template>
                            </select>
                            <button type="button" @click="recipe.splice(index, 1)" class="btn-quiet size-9 !px-0" aria-label="Remove link"><x-icon name="x" class="size-4" /></button>
                        </div>
                    </template>
                    <p x-show="! recipe.length" class="text-sm text-ink-500">No links. Sales of {{ $item->name }} don't take anything off the shelf.</p>
                </div>

                <button type="button" @click="recipe.push({ piece_item_id: {{ $pieces->first()->id }}, qty: 1, variant_index: null })" class="btn-quiet mt-3 text-brand-600 dark:text-brand-300">
                    <x-icon name="plus" class="size-4" /> Link a piece or liquid
                </button>
            @endif

            <div class="mt-6 flex justify-end gap-2 border-t border-ink-200 pt-5 dark:border-white/[0.06]">
                <a href="{{ route('staff.products') }}" class="btn-ghost">Cancel</a>
                <button type="submit" class="btn-primary" data-loading-text="Saving…">Save links</button>
            </div>
        </form>
    </div>
</x-app-layout>
