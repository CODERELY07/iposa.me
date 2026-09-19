<?php

namespace App\Models;

use App\Enums\ItemKind;
use App\Models\Concerns\BelongsToBusiness;
use Database\Factories\ItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['business_id', 'category_id', 'kind', 'name', 'unit', 'on_hand', 'low_threshold', 'unit_cost', 'archived_at'])]
class Item extends Model
{
    /** @use HasFactory<ItemFactory> */
    use BelongsToBusiness, HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => ItemKind::class,
            'on_hand' => 'decimal:3',
            'low_threshold' => 'decimal:3',
            'unit_cost' => 'decimal:2',
            'archived_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Sizes and prices (menu items only).
     *
     * @return HasMany<ItemVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ItemVariant::class)->orderBy('sort')->orderBy('id');
    }

    /**
     * Pieces one sale of this menu item uses.
     *
     * @return HasMany<RecipeLine, $this>
     */
    public function recipeLines(): HasMany
    {
        return $this->hasMany(RecipeLine::class);
    }

    /**
     * @return HasMany<StockMovement, $this>
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * @param  Builder<Item>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('archived_at');
    }

    /**
     * @param  Builder<Item>  $query
     */
    public function scopeOfKind(Builder $query, ItemKind $kind): void
    {
        $query->where('kind', $kind);
    }

    public function isMenu(): bool
    {
        return $this->kind === ItemKind::Menu;
    }

    public function tracksStock(): bool
    {
        return $this->on_hand !== null;
    }

    /**
     * Alert threshold: the item's own, or the business default.
     */
    public function effectiveLowThreshold(Business $business): float
    {
        return $this->low_threshold !== null ? (float) $this->low_threshold : $business->lowStockThreshold();
    }

    public function isLowStock(Business $business): bool
    {
        return $this->tracksStock() && (float) $this->on_hand <= $this->effectiveLowThreshold($business);
    }
}
