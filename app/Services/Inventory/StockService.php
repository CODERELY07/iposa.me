<?php

namespace App\Services\Inventory;

use App\Enums\StockMovementReason;
use App\Models\Business;
use App\Models\Item;
use App\Models\ItemVariant;
use App\Models\StockMovement;
use Carbon\CarbonInterface;

/**
 * The only place on-hand counts change. Every change writes a stock movement.
 * Call inside a DB transaction.
 */
class StockService
{
    /**
     * Apply quantity changes (item id => signed change) and log them.
     *
     * @param  array<int, float>  $changes
     * @param  array{order_id?: int|null, audit_id?: int|null, user_id?: int|null}  $references
     * @param  array<int, float>  $costedChanges  item id => the part of the change a sale charged in its cost (same sign)
     */
    public function apply(Business $business, array $changes, StockMovementReason $reason, array $references = [], ?CarbonInterface $at = null, array $costedChanges = []): void
    {
        $changes = array_filter($changes, fn (float $change) => abs($change) >= 0.0005);

        if ($changes === []) {
            return;
        }

        $items = Item::withoutGlobalScopes()
            ->where('business_id', $business->id)
            ->whereKey(array_keys($changes))
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($changes as $itemId => $change) {
            $item = $items->get($itemId);

            if ($item === null) {
                continue;
            }

            if ($item->tracksStock()) {
                $item->forceFill(['on_hand' => round((float) $item->on_hand + $change, 3)])->save();
            }

            StockMovement::withoutGlobalScopes()->create([
                'business_id' => $business->id,
                'item_id' => $item->id,
                'qty_change' => round($change, 3),
                'costed_qty' => round($costedChanges[$itemId] ?? 0, 3),
                'reason' => $reason,
                'order_id' => $references['order_id'] ?? null,
                'audit_id' => $references['audit_id'] ?? null,
                'user_id' => $references['user_id'] ?? null,
                'created_at' => $at ?? now(),
            ]);
        }
    }

    /**
     * Apply quantity changes to menu-item sizes that keep their own count (size id => signed
     * change), logging each against the item and the size. A size that doesn't count itself is skipped.
     *
     * @param  array<int, float>  $changes
     * @param  array{order_id?: int|null, user_id?: int|null}  $references
     */
    public function applyToVariants(Business $business, array $changes, StockMovementReason $reason, array $references = [], ?CarbonInterface $at = null): void
    {
        $changes = array_filter($changes, fn (float $change) => abs($change) >= 0.0005);

        if ($changes === []) {
            return;
        }

        $variants = ItemVariant::query()
            ->whereKey(array_keys($changes))
            ->whereHas('item', fn ($query) => $query->withoutGlobalScopes()->where('business_id', $business->id))
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($changes as $variantId => $change) {
            $variant = $variants->get($variantId);

            if ($variant === null || ! $variant->tracksStock()) {
                continue;
            }

            $variant->forceFill(['on_hand' => round((float) $variant->on_hand + $change, 3)])->save();

            StockMovement::withoutGlobalScopes()->create([
                'business_id' => $business->id,
                'item_id' => $variant->item_id,
                'item_variant_id' => $variant->id,
                'qty_change' => round($change, 3),
                'costed_qty' => 0,
                'reason' => $reason,
                'order_id' => $references['order_id'] ?? null,
                'user_id' => $references['user_id'] ?? null,
                'created_at' => $at ?? now(),
            ]);
        }
    }

    /**
     * Set one size's count directly (owner edit), logging the difference as an adjustment.
     * Null stops counting that size.
     */
    public function setVariantOnHand(ItemVariant $variant, ?float $onHand, ?int $userId): void
    {
        if ($onHand === null) {
            $variant->forceFill(['on_hand' => null])->save();

            return;
        }

        $difference = $onHand - (float) ($variant->on_hand ?? 0);

        if ($variant->on_hand === null) {
            $variant->forceFill(['on_hand' => 0])->save();
        }

        $this->applyToVariants($variant->item->business, [$variant->id => $difference], StockMovementReason::Adjustment, ['user_id' => $userId]);
    }

    /**
     * Set an item's count directly (owner edit), logging the difference as an adjustment.
     */
    public function setOnHand(Item $item, ?float $onHand, ?int $userId): void
    {
        if ($onHand === null) {
            $item->forceFill(['on_hand' => null])->save();

            return;
        }

        $difference = $onHand - (float) ($item->on_hand ?? 0);

        if ($item->on_hand === null) {
            $item->forceFill(['on_hand' => 0])->save();
        }

        $this->apply($item->business, [$item->id => $difference], StockMovementReason::Adjustment, ['user_id' => $userId]);
    }
}
