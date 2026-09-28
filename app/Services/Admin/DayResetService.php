<?php

namespace App\Services\Admin;

use App\Models\Audit;
use App\Models\Business;
use App\Models\Expense;
use App\Models\Item;
use App\Models\Order;
use App\Models\StockMovement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Undo a whole business day: something went wrong, so the owner starts it over.
 *
 * Deletes today's orders, puts back the stock every one of today's stock movements
 * moved (sales, voids, restocks, adjustments, the closing audit), deletes today's
 * closing audit, and deletes today's expenses. Recipe links, prices and every other
 * day are never touched.
 */
class DayResetService
{
    /**
     * @return array{orders: int, items: int, audit: bool, expenses: int}
     */
    public function resetToday(Business $business): array
    {
        return DB::transaction(function () use ($business): array {
            $locked = Business::whereKey($business->id)->lockForUpdate()->firstOrFail();

            $orderIds = Order::withoutGlobalScopes()
                ->where('business_id', $locked->id)
                ->whereBetween('paid_at', [today(), today()->endOfDay()])
                ->pluck('id');

            $movements = StockMovement::withoutGlobalScopes()
                ->where('business_id', $locked->id)
                ->whereDate('created_at', today())
                ->get();

            $itemsRestored = $this->reverseStock($locked, $movements);

            $orderCount = $orderIds->count();
            Order::withoutGlobalScopes()->whereKey($orderIds)->delete();

            $audit = Audit::withoutGlobalScopes()->where('business_id', $locked->id)->whereDate('date', today())->first();
            $audit?->delete();

            $expenses = Expense::withoutGlobalScopes()->where('business_id', $locked->id)->whereDate('date', today())->get();

            foreach ($expenses as $expense) {
                if ($expense->asset_id !== null) {
                    $expense->asset?->decrement('paid_count');
                }
            }

            Expense::withoutGlobalScopes()->whereKey($expenses->pluck('id'))->delete();

            // A running total across the shop's whole history, not reset daily: after
            // deleting today's orders the next sale must resume where yesterday left off.
            $locked->forceFill([
                'last_order_number' => (int) (Order::withoutGlobalScopes()->where('business_id', $locked->id)->max('number') ?? 0),
            ])->save();

            return [
                'orders' => $orderCount,
                'items' => $itemsRestored,
                'audit' => $audit !== null,
                'expenses' => $expenses->count(),
            ];
        });
    }

    /**
     * Put back exactly what today's movements moved, then remove them. `on_hand` is
     * nothing but a running total of every movement ever applied, so subtracting
     * today's net change per item restores it to what it was before today, no
     * matter how many movements or reasons there were.
     *
     * @param  Collection<int, StockMovement>  $movements
     * @return int items whose stock was restored
     */
    private function reverseStock(Business $business, Collection $movements): int
    {
        if ($movements->isEmpty()) {
            return 0;
        }

        $netChangeByItem = $movements->groupBy('item_id')->map(
            fn (Collection $group) => $group->sum(fn (StockMovement $movement) => (float) $movement->qty_change)
        );

        $items = Item::withoutGlobalScopes()
            ->where('business_id', $business->id)
            ->whereKey($netChangeByItem->keys())
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $restored = 0;

        foreach ($netChangeByItem as $itemId => $netChange) {
            $item = $items->get($itemId);

            if ($item !== null && $item->tracksStock()) {
                $item->forceFill(['on_hand' => round((float) $item->on_hand - $netChange, 3)])->save();
                $restored++;
            }
        }

        StockMovement::withoutGlobalScopes()->whereKey($movements->pluck('id'))->delete();

        return $restored;
    }
}
