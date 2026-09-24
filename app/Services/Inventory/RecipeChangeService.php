<?php

namespace App\Services\Inventory;

use App\Enums\ItemKind;
use App\Models\Item;
use App\Models\RecipeChange;
use App\Models\RecipeLine;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Who changed what one sale uses, and when. Owners change links directly (recorded);
 * cashiers ask, and nothing changes until the owner approves.
 */
class RecipeChangeService
{
    /**
     * The item's links right now, with names and units as they are today.
     *
     * @return list<array{piece_item_id: int, piece: string, unit: string, qty: float, item_variant_id: ?int, variant: ?string}>
     */
    public function snapshot(Item $item): array
    {
        $lines = RecipeLine::query()->where('item_id', $item->id)->with(['piece' => fn ($query) => $query->withoutGlobalScopes(), 'variant'])->orderBy('id')->get();

        return $lines->map(fn (RecipeLine $line) => [
            'piece_item_id' => $line->piece_item_id,
            'piece' => $line->piece?->name ?? 'Deleted item',
            'unit' => self::unitOf($line->piece),
            'qty' => round((float) $line->qty, 3),
            'item_variant_id' => $line->item_variant_id,
            'variant' => $line->variant?->label,
        ])->values()->all();
    }

    /**
     * Submitted rows (variant_index points at the item's sizes in order) as a snapshot.
     *
     * @param  list<array{piece_item_id: int|string, qty: float|string, variant_index?: int|string|null}>  $rows
     * @return list<array{piece_item_id: int, piece: string, unit: string, qty: float, item_variant_id: ?int, variant: ?string}>
     */
    public function snapshotFromRows(Item $item, array $rows): array
    {
        $variants = $item->variants()->get()->values();
        $pieces = Item::withoutGlobalScopes()->where('business_id', $item->business_id)->whereKey(array_column($rows, 'piece_item_id'))->get()->keyBy('id');

        return collect($rows)->map(function (array $row) use ($variants, $pieces): array {
            $index = $row['variant_index'] ?? null;
            $variant = $index !== null && $index !== '' ? $variants->get((int) $index) : null;
            $piece = $pieces->get((int) $row['piece_item_id']);

            return [
                'piece_item_id' => (int) $row['piece_item_id'],
                'piece' => $piece?->name ?? 'Unknown item',
                'unit' => self::unitOf($piece),
                'qty' => round((float) $row['qty'], 3),
                'item_variant_id' => $variant?->id,
                'variant' => $variant?->label,
            ];
        })->values()->all();
    }

    /**
     * Record an owner's save, when the links really changed.
     *
     * @param  list<array<string, mixed>>  $before
     */
    public function recordSaved(Item $item, User $owner, array $before): void
    {
        $after = $this->snapshot($item);

        if (RecipeChange::sameLinks($before, $after)) {
            return;
        }

        RecipeChange::withoutGlobalScopes()->create([
            'business_id' => $item->business_id,
            'item_id' => $item->id,
            'user_id' => $owner->id,
            'requested_by' => $owner->name,
            'status' => RecipeChange::SAVED,
            'before' => $before,
            'after' => $after,
            'decided_by' => $owner->id,
            'decided_by_name' => $owner->name,
            'decided_at' => now(),
        ]);
    }

    /**
     * A cashier asks for new links. Returns null when nothing would change.
     *
     * @param  list<array{piece_item_id: int|string, qty: float|string, variant_index?: int|string|null}>  $rows
     */
    public function request(Item $item, User $cashier, array $rows): ?RecipeChange
    {
        return DB::transaction(function () use ($item, $cashier, $rows): ?RecipeChange {
            $before = $this->snapshot($item);
            $after = $this->snapshotFromRows($item, $rows);

            RecipeChange::withoutGlobalScopes()
                ->where('item_id', $item->id)
                ->where('status', RecipeChange::PENDING)
                ->update(['status' => RecipeChange::REPLACED]);

            if (RecipeChange::sameLinks($before, $after)) {
                return null;
            }

            return RecipeChange::withoutGlobalScopes()->create([
                'business_id' => $item->business_id,
                'item_id' => $item->id,
                'user_id' => $cashier->id,
                'requested_by' => $cashier->name,
                'status' => RecipeChange::PENDING,
                'before' => $before,
                'after' => $after,
            ]);
        });
    }

    /**
     * Put a cashier's request into effect.
     *
     * @throws ValidationException
     */
    public function approve(RecipeChange $change, User $owner): void
    {
        DB::transaction(function () use ($change, $owner): void {
            $change = RecipeChange::withoutGlobalScopes()->whereKey($change->id)->lockForUpdate()->firstOrFail();
            $item = Item::withoutGlobalScopes()->whereKey($change->item_id)->lockForUpdate()->firstOrFail();

            if (! $change->isPending()) {
                throw ValidationException::withMessages(['change' => 'This request was already handled.']);
            }

            if ($item->kind !== ItemKind::Menu || $item->archived_at !== null) {
                throw ValidationException::withMessages(['change' => "{$item->name} is no longer on the menu. Reject this request."]);
            }

            // Approving must never undo a change made after the cashier asked.
            if (! RecipeChange::sameLinks($change->before, $this->snapshot($item))) {
                throw ValidationException::withMessages(['change' => "The links of {$item->name} changed after this request. Reject it and ask for a new one."]);
            }

            $pieceIds = array_column($change->after, 'piece_item_id');
            $usablePieces = Item::withoutGlobalScopes()
                ->where('business_id', $item->business_id)
                ->whereKey($pieceIds)
                ->whereIn('kind', [ItemKind::Piece, ItemKind::Bulk])
                ->whereNull('archived_at')
                ->count();
            $variantIds = array_filter(array_column($change->after, 'item_variant_id'));
            $usableVariants = $item->variants()->whereKey($variantIds)->count();

            if ($usablePieces !== count(array_unique($pieceIds)) || $usableVariants !== count(array_unique($variantIds))) {
                throw ValidationException::withMessages(['change' => 'A piece or size in this request no longer exists. Reject it and ask for a new one.']);
            }

            $item->recipeLines()->delete();

            foreach ($change->after as $line) {
                $item->recipeLines()->create([
                    'piece_item_id' => $line['piece_item_id'],
                    'qty' => $line['qty'],
                    'item_variant_id' => $line['item_variant_id'],
                ]);
            }

            $change->update([
                'status' => RecipeChange::APPROVED,
                'decided_by' => $owner->id,
                'decided_by_name' => $owner->name,
                'decided_at' => now(),
            ]);
        });
    }

    /**
     * @throws ValidationException
     */
    public function reject(RecipeChange $change, User $owner): void
    {
        if (! $change->isPending()) {
            throw ValidationException::withMessages(['change' => 'This request was already handled.']);
        }

        $change->update([
            'status' => RecipeChange::REJECTED,
            'decided_by' => $owner->id,
            'decided_by_name' => $owner->name,
            'decided_at' => now(),
        ]);
    }

    private static function unitOf(?Item $piece): string
    {
        return $piece === null ? '' : ($piece->unit ?: ($piece->kind === ItemKind::Piece ? 'pc' : ''));
    }
}
