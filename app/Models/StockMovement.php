<?php

namespace App\Models;

use App\Enums\StockMovementReason;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Audit trail: every change to an item's on-hand count, and why.
 */
#[Fillable(['business_id', 'item_id', 'item_variant_id', 'qty_change', 'costed_qty', 'reason', 'order_id', 'audit_id', 'user_id', 'created_at'])]
class StockMovement extends Model
{
    use BelongsToBusiness;

    public const UPDATED_AT = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'qty_change' => 'decimal:3',
            'costed_qty' => 'decimal:3',
            'reason' => StockMovementReason::class,
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
     * The size this movement counted, when the item keeps a separate count per size.
     *
     * @return BelongsTo<ItemVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ItemVariant::class, 'item_variant_id');
    }

    /**
     * The order this movement was a sale or void for, if any.
     *
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * The closing audit this movement was counted in, if any.
     *
     * @return BelongsTo<Audit, $this>
     */
    public function audit(): BelongsTo
    {
        return $this->belongsTo(Audit::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
