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
            'quantity' => ['required', 'numeric', 'gt:0', 'max:999999'],
            'container_id' => ['nullable', 'integer', Rule::exists('item_containers', 'id')->where('item_id', $this->route('item')?->id)],
        ];
    }
}
