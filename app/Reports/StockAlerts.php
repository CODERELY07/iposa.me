<?php

namespace App\Reports;

use App\Enums\ItemKind;
use App\Enums\StockMovementReason;
use App\Models\Business;
use App\Models\Item;
use App\Models\ItemVariant;
use App\Models\StockMovement;
use Illuminate\Support\Collection;

/**
 * What is running low, with a rough "runs out" estimate from the last 7 days of use.
 * Scoped to the business it is given, so the dashboard and the scheduled SMS agree
 * and the SMS never depends on who is logged in.
 */
class StockAlerts
{
    /**
     * Tracked items and sizes at or below their alert level, most urgent first.
     *
     * @return Collection<int, array{name: string, left: string, runsOut: ?string}>
     */
    public function low(Business $business, int $limit = 5): Collection
    {
        $items = Item::withoutGlobalScopes()->where('business_id', $business->id)->active()
            ->whereNotNull('on_hand')->orderBy('on_hand')->get()
            ->filter(fn (Item $item) => $item->isLowStock($business))
            ->take($limit);

        // Sizes that keep their own count (bottled water 500ml vs 1L) each have their own alert.
        $sizes = ItemVariant::query()
            ->with(['item' => fn ($query) => $query->withoutGlobalScopes()])
            ->whereNotNull('on_hand')
            ->whereHas('item', fn ($query) => $query->withoutGlobalScopes()->where('business_id', $business->id)->active())
            ->orderBy('on_hand')->get()
            ->filter(fn (ItemVariant $size) => $size->item->isVariantLowStock($size, $business))
            ->take($limit);

        if ($items->isEmpty() && $sizes->isEmpty()) {
            return collect();
        }

        $usage = fn (string $column, $ids) => StockMovement::withoutGlobalScopes()
            ->where('business_id', $business->id)
            ->whereIn($column, $ids)
            ->whereIn('reason', [StockMovementReason::Sale, StockMovementReason::Audit])
            ->where('qty_change', '<', 0)
            ->where('created_at', '>=', today()->subDays(7))
            ->selectRaw("{$column}, -sum(qty_change) / 7 as per_day")
            ->groupBy($column)
            ->pluck('per_day', $column);

        $itemUse = $usage('item_id', $items->pluck('id'));
        $sizeUse = $usage('item_variant_id', $sizes->pluck('id'));

        $runsOut = fn (?float $daysLeft): ?string => match (true) {
            $daysLeft === null => null,
            $daysLeft < 1 => 'today at this pace',
            $daysLeft < 2 => 'tomorrow at this pace',
            default => 'in about '.(int) floor($daysLeft).' days',
        };

        $itemRows = $items->map(function (Item $item) use ($itemUse, $runsOut): array {
            $perDay = (float) ($itemUse[$item->id] ?? 0);

            return [
                'name' => $item->name,
                'left' => rtrim(rtrim(number_format((float) $item->on_hand, 2), '0'), '.').' '.($item->unit ?: ($item->kind === ItemKind::Piece ? 'pcs' : 'left')),
                'runsOut' => $runsOut($perDay > 0 ? max(0, (float) $item->on_hand) / $perDay : null),
            ];
        });

        $sizeRows = $sizes->map(function (ItemVariant $size) use ($sizeUse, $runsOut): array {
            $perDay = (float) ($sizeUse[$size->id] ?? 0);

            return [
                'name' => $size->item->name.' · '.$size->label,
                'left' => rtrim(rtrim(number_format((float) $size->on_hand, 2), '0'), '.').' left',
                'runsOut' => $runsOut($perDay > 0 ? max(0, (float) $size->on_hand) / $perDay : null),
            ];
        });

        return $itemRows->concat($sizeRows)->take($limit)->values();
    }
}
