<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Name, size, price and cost are copied at sale time so reports never change when the menu does.
 */
#[Fillable(['order_id', 'item_id', 'item_variant_id', 'name', 'variant_label', 'price', 'unit_cost', 'qty'])]
class OrderLine extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'qty' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function lineTotal(): float
    {
        return round((float) $this->price * $this->qty, 2);
    }
}
