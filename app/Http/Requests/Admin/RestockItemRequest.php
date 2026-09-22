<?php

namespace App\Http\Requests\Admin;

use App\Models\Item;
use App\Models\ItemContainer;
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

        return $item !== null && ($item->kind->value !== 'menu' || $item->tracksStock());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'quantity' => ['required', 'numeric', 'gt:0', 'max:999999'],
            'container_id' => ['nullable', 'integer', Rule::exists('item_containers', 'id')->where('item_id', $this->item()?->id)],
            'paid' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'log_expense' => ['nullable', 'boolean'],
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
        ];
    }

    public function container(): ?ItemContainer
    {
        $id = $this->validated('container_id');

        return $id === null ? null : $this->item()?->containers()->whereKey($id)->first();
    }

    public function paid(): ?float
    {
        $paid = $this->validated('paid');

        return $paid === null || $paid === '' ? null : (float) $paid;
    }

    private function item(): ?Item
    {
        $item = $this->route('item');

        return $item instanceof Item ? $item : null;
    }
}
