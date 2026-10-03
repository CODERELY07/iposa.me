<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

/**
 * Waste is logged the way a restock is: how much, of which container, which size.
 */
class LogWasteRequest extends RestockItemRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'quantity' => ['required', 'numeric', 'gt:0', 'max:999999'],
            'container_id' => ['nullable', 'integer', Rule::exists('item_containers', 'id')->where('item_id', $this->route('item')?->id)],
            'item_variant_id' => $this->variantRules(),
            'note' => ['nullable', 'string', 'max:160'],
            'date' => ['nullable', 'date', 'before_or_equal:today'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'quantity.required' => 'How much was wasted?',
            'quantity.gt' => 'How much was wasted?',
        ] + parent::messages();
    }
}
