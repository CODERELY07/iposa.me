<?php

namespace App\Reports;

use App\Models\Business;
use App\Models\Item;
use App\Models\ItemPurchase;
use App\Models\RecipeLine;
use Illuminate\Support\Collection;

/**
 * What an ingredient has cost over time, from whom, and which menu items feel it.
 * Read-only: a sale keeps the cost it was rung up at, whatever happens to prices later.
 */
class PriceHistory
{
    /**
     * @return array{
     *     purchases: Collection<int, array{purchase: ItemPurchase, change: ?float}>,
     *     suppliers: Collection<int, array{name: string, unit_cost: float, bought_on: string, cheapest: bool}>,
     *     affected: Collection<int, array{name: string, size: ?string, change: float}>,
     *     from: ?float,
     *     to: ?float
     * }
     */
    public function forItem(Item $item, int $limit = 20): array
    {
        $purchases = ItemPurchase::withoutGlobalScopes()
            ->where('item_id', $item->id)
            ->with(['supplier' => fn ($query) => $query->withoutGlobalScopes()])
            ->orderByDesc('bought_on')
            ->orderByDesc('id')
            ->limit($limit + 1)
            ->get();

        $rows = $purchases->take($limit)->values()->map(function (ItemPurchase $purchase, int $index) use ($purchases): array {
            $before = $purchases->get($index + 1);

            return [
                'purchase' => $purchase,
                'change' => $before !== null && (float) $before->unit_cost > 0
                    ? round(((float) $purchase->unit_cost - (float) $before->unit_cost) / (float) $before->unit_cost * 100, 1)
                    : null,
            ];
        });

        $latestBySupplier = $purchases->whereNotNull('supplier_id')->groupBy('supplier_id')->map(fn (Collection $group) => $group->first());
        $cheapest = $latestBySupplier->count() > 1 ? $latestBySupplier->min(fn (ItemPurchase $purchase) => (float) $purchase->unit_cost) : null;

        $suppliers = $latestBySupplier
            ->sortBy(fn (ItemPurchase $purchase) => (float) $purchase->unit_cost)
            ->map(fn (ItemPurchase $purchase): array => [
                'name' => $purchase->supplier->name,
                'unit_cost' => (float) $purchase->unit_cost,
                'bought_on' => $purchase->bought_on->format('M j'),
                'cheapest' => $cheapest !== null && (float) $purchase->unit_cost === (float) $cheapest,
            ])
            ->values();

        $latest = $purchases->first();
        $previous = $purchases->get(1);
        $from = $previous !== null ? (float) $previous->unit_cost : null;
        $to = $latest !== null ? (float) $latest->unit_cost : null;

        return [
            'purchases' => $rows,
            'suppliers' => $suppliers,
            'affected' => $from !== null && $to !== null && $from !== $to ? $this->affectedMenu($item, $from, $to) : collect(),
            'from' => $from,
            'to' => $to,
        ];
    }

    /**
     * Items whose latest purchase price rose by at least $minPercent against the one before,
     * bought within the last $days, biggest rise first.
     *
     * @return Collection<int, array{item: Item, from: float, to: float, percent: float, affected: int}>
     */
    public function recentIncreases(Business $business, int $days = 60, float $minPercent = 5.0, int $limit = 5): Collection
    {
        $recentItemIds = ItemPurchase::withoutGlobalScopes()
            ->where('business_id', $business->id)
            ->whereDate('bought_on', '>=', today()->subDays($days)->toDateString())
            ->distinct()
            ->pluck('item_id');

        if ($recentItemIds->isEmpty()) {
            return collect();
        }

        $items = Item::withoutGlobalScopes()->where('business_id', $business->id)->whereNull('archived_at')->whereKey($recentItemIds)->get()->keyBy('id');

        return ItemPurchase::withoutGlobalScopes()
            ->where('business_id', $business->id)
            ->whereIn('item_id', $items->keys())
            ->orderByDesc('bought_on')
            ->orderByDesc('id')
            ->get()
            ->groupBy('item_id')
            ->map(function (Collection $purchases, int $itemId) use ($items, $days, $minPercent): ?array {
                $latest = $purchases->first();
                $previous = $purchases->get(1);

                if ($previous === null || (float) $previous->unit_cost <= 0 || $latest->bought_on->lt(today()->subDays($days))) {
                    return null;
                }

                $percent = round(((float) $latest->unit_cost - (float) $previous->unit_cost) / (float) $previous->unit_cost * 100, 1);

                if ($percent < $minPercent) {
                    return null;
                }

                $item = $items[$itemId];

                return [
                    'item' => $item,
                    'from' => (float) $previous->unit_cost,
                    'to' => (float) $latest->unit_cost,
                    'percent' => $percent,
                    'affected' => $this->affectedMenu($item, (float) $previous->unit_cost, (float) $latest->unit_cost)->pluck('name')->unique()->count(),
                ];
            })
            ->filter()
            ->sortByDesc('percent')
            ->take($limit)
            ->values();
    }

    /**
     * The menu items whose cost includes this ingredient, and what one sale of each now costs
     * more (or less) because of the price change. Items that don't count their links in cost
     * (manual cost only) are left out: their cost doesn't move.
     *
     * @return Collection<int, array{name: string, size: ?string, change: float}>
     */
    private function affectedMenu(Item $piece, float $from, float $to): Collection
    {
        return RecipeLine::query()
            ->where('piece_item_id', $piece->id)
            ->with(['item' => fn ($query) => $query->withoutGlobalScopes(), 'variant'])
            ->get()
            ->filter(fn (RecipeLine $line) => $line->item !== null && $line->item->archived_at === null && $line->item->costing_method->usesLinked())
            ->map(fn (RecipeLine $line): array => [
                'name' => $line->item->name,
                'size' => $line->variant?->label,
                'change' => round((float) $line->qty * ($to - $from), 2),
            ])
            ->values();
    }
}
