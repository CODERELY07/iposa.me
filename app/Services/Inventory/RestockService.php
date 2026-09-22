<?php

namespace App\Services\Inventory;

use App\Enums\ExpenseCategory;
use App\Enums\StockMovementReason;
use App\Models\Item;
use App\Models\ItemContainer;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Stock bought and put on the shelf: "1 tin of oil, paid ₱2,340".
 *
 * The count goes up by what was bought (logged as a Restock), the cost per unit
 * becomes what this purchase cost (the latest price wins), and the money can be
 * logged as a stock-purchase expense in the same step.
 */
class RestockService
{
    public function __construct(private StockService $stock) {}

    /**
     * @return array{added: float, unit_cost: ?float, expense_logged: bool}
     */
    public function restock(Item $item, User $user, float $quantity, ?ItemContainer $container, ?float $paid, bool $logExpense): array
    {
        return DB::transaction(function () use ($item, $user, $quantity, $container, $paid, $logExpense): array {
            $added = round($container !== null ? $quantity * (float) $container->size : $quantity, 3);
            $unitCost = null;

            if (! $item->tracksStock()) {
                $item->forceFill(['on_hand' => 0])->save();
            }

            $this->stock->apply($item->business, [$item->id => $added], StockMovementReason::Restock, ['user_id' => $user->id]);

            if ($paid !== null && $paid > 0 && $added > 0) {
                $unitCost = round($paid / $added, 6);
                $item->forceFill(['unit_cost' => $unitCost])->save();

                // Next time, the form and the restock screen start from this price.
                $container?->update(['price' => round($paid / $quantity, 2)]);
            }

            $expenseLogged = false;

            if ($logExpense && $paid !== null && $paid > 0 && $item->business->hasFeature('expenses')) {
                $item->business->expenses()->create([
                    'date' => today(),
                    'category' => ExpenseCategory::StockPurchase,
                    'kind' => ExpenseCategory::StockPurchase->defaultKind(),
                    'description' => $this->describe($item, $quantity, $container, $added),
                    'amount' => round($paid, 2),
                    'user_id' => $user->id,
                    'logged_by' => $user->name,
                ]);
                $expenseLogged = true;
            }

            return ['added' => $added, 'unit_cost' => $unitCost, 'expense_logged' => $expenseLogged];
        });
    }

    /**
     * "Cooking oil · 1 tin (18,000 ml)" or "Burger buns · 50 pc".
     */
    private function describe(Item $item, float $quantity, ?ItemContainer $container, float $added): string
    {
        if ($container === null) {
            return trim("{$item->name} · ".Item::trimNumber($quantity).' '.($item->unit ?? ''));
        }

        $count = Item::trimNumber($quantity).' '.str($container->label)->plural($quantity);

        return "{$item->name} · {$count} (".Item::trimNumber($added).' '.$item->unit.')';
    }
}
