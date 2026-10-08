<?php

namespace App\Http\Requests\Staff;

use App\Http\Requests\Admin\RestockItemRequest;
use Illuminate\Validation\Rule;

/**
 * A cashier's restock: how many arrived, never what was paid.
 */
class RestockProductRequest extends RestockItemRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Made up by the register for a delivery added offline, so a replay isn't added twice.
            'uuid' => ['nullable', 'uuid'],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:999999'],
            'container_id' => ['nullable', 'integer', Rule::exists('item_containers', 'id')->where('item_id', $this->route('item')?->id)],
            'item_variant_id' => $this->variantRules(),
        ];
    }
}
