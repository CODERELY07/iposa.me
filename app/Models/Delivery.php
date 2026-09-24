<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A cashier's restock, waiting for the owner to check it against the supplier's receipt.
 */
#[Fillable(['business_id', 'item_id', 'user_id', 'received_by', 'quantity', 'item_container_id', 'container_label', 'container_size', 'added', 'status', 'receipt_added', 'paid', 'missing_cost', 'checked_by', 'checked_by_name', 'checked_at'])]
class Delivery extends Model
{
    use BelongsToBusiness;

    public const PENDING = 'pending';

    public const CHECKED = 'checked';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'container_size' => 'decimal:3',
            'added' => 'decimal:3',
            'receipt_added' => 'decimal:3',
            'paid' => 'decimal:2',
            'missing_cost' => 'decimal:2',
            'checked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Item, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }

    /**
     * What the cashier recorded, the way they entered it: "2 tins" or "40 pc".
     */
    public function describeRecorded(): string
    {
        if ($this->container_label !== null) {
            return Item::trimNumber((float) $this->quantity).' '.str($this->container_label)->plural((float) $this->quantity);
        }

        return trim(Item::trimNumber((float) $this->quantity).' '.($this->item?->unit ?: 'pc'));
    }

    /**
     * The unit the receipt quantity is typed in: the container, or the item's own unit.
     */
    public function entryUnit(): string
    {
        return $this->container_label !== null ? (string) str($this->container_label)->plural() : ($this->item?->unit ?: 'pc');
    }
}
