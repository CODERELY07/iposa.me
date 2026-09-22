<?php

namespace App\Services\Audit;

use App\Enums\ItemKind;
use App\Enums\StockMovementReason;
use App\Models\Audit;
use App\Models\Business;
use App\Models\Item;
use App\Models\RecipeLine;
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
                ->where('kind', ItemKind::Bulk)
                ->whereNull('archived_at')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $missing = $items->keys()->diff(array_keys($counts));

            if ($missing->isNotEmpty() || count($counts) !== $items->count()) {
                throw ValidationException::withMessages([
                    'counts' => 'Count every bulk item before closing the day. Refresh the page if the list changed.',
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

                $surplus = max(0, round($counted - $expected, 3));
                $recipeOverDeducted = $surplus > 0 && $inRecipes->has($item->id) && ($surplusReasons[$item->id] ?? null) === 'recipe';

                $audit->lines()->updateOrCreate(['item_id' => $item->id], [
                    'expected' => $expected,
                    'counted' => $counted,
                    'used' => max(0, round($expected - $counted, 3)),
                    'restocked' => $recipeOverDeducted ? 0 : $surplus,
                    'recipe_surplus' => $recipeOverDeducted ? $surplus : 0,
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

            return $audit->refresh()->load('lines');
        });
    }
}
