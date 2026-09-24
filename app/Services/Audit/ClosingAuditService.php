<?php

namespace App\Services\Audit;

use App\Enums\ItemKind;
use App\Enums\StockMovementReason;
use App\Models\Audit;
use App\Models\Business;
use App\Models\Item;
use App\Models\RecipeLine;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Inventory\StockService;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClosingAuditService
{
    public function __construct(private StockService $stock) {}

    /**
     * Today's audit for this business, if one was submitted.
     */
    public function forDate(Business $business, CarbonInterface $date): ?Audit
    {
        return Audit::withoutGlobalScopes()
            ->where('business_id', $business->id)
            ->whereDate('date', $date->toDateString())
            ->with('lines')
            ->first();
    }

    /**
     * Save the shelf counts for a day. The first submit records "expected" from the system;
     * a correction by the owner keeps the original expected and only moves stock by the difference.
     *
     * A count above expected is a restock, unless the item is used in recipes and
     * the counter says the recipes deduct more than the kitchen uses ("recipe").
     * Recipes can only have over-deducted what they deducted since the last count,
     * so anything above that is still a restock.
     *
     * @param  array<int, float>  $counts  item id => counted
     * @param  array<int, string>  $surplusReasons  item id => "restock" | "recipe"
     *
     * @throws ValidationException
     */
    public function submit(Business $business, User $user, array $counts, ?CarbonInterface $startedAt = null, ?CarbonInterface $date = null, ?CarbonInterface $submittedAt = null, array $surplusReasons = []): Audit
    {
        $date ??= now();
        $submittedAt ??= now();

        return DB::transaction(function () use ($business, $user, $counts, $startedAt, $date, $submittedAt, $surplusReasons): Audit {
            $items = Item::withoutGlobalScopes()
                ->where('business_id', $business->id)
                ->whereIn('kind', self::countedKinds($business))
                ->whereNull('archived_at')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $missing = $items->keys()->diff(array_keys($counts));

            if ($missing->isNotEmpty() || count($counts) !== $items->count()) {
                throw ValidationException::withMessages([
                    'counts' => 'Count every item on the list before closing the day. Refresh the page if the list changed.',
                ]);
            }

            $audit = $this->forDate($business, $date);
            $isCorrection = $audit !== null;

            $audit ??= Audit::withoutGlobalScopes()->create([
                'business_id' => $business->id,
                'date' => $date->toDateString(),
                'user_id' => $user->id,
                'counted_by' => $user->name,
                'started_at' => $startedAt,
                'submitted_at' => $submittedAt,
                'duration_seconds' => $startedAt ? max(0, (int) $startedAt->diffInSeconds($submittedAt)) : null,
            ]);

            if ($isCorrection) {
                $audit->update(['submitted_at' => $submittedAt, 'user_id' => $user->id, 'counted_by' => $user->name]);
            }

            $changes = [];
            $inRecipes = RecipeLine::query()->whereIn('piece_item_id', $items->keys())->distinct()->pluck('piece_item_id')->flip();

            foreach ($items as $item) {
                $counted = round((float) $counts[$item->id], 3);
                $line = $audit->lines->firstWhere('item_id', $item->id);
                $expected = $line !== null ? (float) $line->expected : (float) ($item->on_hand ?? 0);
                $previouslyCounted = $line !== null ? (float) $line->counted : $expected;

                // A correction keeps the window of the first count.
                [$deducted, $deductedCosted] = $line !== null
                    ? [(float) $line->recipe_deducted, (float) $line->recipe_deducted_costed]
                    : $this->recipeDeductionsSinceLastCount($item, $audit);

                $surplus = max(0, round($counted - $expected, 3));
                $recipeSurplus = $surplus > 0 && $inRecipes->has($item->id) && ($surplusReasons[$item->id] ?? null) === 'recipe'
                    ? min($surplus, $deducted)
                    : 0.0;

                $audit->lines()->updateOrCreate(['item_id' => $item->id], [
                    'expected' => $expected,
                    'counted' => $counted,
                    'used' => max(0, round($expected - $counted, 3)),
                    'restocked' => round($surplus - $recipeSurplus, 3),
                    'recipe_surplus' => $recipeSurplus,
                    'recipe_deducted' => $deducted,
                    'recipe_deducted_costed' => $deductedCosted,
                    // Give back only what sales had charged: the share of the deductions that was costed.
                    'recipe_surplus_costed' => $deducted > 0 ? round($recipeSurplus * $deductedCosted / $deducted, 3) : 0,
                    'recipe_fix' => $recipeSurplus > 0 ? $line?->recipe_fix : null,
                    'recipe_fix_at' => $recipeSurplus > 0 ? $line?->recipe_fix_at : null,
                    'unit_cost' => $line?->unit_cost ?? ($item->unit_cost ?? 0),
                ]);

                if ($item->on_hand === null) {
                    $item->forceFill(['on_hand' => 0])->save();
                    $previouslyCounted = $line !== null ? $previouslyCounted : 0;
                }

                $changes[$item->id] = $counted - $previouslyCounted;
            }

            $this->stock->apply($business, $changes, StockMovementReason::Audit, [
                'audit_id' => $audit->id,
                'user_id' => $user->id,
            ], $submittedAt);

            // The first submit is when the shelf was counted; a correction doesn't move it.
            if ($audit->last_movement_id === null) {
                $audit->forceFill(['last_movement_id' => (int) StockMovement::withoutGlobalScopes()->where('business_id', $business->id)->max('id')])->save();
            }

            return $audit->refresh()->load('lines');
        });
    }

    /**
     * Item kinds the closing audit counts: bulk and liquids always, pieces when the shop asks.
     *
     * @return list<ItemKind>
     */
    public static function countedKinds(Business $business): array
    {
        return $business->auditsPieces() ? [ItemKind::Bulk, ItemKind::Piece] : [ItemKind::Bulk];
    }

    /**
     * What sales took off this item through recipes since its last count (voids given
     * back), and the part of that whose cost the sales charged.
     *
     * @return array{0: float, 1: float}
     */
    private function recipeDeductionsSinceLastCount(Item $item, Audit $current): array
    {
        $previous = Audit::withoutGlobalScopes()
            ->where('business_id', $item->business_id)
            ->whereKeyNot($current->id)
            ->whereHas('lines', fn ($query) => $query->where('item_id', $item->id))
            ->latest('id')
            ->first();

        // Counts from before counts kept their place fall back to the item's last count movement.
        $lastCountId = $previous?->last_movement_id ?? ($previous === null ? null : StockMovement::withoutGlobalScopes()
            ->where('item_id', $item->id)
            ->where('reason', StockMovementReason::Audit)
            ->max('id'));

        $totals = StockMovement::withoutGlobalScopes()
            ->where('item_id', $item->id)
            ->whereIn('reason', [StockMovementReason::Sale, StockMovementReason::Void])
            ->when($lastCountId !== null, fn ($query) => $query->where('id', '>', $lastCountId))
            ->selectRaw('coalesce(-sum(qty_change), 0) as deducted, coalesce(-sum(costed_qty), 0) as costed')
            ->first();

        $deducted = max(0, round((float) $totals->deducted, 3));

        return [$deducted, min($deducted, max(0, round((float) $totals->costed, 3)))];
    }
}
