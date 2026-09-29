<?php

namespace App\Services\Inventory;

use App\Enums\CostingMethod;
use App\Enums\ItemKind;
use App\Models\Business;
use App\Models\Item;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ItemService
{
    public function __construct(private StockService $stock, private RecipeChangeService $recipeChanges) {}

    /**
     * Create or update an item with its sizes and recipe links in one transaction.
     * Leave out `recipe` (and `costing_method`) to keep the item's links as they are.
     *
     * @param  array{
     *     kind: string, name: string, category_id?: int|null, unit?: string|null,
     *     on_hand?: float|string|null, low_threshold?: float|string|null, unit_cost?: float|string|null, costing_method?: string|null,
     *     containers?: list<array{id?: int|null, label: string, size: float|string, price?: float|string|null}>,
     *     variants?: list<array{id?: int|null, label: string, cost?: float|string|null, price: float|string}>,
     *     recipe?: list<array{piece_item_id: int, qty: float|string, variant_index?: int|null, order_type?: string|null}>
     * }  $data
     */
    public function save(Business $business, User $user, array $data, ?Item $item = null): Item
    {
        return DB::transaction(function () use ($business, $user, $data, $item): Item {
            $kind = ItemKind::from($data['kind']);
            $isNew = $item === null;
            $linksBefore = $isNew ? [] : $this->recipeChanges->snapshot($item);
            $item ??= new Item(['business_id' => $business->id]);

            $containerRows = $kind === ItemKind::Menu ? [] : array_values($data['containers'] ?? []);

            $item->fill([
                'kind' => $kind,
                'name' => $data['name'],
                'category_id' => $data['category_id'] ?? null,
                'unit' => $data['unit'] ?? null,
                'low_threshold' => self::nullableNumber($data['low_threshold'] ?? null),
            ]);

            if ($kind !== ItemKind::Menu) {
                $item->costing_method = CostingMethod::ManualOnly;
            } elseif (array_key_exists('costing_method', $data) && $data['costing_method'] !== null) {
                $item->costing_method = CostingMethod::from($data['costing_method']);
            }

            // With containers the cost comes from what the owner paid for one;
            // without, it is typed per unit as before.
            if ($kind === ItemKind::Menu) {
                $item->unit_cost = null;
            } elseif ($containerRows === []) {
                $item->unit_cost = self::nullableCost($data['unit_cost'] ?? null);
            }

            $item->business_id = $business->id;
            $item->save();

            $costFromContainers = $this->syncContainers($item, $containerRows);

            if ($costFromContainers !== null) {
                $item->forceFill(['unit_cost' => $costFromContainers])->save();
            }

            $variants = $kind === ItemKind::Menu ? $this->syncVariants($item, $data['variants'] ?? []) : [];

            if ($kind !== ItemKind::Menu) {
                $item->variants()->delete();
            }

            if ($kind !== ItemKind::Menu) {
                $item->recipeLines()->delete();
            } elseif (array_key_exists('recipe', $data)) {
                $this->syncRecipe($item, $data['recipe'] ?? [], $variants);
            }

            $this->recipeChanges->recordSaved($item, $user, $linksBefore);

            $onHand = self::nullableNumber($data['on_hand'] ?? null);

            if ($isNew) {
                $item->forceFill(['on_hand' => $onHand])->save();
            } elseif ($onHand !== (($item->on_hand === null) ? null : (float) $item->on_hand)) {
                $this->stock->setOnHand($item, $onHand, $user->id);
            }

            return $item->refresh()->load(['variants', 'recipeLines', 'containers']);
        });
    }

    /**
     * Keep existing containers (by id), add new ones, delete removed ones.
     *
     * Returns the cost per unit to use, or null to leave the item's cost alone:
     * the first container that is new, or whose price or size changed, sets it.
     * So saving the form untouched never overwrites the price of the last restock.
     *
     * @param  list<array{id?: int|null, label: string, size: float|string, price?: float|string|null}>  $rows
     */
    private function syncContainers(Item $item, array $rows): ?float
    {
        $existing = $item->containers()->get()->keyBy('id');
        $keptIds = [];
        $cost = null;

        foreach ($rows as $sort => $row) {
            $attributes = [
                'label' => trim($row['label']),
                'size' => round((float) $row['size'], 3),
                'price' => ($row['price'] ?? '') === '' ? null : round((float) $row['price'], 2),
                'sort' => $sort,
            ];

            $container = ! empty($row['id']) ? $existing->get((int) $row['id']) : null;
            $pricingChanged = $container === null
                || abs((float) $container->size - $attributes['size']) >= 0.0005
                || ($container->price === null ? null : (float) $container->price) !== $attributes['price'];

            if ($container !== null) {
                $container->update($attributes);
            } else {
                $container = $item->containers()->create($attributes);
            }

            $keptIds[] = $container->id;

            if ($cost === null && $pricingChanged) {
                $cost = $container->costPerUnit();
            }
        }

        $item->containers()->whereNotIn('id', $keptIds)->delete();
        $item->unsetRelation('containers');

        return $cost;
    }

    /**
     * Keep existing sizes (by id), add new ones, delete removed ones.
     *
     * @param  list<array{id?: int|null, label: string, cost?: float|string|null, price: float|string}>  $rows
     * @return list<int> variant ids in the submitted order
     */
    private function syncVariants(Item $item, array $rows): array
    {
        $keptIds = [];

        foreach (array_values($rows) as $sort => $row) {
            $cost = self::nullableNumber($row['cost'] ?? null);
            $attributes = [
                'label' => $row['label'],
                'cost' => $cost,
                'price' => $row['price'],
                'sort' => $sort,
            ];

            $variant = ! empty($row['id']) ? $item->variants()->whereKey($row['id'])->first() : null;
            $costChanged = $variant === null
                ? $cost !== null
                : ($variant->cost === null ? $cost !== null : $cost === null || abs((float) $variant->cost - $cost) >= 0.005);

            if ($costChanged) {
                $attributes['cost_updated_at'] = now();
            }

            if ($variant !== null) {
                $variant->update($attributes);
            } else {
                $variant = $item->variants()->create($attributes);
            }

            $keptIds[] = $variant->id;
        }

        $item->variants()->whereNotIn('id', $keptIds)->delete();

        return $keptIds;
    }

    /**
     * Replace recipe links. `variant_index` points at a submitted size (null = every size),
     * and `order_type` scopes the link to dine-in or take-out (null = both).
     *
     * @param  list<array{piece_item_id: int, qty: float|string, variant_index?: int|null, order_type?: string|null}>  $rows
     * @param  list<int>  $variantIds
     */
    private function syncRecipe(Item $item, array $rows, array $variantIds): void
    {
        $item->recipeLines()->delete();

        foreach ($rows as $row) {
            $variantIndex = $row['variant_index'] ?? null;

            $item->recipeLines()->create([
                'piece_item_id' => $row['piece_item_id'],
                'qty' => $row['qty'],
                'item_variant_id' => $variantIndex !== null && $variantIndex !== '' ? ($variantIds[(int) $variantIndex] ?? null) : null,
                'order_type' => ($row['order_type'] ?? null) ?: null,
            ]);
        }
    }

    private static function nullableNumber(mixed $value): ?float
    {
        return $value === null || $value === '' ? null : round((float) $value, 3);
    }

    /**
     * Costs keep six decimals: ₱0.011889 per ml must not become ₱0.012.
     */
    private static function nullableCost(mixed $value): ?float
    {
        return $value === null || $value === '' ? null : round((float) $value, 6);
    }
}
