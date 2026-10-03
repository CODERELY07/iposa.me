<?php

namespace App\Services\Inventory;

use App\Models\Item;
use App\Models\ItemPurchase;
use App\Models\Supplier;
use Carbon\CarbonInterface;

/**
 * Remembers what an item cost each time it was bought, and from whom, so a rising price
 * can be seen and traced to the menu items it affects. A record only: nothing here
 * changes a cost, a sale or a profit figure.
 */
class PurchaseRecorder
{
    /**
     * @param  float  $quantity  in the item's own unit
     */
    public function record(Item $item, float $quantity, float $paid, string $source, CarbonInterface $on, ?string $supplierName = null, ?int $userId = null): ?ItemPurchase
    {
        if ($quantity <= 0 || $paid <= 0) {
            return null;
        }

        return ItemPurchase::withoutGlobalScopes()->create([
            'business_id' => $item->business_id,
            'item_id' => $item->id,
            'supplier_id' => $this->supplier($item->business_id, $supplierName)?->id,
            'user_id' => $userId,
            'unit_cost' => round($paid / $quantity, 6),
            'quantity' => round($quantity, 3),
            'paid' => round($paid, 2),
            'source' => $source,
            'bought_on' => $on->toDateString(),
        ]);
    }

    /**
     * Find the supplier by name (ignoring case and spacing) or remember a new one.
     */
    private function supplier(int $businessId, ?string $name): ?Supplier
    {
        $name = trim(preg_replace('/\s+/', ' ', (string) $name) ?? '');

        if ($name === '') {
            return null;
        }

        $existing = Supplier::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->whereRaw('lower(name) = ?', [mb_strtolower($name)])
            ->first();

        return $existing ?? Supplier::withoutGlobalScopes()->create(['business_id' => $businessId, 'name' => mb_substr($name, 0, 80)]);
    }
}
