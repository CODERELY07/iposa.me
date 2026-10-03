<?php

namespace App\Http\Requests\Admin;

use App\Models\Item;
use App\Models\ItemContainer;
use App\Models\ItemVariant;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RestockItemRequest extends FormRequest
{
    /**
     * Anything counted on a shelf can be restocked: pieces, liquids, and menu
     * items that count themselves (bottled water). Made-to-order food cannot.
     */
    public function authorize(): bool
    {
        $item = $this->item();

        return $item !== null && ($item->kind->value !== 'menu' || $item->tracksAnyStock());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'quantity' => ['required', 'numeric', 'gt:0', 'max:999999'],
            'container_id' => ['nullable', 'integer', Rule::exists('item_containers', 'id')->where('item_id', $this->item()?->id)],
            'item_variant_id' => $this->variantRules(),
            'supplier' => ['nullable', 'string', 'max:80'],
            'paid' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'log_expense' => ['nullable', 'boolean'],
            'date' => ['nullable', 'date', 'before_or_equal:today'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'quantity.required' => 'How many did you buy?',
            'quantity.gt' => 'How many did you buy?',
            'container_id.exists' => 'Pick one of this item’s containers.',
            'item_variant_id.required' => 'Which size did you restock?',
            'item_variant_id.exists' => 'Pick one of the sizes that keep their own count.',
        ];
    }

    public function container(): ?ItemContainer
    {
        $id = $this->validated('container_id');

        return $id === null ? null : $this->item()?->containers()->whereKey($id)->first();
    }

    /**
     * Which size was restocked, when the item keeps a separate count per size.
     *
     * @return list<mixed>
     */
    protected function variantRules(): array
    {
        $item = $this->item();

        return [
            Rule::requiredIf($item !== null && $item->tracksStockPerSize()),
            'nullable',
            'integer',
            Rule::exists('item_variants', 'id')->where('item_id', $item?->id)->whereNotNull('on_hand'),
        ];
    }

    public function variant(): ?ItemVariant
    {
        $id = $this->validated('item_variant_id');

        return $id === null ? null : $this->item()?->variants()->whereKey($id)->first();
    }

    public function paid(): ?float
    {
        $paid = $this->validated('paid');

        return $paid === null || $paid === '' ? null : (float) $paid;
    }

    /**
     * Defaults to today, so a restock can be backdated when it was bought earlier
     * and just wasn't entered yet.
     */
    public function boughtOn(): CarbonImmutable
    {
        $date = $this->validated('date');

        return $date ? CarbonImmutable::parse($date)->startOfDay() : CarbonImmutable::today();
    }

    private function item(): ?Item
    {
        $item = $this->route('item');

        return $item instanceof Item ? $item : null;
    }
}
