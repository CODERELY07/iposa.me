<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One way an item is bought: "bottle, holds 1000 ml, ₱145".
 * `size` is in the item's own unit, so counts add up across sizes.
 */
#[Fillable(['item_id', 'label', 'size', 'price', 'sort'])]
class ItemContainer extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'decimal:3',
            'price' => 'decimal:2',
            'sort' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Item, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * What one unit of the item costs when bought in this container, or null
     * without a price. Six decimals, e.g. ₱145 / 1000 ml = ₱0.145000 per ml.
     */
    public function costPerUnit(): ?float
    {
        if ($this->price === null || (float) $this->size <= 0) {
            return null;
        }

        return round((float) $this->price / (float) $this->size, 6);
    }
}
