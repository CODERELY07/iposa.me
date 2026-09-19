<?php

namespace App\Http\Requests\Admin;

use App\Enums\ItemKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create and update share the same rules.
 */
class SaveItemRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $businessId = $this->user()->business_id;
        $isMenu = $this->input('kind') === ItemKind::Menu->value;
        $currentItemId = $this->route('item')?->id;

        return [
            'kind' => ['required', Rule::enum(ItemKind::class)],
            'name' => ['required', 'string', 'max:120'],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->where('business_id', $businessId)],
            'unit' => ['nullable', 'string', 'max:60'],
            'on_hand' => ['nullable', 'numeric', 'min:-99999', 'max:9999999'],
            'low_threshold' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'unit_cost' => [Rule::requiredIf(! $isMenu), 'nullable', 'numeric', 'min:0', 'max:9999999'],

            'variants' => [Rule::requiredIf($isMenu), 'nullable', 'array', 'max:12'],
            'variants.*.id' => ['nullable', 'integer'],
            'variants.*.label' => ['required', 'string', 'max:40', 'distinct:ignore_case'],
            'variants.*.cost' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'variants.*.price' => ['required', 'numeric', 'min:0', 'max:9999999'],

            'recipe' => ['nullable', 'array', 'max:30'],
            'recipe.*.piece_item_id' => [
                'required', 'integer',
                Rule::exists('items', 'id')->where('business_id', $businessId)->where('kind', ItemKind::Piece->value)->whereNull('archived_at'),
                Rule::notIn(array_filter([$currentItemId])),
            ],
            'recipe.*.qty' => ['required', 'numeric', 'gt:0', 'max:9999'],
            'recipe.*.variant_index' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'variants.required' => 'Menu items need at least one size with a price.',
            'variants.*.label.required' => 'Give every size a name (e.g. Regular, 16oz).',
            'variants.*.label.distinct' => 'Each size needs a different name.',
            'variants.*.price.required' => 'Every size needs a selling price.',
            'unit_cost.required' => 'Enter what one unit costs you, so usage can be priced.',
            'recipe.*.piece_item_id.exists' => 'Pick a piece from your inventory.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'on_hand' => 'on hand',
            'low_threshold' => 'alert level',
            'unit_cost' => 'cost per unit',
        ];
    }
}
