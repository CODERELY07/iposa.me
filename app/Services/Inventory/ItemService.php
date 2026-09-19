<?php

namespace App\Services\Inventory;

use App\Enums\ItemKind;
use App\Models\Business;
use App\Models\Item;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ItemService
{
    public function __construct(private StockService $stock) {}

    /**
     * Create or update an item with its sizes and recipe links in one transaction.
     *
     * @param  array{
     *     kind: string, name: string, category_id?: int|null, unit?: string|null,
     *     on_hand?: float|string|null, low_threshold?: float|string|null, unit_cost?: float|string|null,
     *     variants?: list<array{id?: int|null, label: string, cost?: float|string|null, price: float|string}>,
     *     recipe?: list<array{piece_item_id: int, qty: float|string, variant_index?: int|null}>
     * }  $data
     */
    public function save(Business $business, User $user, array $data, ?Item $item = null): Item
    {
        return DB::transaction(function () use ($business, $user, $data, $item): Item {
            $kind = ItemKind::from($data['kind']);
            $isNew = $item === null;
            $item ??= new Item(['business_id' => $business->id]);

            $item->fill([
                'kind' => $kind,
                'name' => $data['name'],
                'category_id' => $data['category_id'] ?? null,
                'unit' => $data['unit'] ?? null,
                'low_threshold' => self::nullableNumber($data['low_threshold'] ?? null),
                'unit_cost' => $kind === ItemKind::Menu ? null : self::nullableNumber($data['unit_cost'] ?? null),
            ]);
            $item->business_id = $business->id;
            $item->save();

            $variants = $kind === ItemKind::Menu ? $this->syncVariants($item, $data['variants'] ?? []) : [];

            if ($kind !== ItemKind::Menu) {
                $item->variants()->delete();
            }

            $this->syncRecipe($item, $kind === ItemKind::Menu ? ($data['recipe'] ?? []) : [], $variants);

            $onHand = self::nullableNumber($data['on_hand'] ?? null);

            if ($isNew) {
                $item->forceFill(['on_hand' => $onHand])->save();
            } elseif ($onHand !== (($item->on_hand === null) ? null : (float) $item->on_hand)) {
                $this->stock->setOnHand($item, $onHand, $user->id);
            }

            return $item->refresh()->load(['variants', 'recipeLines']);
        });
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
            $attributes = [
                'label' => $row['label'],
                'cost' => self::nullableNumber($row['cost'] ?? null) ?? 0,
                'price' => $row['price'],
                'sort' => $sort,
            ];

            $variant = ! empty($row['id']) ? $item->variants()->whereKey($row['id'])->first() : null;

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
     * Replace recipe links. `variant_index` points at a submitted size (null = every size).
     *
     * @param  list<array{piece_item_id: int, qty: float|string, variant_index?: int|null}>  $rows
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
            ]);
        }
    }

    private static function nullableNumber(mixed $value): ?float
    {
        return $value === null || $value === '' ? null : round((float) $value, 3);
    }
}
