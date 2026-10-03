<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One purchase of an item: how many, what was paid, and so what each unit cost.
 */
#[Fillable(['business_id', 'item_id', 'supplier_id', 'user_id', 'unit_cost', 'quantity', 'paid', 'source', 'bought_on'])]
class ItemPurchase extends Model
{
    use BelongsToBusiness;

    public const RESTOCK = 'restock';

    public const DELIVERY = 'delivery';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_cost' => 'decimal:6',
            'quantity' => 'decimal:3',
            'paid' => 'decimal:2',
            'bought_on' => 'date',
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
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
