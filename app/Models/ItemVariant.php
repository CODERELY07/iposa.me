<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['item_id', 'label', 'cost', 'price', 'sort'])]
class ItemVariant extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cost' => 'decimal:2',
            'price' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Item, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function profit(): float
    {
        return round((float) $this->price - (float) $this->cost, 2);
    }

    /**
     * Margin as a percentage of the selling price, or null when the price is zero.
     */
    public function marginPercent(): ?float
    {
        return (float) $this->price > 0 ? round($this->profit() / (float) $this->price * 100, 1) : null;
    }
}
