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
        $hasPricedContainer = ! $isMenu && collect((array) $this->input('containers', []))
            ->contains(fn ($container) => is_array($container) && ($container['price'] ?? '') !== '' && $container['price'] !== null);

        // An item counted in "1L bottle" moving to containers is now stored in ml:
        // its old count means something else, so it has to be entered again.
        $currentItem = $this->route('item');
        $isConvertingToContainers = ! $isMenu
            && $currentItem !== null
            && filled($this->input('containers'))
            && $currentItem->containers()->doesntExist()
            && $currentItem->on_hand !== null;

        return [
            'kind' => ['required', Rule::enum(ItemKind::class)],
            'name' => ['required', 'string', 'max:120'],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->where('business_id', $businessId)],
            'unit' => [Rule::requiredIf(! $isMenu && filled($this->input('containers'))), 'nullable', 'string', 'max:60'],
            'on_hand' => [Rule::requiredIf($isConvertingToContainers), 'nullable', 'numeric', 'min:-99999', 'max:9999999'],
            'low_threshold' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'unit_cost' => [Rule::requiredIf(! $isMenu && ! $hasPricedContainer), 'nullable', 'numeric', 'min:0', 'max:9999999'],

            'containers' => ['nullable', 'array', 'max:5'],
            'containers.*.id' => ['nullable', 'integer'],
            'containers.*.label' => ['required', 'string', 'max:40', 'distinct:ignore_case'],
            'containers.*.size' => ['required', 'numeric', 'gt:0', 'max:9999999'],
            'containers.*.price' => ['nullable', 'numeric', 'min:0', 'max:9999999'],

            'variants' => [Rule::requiredIf($isMenu), 'nullable', 'array', 'max:12'],
            'variants.*.id' => ['nullable', 'integer'],
            'variants.*.label' => ['required', 'string', 'max:40', 'distinct:ignore_case'],
            'variants.*.cost' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'variants.*.price' => ['required', 'numeric', 'min:0', 'max:9999999'],

            'include_recipe_cost' => ['nullable', 'boolean'],
            'recipe' => ['nullable', 'array', 'max:30'],
            'recipe.*.piece_item_id' => [
                'required', 'integer',
                // Pieces (1 bun) and liquids (15 ml ketchup): both come off the shelf with each sale.
                Rule::exists('items', 'id')->where('business_id', $businessId)->whereIn('kind', [ItemKind::Piece->value, ItemKind::Bulk->value])->whereNull('archived_at'),
                Rule::notIn(array_filter([$currentItemId])),
            ],
            'recipe.*.qty' => ['required', 'numeric', 'gt:0', 'max:99999'],
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
            'unit_cost.required' => 'Enter what one unit costs you, or what you pay for one container, so usage can be priced.',
            'unit.required' => 'Pick what the containers are measured in (ml, g…).',
            'on_hand.required' => 'Enter what is on the shelf now in the new unit. The old count was in a different unit, so it can’t be reused.',
            'containers.*.label.required' => 'Name the container (bottle, jug, tin).',
            'containers.*.label.distinct' => 'Each container needs a different name.',
            'containers.*.size.required' => 'Say how much one container holds.',
            'containers.*.size.gt' => 'A container has to hold something.',
            'recipe.*.piece_item_id.exists' => 'Pick a piece or a liquid from your inventory.',
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
