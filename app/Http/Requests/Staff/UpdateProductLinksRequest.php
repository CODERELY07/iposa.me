<?php

namespace App\Http\Requests\Staff;

use App\Enums\ItemKind;
use App\Models\Item;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A cashier setting what one sale of a menu item uses. Nothing else on the item.
 */
class UpdateProductLinksRequest extends FormRequest
{
    public function authorize(): bool
    {
        $item = $this->route('item');

        return $item instanceof Item && $item->kind === ItemKind::Menu && $item->archived_at === null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // A size that no longer exists must not silently become "all sizes".
        $lastSize = max(0, $this->route('item')->variants()->count() - 1);

        return [
            'recipe' => ['nullable', 'array', 'max:30'],
            'recipe.*.piece_item_id' => [
                'required', 'integer',
                Rule::exists('items', 'id')->where('business_id', $this->user()->business_id)->whereIn('kind', [ItemKind::Piece->value, ItemKind::Bulk->value])->whereNull('archived_at'),
            ],
            'recipe.*.qty' => ['required', 'numeric', 'gt:0', 'max:99999'],
            'recipe.*.variant_index' => ['nullable', 'integer', 'min:0', 'max:'.$lastSize],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'recipe.*.piece_item_id.exists' => 'Pick a piece or a liquid from the list.',
            'recipe.*.variant_index.max' => 'The sizes of this item changed. Reload the page and try again.',
        ];
    }
}
