<?php

namespace App\Services\Audit;

use App\Models\AuditLine;
use App\Models\Business;
use App\Models\Item;
use App\Models\RecipeLine;
use App\Models\User;
use App\Services\Inventory\RecipeChangeService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * "Recipes use less than set" at closing: offer to lower every recipe that uses the
 * item by the share the count found, so future sales deduct and cost the real amount.
 */
class RecipeFixService
{
    public function __construct(private RecipeChangeService $recipeChanges) {}

    /**
     * Open suggestions: the newest over-deducting count per item, from the last 30 days.
     *
     * @return Collection<int, array{line: AuditLine, item: Item, deducted: float, used: float, share: float, recipes: int}>
     */
    public function suggestions(Business $business): Collection
    {
        $lines = AuditLine::query()
            ->whereHas('audit', fn ($query) => $query->withoutGlobalScopes()->where('business_id', $business->id)->where('date', '>=', today()->subDays(30)))
            ->with(['audit', 'item' => fn ($query) => $query->withoutGlobalScopes()])
            ->where('recipe_surplus', '>', 0)
            ->where('recipe_deducted', '>', 0)
            ->orderByDesc('id')
            ->get()
            ->unique('item_id');

        $newestLineIds = AuditLine::query()
            ->whereIn('item_id', $lines->pluck('item_id'))
            ->selectRaw('item_id, max(id) as id')
            ->groupBy('item_id')
            ->pluck('id', 'item_id');

        return $lines
            ->filter(fn (AuditLine $line) => $line->recipe_fix === null
                && $line->item !== null
                && $line->item->archived_at === null
                && (int) $newestLineIds[$line->item_id] === $line->id)
            ->map(function (AuditLine $line): array {
                $deducted = (float) $line->recipe_deducted;
                $used = max(0.0, $deducted - (float) $line->recipe_surplus);

                return [
                    'line' => $line,
                    'item' => $line->item,
                    'deducted' => $deducted,
                    'used' => round($used, 3),
                    'share' => round($used / $deducted, 4),
                    'recipes' => RecipeLine::query()->where('piece_item_id', $line->item_id)->distinct()->count('item_id'),
                ];
            })
            ->values();
    }

    /**
     * Lower every recipe that uses the item by the share really used. Each menu item's
     * change goes into its link history.
     *
     * @throws ValidationException
     */
    public function apply(AuditLine $line, User $owner): int
    {
        return DB::transaction(function () use ($line, $owner): int {
            $suggestion = $this->suggestions($owner->business)->firstWhere('line.id', $line->id);

            if ($suggestion === null) {
                throw ValidationException::withMessages(['recipe' => 'This suggestion is out of date. A newer count replaced it.']);
            }

            if ($suggestion['share'] <= 0) {
                throw ValidationException::withMessages(['recipe' => "The count says none of the {$suggestion['item']->name} was used. Check those recipes by hand."]);
            }

            $recipeLines = RecipeLine::query()->where('piece_item_id', $line->item_id)->with('item')->get();

            foreach ($recipeLines->groupBy('item_id') as $lines) {
                $menuItem = $lines->first()->item;
                $before = $this->recipeChanges->snapshot($menuItem);

                foreach ($lines as $recipeLine) {
                    $recipeLine->update(['qty' => max(0.001, round((float) $recipeLine->qty * $suggestion['share'], 3))]);
                }

                $this->recipeChanges->recordSaved($menuItem, $owner, $before);
            }

            $line->update(['recipe_fix' => 'applied', 'recipe_fix_at' => now()]);

            return $recipeLines->pluck('item_id')->unique()->count();
        });
    }

    public function dismiss(AuditLine $line): void
    {
        $line->update(['recipe_fix' => 'dismissed', 'recipe_fix_at' => now()]);
    }
}
