<?php

namespace App\Models;

use App\Enums\OrderType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * "One Cheeseburger uses 1 bun." A null variant means every size uses it,
 * and a null order type means both dine-in and take-out use it.
 */
#[Fillable(['item_id', 'item_variant_id', 'piece_item_id', 'qty', 'order_type'])]
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
            'order_type' => OrderType::class,
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

    public function appliesToOrderType(OrderType $orderType): bool
    {
        return $this->order_type === null || $this->order_type === $orderType;
    }
}
