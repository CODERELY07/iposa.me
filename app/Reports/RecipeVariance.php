<?php

namespace App\Reports;

use App\Enums\ItemKind;
use App\Enums\StockMovementReason;
use App\Models\Audit;
use App\Models\Business;
use App\Models\Item;
use App\Models\RecipeLine;
use App\Models\StockMovement;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * For liquids (and pieces, when counted) that are both in recipes and counted at closing: what the recipes
 * say the sales used, against what the shelf count says really went.
 *
 * The audit only charges what the recipes don't explain (its "expected" is the
 * stock after sales took their share), so the two never double-count. The gap is
 * the useful part: waste, bigger portions, free extras, or a recipe set too high.
 */
class RecipeVariance
{
    /**
     * The most recent closing audit's comparison, or null when there is nothing to compare.
     *
     * @return array{date: CarbonInterface, rows: Collection<int, array{name: string, unit: string, recipe: float, extra: float, extra_cost: float, over_deducted: float, total: float, verdict: string}>}|null
     */
    public function latest(Business $business): ?array
    {
        $audit = Audit::withoutGlobalScopes()
            ->where('business_id', $business->id)
            ->latest('date')
            ->with('lines')
            ->first();

        if ($audit === null) {
            return null;
        }

        $liquidIds = RecipeLine::query()
            ->whereIn('piece_item_id', Item::withoutGlobalScopes()->where('business_id', $business->id)->whereIn('kind', [ItemKind::Bulk, ItemKind::Piece])->select('id'))
            ->distinct()
            ->pluck('piece_item_id');

        if ($liquidIds->isEmpty()) {
            return null;
        }

        $items = Item::withoutGlobalScopes()->whereKey($liquidIds)->get()->keyBy('id');

        $recipeUse = StockMovement::withoutGlobalScopes()
            ->where('business_id', $business->id)
            ->whereIn('item_id', $liquidIds)
            ->where('reason', StockMovementReason::Sale)
            ->whereDate('created_at', $audit->date->toDateString())
            ->selectRaw('item_id, -sum(qty_change) as used')
            ->groupBy('item_id')
            ->pluck('used', 'item_id');

        $rows = $audit->lines
            ->filter(fn ($line) => $items->has($line->item_id))
            ->map(function ($line) use ($items, $recipeUse): array {
                $item = $items->get($line->item_id);
                // Counts since this migration know exactly what recipes took since the previous count.
                $recipe = round((float) $line->recipe_deducted > 0 ? (float) $line->recipe_deducted : (float) ($recipeUse[$line->item_id] ?? 0), 3);
                $extra = (float) $line->used;
                $overDeducted = (float) $line->recipe_surplus;

                return [
                    'name' => $item->name,
                    'unit' => $item->unit ?? '',
                    'recipe' => $recipe,
                    'extra' => $extra,
                    'extra_cost' => round($extra * (float) $line->unit_cost, 2),
                    'over_deducted' => $overDeducted,
                    'total' => round($recipe + $extra - $overDeducted, 3),
                    'verdict' => match (true) {
                        $overDeducted > 0 => 'Recipes deduct more than the kitchen uses. See the suggestion above.',
                        $recipe <= 0 && $extra > 0 => 'Used without any recipe sales since the last count.',
                        $recipe > 0 && $extra > $recipe * 0.25 => 'More than the recipes explain: waste, bigger portions or free extras?',
                        default => 'Close to what the recipes say.',
                    },
                ];
            })
            ->filter(fn (array $row) => $row['recipe'] > 0 || $row['extra'] > 0 || $row['over_deducted'] > 0)
            ->values();

        return $rows->isEmpty() ? null : ['date' => $audit->date, 'rows' => $rows];
    }
}
