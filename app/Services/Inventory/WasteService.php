<?php

namespace App\Services\Inventory;

use App\Enums\ItemKind;
use App\Enums\StockMovementReason;
use App\Models\Item;
use App\Models\ItemContainer;
use App\Models\ItemVariant;
use App\Models\StockMovement;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Stock that was spilled, spoiled or thrown away. It takes the stock off the shelf and
 * writes down what it was worth that day (the cost per unit at that moment, locked in
 * like a sale's cost), and that value counts against Net Profit as Waste. It is never
 * cash going out, so it stays out of Money Movement. The closing audit starts from the
 * lower count, so logged waste is not reported again as a shortage.
 */
class WasteService
{
    public function __construct(private StockService $stock) {}

    /**
     * @return array{removed: float, cost: ?float, priced: bool} cost is null when the item has no cost set,
     *                                                           so the loss can't be valued (never a silent 0)
     *
     * @throws ValidationException
     */
    public function log(Item $item, User $user, float $quantity, ?ItemContainer $container, ?string $note, ?CarbonInterface $at = null, ?ItemVariant $variant = null): array
    {
        $removed = round($container !== null ? $quantity * (float) $container->size : $quantity, 3);
        $note = filled($note) ? mb_substr(trim((string) $note), 0, 160) : null;
        $unitCost = $this->unitCost($item, $variant);
        $references = ['user_id' => $user->id, 'note' => $note, 'unit_cost' => $unitCost];

        DB::transaction(function () use ($item, $variant, $removed, $references, $at): void {
            if ($variant !== null) {
                if (! $variant->tracksStock()) {
                    throw ValidationException::withMessages(['quantity' => "{$item->name} ({$variant->label}) isn't counted, so there is no stock to take waste off."]);
                }

                $this->stock->applyToVariants($item->business, [$variant->id => -$removed], StockMovementReason::Waste, $references, $at);

                return;
            }

            if (! $item->tracksStock()) {
                throw ValidationException::withMessages(['quantity' => "{$item->name} isn't counted, so there is no stock to take waste off."]);
            }

            $this->stock->apply($item->business, [$item->id => -$removed], StockMovementReason::Waste, $references, $at);
        });

        return [
            'removed' => $removed,
            'cost' => $unitCost !== null ? round($removed * $unitCost, 2) : null,
            'priced' => $unitCost !== null,
        ];
    }

    /**
     * Cancel a waste entry logged today: the stock goes back and the loss is taken out of
     * profit, on the same day it was booked. Older entries are final, like a voided sale's day.
     *
     * @throws ValidationException
     */
    public function undo(StockMovement $movement, User $user): void
    {
        if ($movement->reason !== StockMovementReason::Waste || (float) $movement->qty_change >= 0) {
            throw ValidationException::withMessages(['waste' => 'Only a waste entry can be undone.']);
        }

        if ($movement->undoneBy()->exists()) {
            throw ValidationException::withMessages(['waste' => 'This waste entry was already undone.']);
        }

        if (! $movement->created_at->isToday()) {
            throw ValidationException::withMessages(['waste' => 'Only waste logged for today can be undone.']);
        }

        DB::transaction(function () use ($movement, $user): void {
            $item = Item::withoutGlobalScopes()->whereKey($movement->item_id)->firstOrFail();
            $back = abs((float) $movement->qty_change);
            $references = [
                'user_id' => $user->id,
                'note' => mb_substr('Undo: '.($movement->note ?: 'waste'), 0, 160),
                'unit_cost' => $movement->unit_cost !== null ? (float) $movement->unit_cost : null,
                'reverses_id' => $movement->id,
            ];

            if ($movement->item_variant_id !== null) {
                $this->stock->applyToVariants($item->business, [$movement->item_variant_id => $back], StockMovementReason::Waste, $references, $movement->created_at);

                return;
            }

            $this->stock->apply($item->business, [$item->id => $back], StockMovementReason::Waste, $references, $movement->created_at);
        });
    }

    /**
     * What one unit was worth: a piece or liquid's cost per unit, or a menu item's own cost for
     * the size that counts itself. Null when that isn't set (or a shared count spans sizes
     * that cost differently), so the waste is shown as unvalued instead of free.
     */
    private function unitCost(Item $item, ?ItemVariant $variant): ?float
    {
        if ($variant !== null) {
            return $variant->cost !== null ? (float) $variant->cost : null;
        }

        if ($item->kind === ItemKind::Menu) {
            $sizes = $item->variants;

            return $sizes->count() === 1 && $sizes->first()->cost !== null ? (float) $sizes->first()->cost : null;
        }

        return $item->unit_cost !== null ? (float) $item->unit_cost : null;
    }
}
