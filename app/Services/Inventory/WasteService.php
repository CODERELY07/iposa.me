<?php

namespace App\Services\Inventory;

use App\Enums\StockMovementReason;
use App\Models\Item;
use App\Models\ItemContainer;
use App\Models\ItemVariant;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Stock that was spilled, spoiled or thrown away. It only lowers the count and says why;
 * it is not an expense, and the closing audit starts from the lower count, so known waste
 * never shows up as a mystery shortage.
 */
class WasteService
{
    public function __construct(private StockService $stock) {}

    /**
     * @return float how much came off the shelf, in the item's unit
     *
     * @throws ValidationException
     */
    public function log(Item $item, User $user, float $quantity, ?ItemContainer $container, ?string $note, ?CarbonInterface $at = null, ?ItemVariant $variant = null): float
    {
        $removed = round($container !== null ? $quantity * (float) $container->size : $quantity, 3);
        $note = filled($note) ? mb_substr(trim((string) $note), 0, 160) : null;
        $references = ['user_id' => $user->id, 'note' => $note];

        return DB::transaction(function () use ($item, $variant, $removed, $references, $at): float {
            if ($variant !== null) {
                if (! $variant->tracksStock()) {
                    throw ValidationException::withMessages(['quantity' => "{$item->name} ({$variant->label}) isn't counted, so there is no stock to take waste off."]);
                }

                $this->stock->applyToVariants($item->business, [$variant->id => -$removed], StockMovementReason::Waste, $references, $at);

                return $removed;
            }

            if (! $item->tracksStock()) {
                throw ValidationException::withMessages(['quantity' => "{$item->name} isn't counted, so there is no stock to take waste off."]);
            }

            $this->stock->apply($item->business, [$item->id => -$removed], StockMovementReason::Waste, $references, $at);

            return $removed;
        });
    }
}
