<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * "One Cheeseburger uses 1 bun." A null variant means every size uses it.
 */
#[Fillable(['item_id', 'item_variant_id', 'piece_item_id', 'qty'])]
class RecipeLine extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'qty' => 'decimal:3',
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
     * @return BelongsTo<ItemVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ItemVariant::class, 'item_variant_id');
    }

    /**
     * @return BelongsTo<Item, $this>
     */
    public function piece(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'piece_item_id');
    }

    public function appliesTo(ItemVariant $variant): bool
    {
        return $this->item_variant_id === null || $this->item_variant_id === $variant->id;
    }
}
