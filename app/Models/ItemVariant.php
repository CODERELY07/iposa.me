<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['item_id', 'label', 'cost', 'cost_updated_at', 'price', 'on_hand', 'sort'])]
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
            'on_hand' => 'decimal:3',
            'cost_updated_at' => 'datetime',
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
     * Whether this size keeps its own count (a menu item that counts itself, per size).
     */
    public function tracksStock(): bool
    {
        return $this->on_hand !== null;
    }

    /**
     * The cost of one sale, per the item's costing method. Null when it isn't known.
     */
    public function costPerSale(): ?float
    {
        return $this->item->costPerSale($this);
    }

    /**
     * Null when the cost isn't known -- there is no profit figure to show.
     */
    public function profit(): ?float
    {
        $cost = $this->costPerSale();

        return $cost !== null ? round((float) $this->price - $cost, 2) : null;
    }

    /**
     * Margin as a percentage of the selling price. Null when the price is zero or the cost isn't known.
     */
    public function marginPercent(): ?float
    {
        $profit = $this->profit();

        return $profit !== null && (float) $this->price > 0 ? round($profit / (float) $this->price * 100, 1) : null;
    }

    /**
     * A nudge, not a fact: the owner may still be exactly right. There is no
     * ingredient signal for a manual cost to check itself against, so this is
     * the only freshness check available -- it never changes the cost itself.
     */
    public function isCostStale(int $days = 90): bool
    {
        return $this->cost_updated_at !== null && $this->cost_updated_at->lt(now()->subDays($days));
    }
}
