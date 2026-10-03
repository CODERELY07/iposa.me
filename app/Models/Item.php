<?php

namespace App\Models;

use App\Enums\CostingMethod;
use App\Enums\ItemKind;
use App\Models\Concerns\BelongsToBusiness;
use Database\Factories\ItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['business_id', 'category_id', 'kind', 'name', 'unit', 'on_hand', 'low_threshold', 'unit_cost', 'costing_method', 'include_audit_cost', 'archived_at'])]
class Item extends Model
{
    /** @use HasFactory<ItemFactory> */
    use BelongsToBusiness, HasFactory;

    /**
     * Units small enough that nobody counts them one by one: the audit steps in
     * fifties, and containers (a bottle, a tin) are how staff actually count.
     *
     * @var list<string>
     */
    public const SMALL_UNITS = ['ml', 'g'];

    /**
     * Units an owner can pick when an item is bought in containers.
     *
     * @var array<string, string>
     */
    public const MEASURES = ['ml' => 'ml', 'l' => 'L', 'g' => 'g', 'kg' => 'kg'];

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
            'unit_cost' => 'decimal:6',
            'costing_method' => CostingMethod::class,
            'include_audit_cost' => 'boolean',
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
        return $this->hasMany(ItemVariant::class)->orderBy('sort')->orderBy('id')->chaperone();
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
     * Every change to this item's links, newest first.
     *
     * @return HasMany<RecipeChange, $this>
     */
    public function recipeChanges(): HasMany
    {
        return $this->hasMany(RecipeChange::class)->latest('id');
    }

    /**
     * What the linked pieces and liquids of one sale of this size cost, at their
     * current cost per unit. Null when nothing is linked to this size -- there is
     * no recipe cost to report, not a free one. Expects `recipeLines.piece` loaded.
     */
    public function linkedCostFor(ItemVariant $variant): ?float
    {
        $lines = $this->recipeLines->filter(fn (RecipeLine $line) => $line->appliesTo($variant));

        if ($lines->isEmpty()) {
            return null;
        }

        return round((float) $lines->sum(fn (RecipeLine $line) => (float) $line->qty * (float) ($line->piece?->unit_cost ?? 0)), 2);
    }

    /**
     * The cost of one sale of this size, per the item's costing method.
     *
     * A size with no ingredients linked has no recipe cost to add, so the cost the owner
     * typed stands on its own: linking is optional, and a typed cost is a real cost. It is
     * only unknown (null, never a silent ₱0) when nothing usable is configured -- no typed
     * cost where one is needed, or nothing at all for a linked-only size.
     */
    public function costPerSale(ItemVariant $variant): ?float
    {
        $manual = $variant->cost !== null ? (float) $variant->cost : null;
        $linked = $this->linkedCostFor($variant);

        $cost = match ($this->costing_method) {
            CostingMethod::ManualOnly => $manual,
            // Linked pieces when the size has them; otherwise the typed cost is the cost.
            CostingMethod::LinkedOnly => $linked ?? $manual,
            // The typed cost, plus the linked pieces when there are any.
            CostingMethod::ManualPlusLinked => $manual !== null ? $manual + ($linked ?? 0.0) : null,
        };

        return $cost !== null ? round($cost, 2) : null;
    }

    /**
     * Whether costPerSale() can actually price this size right now.
     */
    public function hasKnownCost(ItemVariant $variant): bool
    {
        return $this->costPerSale($variant) !== null;
    }

    /**
     * How this item is bought and counted (bottle, jug, tin), smallest first by sort.
     * Empty for items counted in their own unit, which behave as they always did.
     *
     * @return HasMany<ItemContainer, $this>
     */
    public function containers(): HasMany
    {
        return $this->hasMany(ItemContainer::class)->orderBy('sort')->orderBy('id');
    }

    /**
     * Every purchase of this item, who from and at what price.
     *
     * @return HasMany<ItemPurchase, $this>
     */
    public function purchases(): HasMany
    {
        return $this->hasMany(ItemPurchase::class);
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
     * Whether the sizes of this menu item each keep their own count (bottled water,
     * 500ml and 1L), instead of one count shared by every size.
     */
    public function tracksStockPerSize(): bool
    {
        return $this->variants->contains(fn (ItemVariant $variant) => $variant->tracksStock());
    }

    public function tracksAnyStock(): bool
    {
        return $this->tracksStock() || $this->tracksStockPerSize();
    }

    public function isVariantLowStock(ItemVariant $variant, Business $business): bool
    {
        return $variant->tracksStock() && (float) $variant->on_hand <= $this->effectiveLowThreshold($business);
    }

    /**
     * Whether using this item already lowers profit somewhere else: a sale's cost
     * (menu items that count themselves, pieces and liquids in recipes) or the
     * closing count (bulk, and pieces when the shop counts them).
     * Buying such stock is not an expense; buying anything else is a supply.
     */
    public function isCostedWhenUsed(Business $business): bool
    {
        return match ($this->kind) {
            ItemKind::Menu, ItemKind::Bulk => true,
            ItemKind::Piece => $business->auditsPieces() || RecipeLine::query()->where('piece_item_id', $this->id)->exists(),
        };
    }

    public function hasContainers(): bool
    {
        return $this->containers->isNotEmpty();
    }

    public function isSmallUnit(): bool
    {
        return in_array(strtolower(trim((string) $this->unit)), self::SMALL_UNITS, true);
    }

    /**
     * How much one tap of − / + changes a count in the item's own unit.
     */
    public function countStep(): float
    {
        return match (true) {
            $this->isSmallUnit() => 50.0,
            $this->kind === ItemKind::Piece => 1.0,
            default => 0.25,
        };
    }

    /**
     * A quantity the way an owner reads it: "2.5 bottles · 2,500 ml", or "4.5 1L bottle"
     * for an item without containers.
     */
    public function describeQuantity(float|string|null $quantity): string
    {
        $quantity = (float) ($quantity ?? 0);
        $unit = $this->unit ?: ($this->kind === ItemKind::Piece ? 'pcs' : '');
        $inUnit = trim(self::trimNumber($quantity).' '.$unit);
        $container = $this->containers->first();

        if ($container === null || (float) $container->size <= 0) {
            return $inUnit;
        }

        $containers = $quantity / (float) $container->size;

        return self::trimNumber($containers).' '.str($container->label)->plural($containers).' · '.$inUnit;
    }

    /**
     * A cost per unit with at least 2 and at most 6 decimals: 145 → "145.00",
     * 0.145 → "0.145", 0.011889 → "0.011889".
     */
    public static function formatUnitCost(float|string|null $cost): string
    {
        $formatted = rtrim(number_format((float) ($cost ?? 0), 6), '0');
        [$whole, $decimals] = explode('.', $formatted) + [1 => ''];

        return $whole.'.'.str_pad($decimals, 2, '0');
    }

    /**
     * 2 → "2", 2.5 → "2.5", 2838.75 → "2,838.75".
     */
    public static function trimNumber(float $value, int $decimals = 2): string
    {
        return rtrim(rtrim(number_format($value, $decimals), '0'), '.');
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
