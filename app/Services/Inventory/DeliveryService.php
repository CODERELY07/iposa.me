<?php

namespace App\Services\Inventory;

use App\Enums\ExpenseCategory;
use App\Enums\StockMovementReason;
use App\Models\AuditLine;
use App\Models\Delivery;
use App\Models\Item;
use App\Models\ItemContainer;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cashier restocks, checked by the owner against the supplier's receipt.
 *
 * The shelf already holds what the cashier recorded. Checking it settles the rest:
 * a receipt for more than was recorded is stock that never reached the shelf (a loss
 * at the purchase price), a receipt for less means the count was too high and is
 * corrected, and the price paid sets the cost and can be logged.
 */
class DeliveryService
{
    public function __construct(private StockService $stock) {}

    public function record(Item $item, User $cashier, float $quantity, ?ItemContainer $container, float $added): Delivery
    {
        return Delivery::withoutGlobalScopes()->create([
            'business_id' => $item->business_id,
            'item_id' => $item->id,
            'user_id' => $cashier->id,
            'received_by' => $cashier->name,
            'quantity' => $quantity,
            'item_container_id' => $container?->id,
            'container_label' => $container?->label,
            'container_size' => $container?->size,
            'added' => $added,
            'status' => Delivery::PENDING,
        ]);
    }

    /**
     * @param  float  $receiptQuantity  in the unit the cashier used (containers or the item's unit)
     * @return array{shortage: float, overcount: float, corrected_count: ?CarbonInterface, missing_cost: float, expense_logged: bool}
     *
     * @throws ValidationException
     */
    public function check(Delivery $delivery, User $owner, float $receiptQuantity, ?float $paid, bool $logExpense): array
    {
        return DB::transaction(function () use ($delivery, $owner, $receiptQuantity, $paid, $logExpense): array {
            $delivery = Delivery::withoutGlobalScopes()->whereKey($delivery->id)->lockForUpdate()->firstOrFail();

            if (! $delivery->isPending()) {
                throw ValidationException::withMessages(['delivery' => 'This delivery was already checked.']);
            }

            $item = Item::withoutGlobalScopes()->whereKey($delivery->item_id)->firstOrFail();
            $business = $item->business;
            $size = $delivery->container_size !== null ? (float) $delivery->container_size : 1.0;
            $receiptAdded = round($receiptQuantity * $size, 3);
            $recorded = (float) $delivery->added;
            $shortage = max(0.0, round($receiptAdded - $recorded, 3));
            $overcount = max(0.0, round($recorded - $receiptAdded, 3));

            // More on the shelf count than the receipt: the count was wrong, bring it down.
            // If a closing count happened since, it already brought the shelf down and charged
            // the missing amount as used; take that phantom usage back out of the count instead.
            $countedSince = $overcount > 0 ? $this->firstCountSince($delivery) : null;

            if ($countedSince !== null) {
                $countedSince->update(['used' => round(max(0.0, (float) $countedSince->used - $overcount), 3)]);
            } elseif ($overcount > 0) {
                $this->stock->apply($business, [$item->id => -$overcount], StockMovementReason::Adjustment, ['user_id' => $owner->id]);
            }

            if ($paid !== null && $paid > 0 && $receiptAdded > 0) {
                $item->forceFill(['unit_cost' => round($paid / $receiptAdded, 6)])->save();

                if ($delivery->item_container_id !== null && $receiptQuantity > 0) {
                    $item->containers()->whereKey($delivery->item_container_id)->update(['price' => round($paid / $receiptQuantity, 2)]);
                }
            }

            $isStock = $item->isCostedWhenUsed($business);
            $missingCost = round($shortage * (float) ($item->unit_cost ?? 0), 2);
            $description = $this->describe($item, $delivery, $receiptQuantity);
            $expenseLogged = false;

            if ($logExpense && $paid !== null && $paid > 0 && $business->hasFeature('expenses')) {
                $category = $isStock ? ExpenseCategory::StockPurchase : ExpenseCategory::Supplies;
                $business->expenses()->create([
                    'date' => today(),
                    'category' => $category,
                    'kind' => $category->defaultKind(),
                    'description' => $description,
                    'amount' => round($paid, 2),
                    'user_id' => $owner->id,
                    'logged_by' => $owner->name,
                ]);
                $expenseLogged = true;
            }

            // Stock bought but never shelved lowers profit now: it will never be used or sold.
            // A supply's purchase is already an expense, so its shortage is inside that amount.
            if ($missingCost > 0 && $isStock) {
                $business->expenses()->create([
                    'date' => today(),
                    'category' => ExpenseCategory::MissingStock,
                    'kind' => ExpenseCategory::MissingStock->defaultKind(),
                    'description' => "{$item->name} · ".Item::trimNumber($shortage).' '.($item->unit ?: 'pc')." short (received by {$delivery->received_by})",
                    'amount' => $missingCost,
                    'user_id' => $owner->id,
                    'logged_by' => $owner->name,
                ]);
            }

            $delivery->update([
                'status' => Delivery::CHECKED,
                'receipt_added' => $receiptAdded,
                'paid' => $paid,
                'missing_cost' => $isStock ? $missingCost : 0,
                'checked_by' => $owner->id,
                'checked_by_name' => $owner->name,
                'checked_at' => now(),
            ]);

            return ['shortage' => $shortage, 'overcount' => $overcount, 'corrected_count' => $countedSince?->audit->date, 'missing_cost' => $isStock ? $missingCost : 0.0, 'expense_logged' => $expenseLogged];
        });
    }

    /**
     * The first closing count of this item after the delivery was recorded, if any.
     */
    private function firstCountSince(Delivery $delivery): ?AuditLine
    {
        return AuditLine::query()
            ->where('item_id', $delivery->item_id)
            ->whereHas('audit', fn ($query) => $query->withoutGlobalScopes()
                ->where('business_id', $delivery->business_id)
                ->where('created_at', '>=', $delivery->created_at))
            ->with(['audit' => fn ($query) => $query->withoutGlobalScopes()])
            ->orderBy('id')
            ->first();
    }

    private function describe(Item $item, Delivery $delivery, float $receiptQuantity): string
    {
        $amount = $delivery->container_label !== null
            ? Item::trimNumber($receiptQuantity).' '.str($delivery->container_label)->plural($receiptQuantity)
            : trim(Item::trimNumber($receiptQuantity).' '.($item->unit ?: 'pc'));

        return "{$item->name} · {$amount} (delivery received by {$delivery->received_by})";
    }
}
