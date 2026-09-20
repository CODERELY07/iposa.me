<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ItemKind;
use App\Enums\StockMovementReason;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveItemRequest;
use App\Models\Audit;
use App\Models\AuditLine;
use App\Models\Category;
use App\Models\Item;
use App\Models\OrderLine;
use App\Models\RecipeLine;
use App\Models\StockMovement;
use App\Services\Inventory\ItemService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ItemController extends Controller
{
    /**
     * Inventory tabs: menu items, pieces, bulk & liquids.
     */
    public function index(Request $request): View
    {
        $business = $request->user()->business;
        $search = trim((string) $request->query('q'));
        $showArchived = $request->boolean('archived');
        $tab = in_array($request->query('tab'), ['menu', 'pieces', 'bulk'], true) ? $request->query('tab') : 'menu';

        $items = Item::query()
            ->when(! $showArchived, fn ($query) => $query->whereNull('archived_at'))
            ->when($showArchived, fn ($query) => $query->whereNotNull('archived_at'))
            ->when($search !== '', fn ($query) => $query->whereLike('name', '%'.$search.'%'))
            ->with(['category', 'variants', 'recipeLines.piece', 'recipeLines.variant'])
            ->orderBy('name')
            ->get();

        $usedToday = StockMovement::query()
            ->where('reason', StockMovementReason::Sale)
            ->where('created_at', '>=', today())
            ->selectRaw('item_id, -sum(qty_change) as used')
            ->groupBy('item_id')
            ->pluck('used', 'item_id');

        $lastAudit = Audit::query()->latest('date')->first();

        return view('admin.inventory.index', [
            'tab' => $tab,
            'search' => $search,
            'showArchived' => $showArchived,
            'menuItems' => $items->where('kind', ItemKind::Menu)->values(),
            'pieceItems' => $items->where('kind', ItemKind::Piece)->values(),
            'bulkItems' => $items->where('kind', ItemKind::Bulk)->values(),
            'usedToday' => $usedToday,
            'bulkDailyUse' => $this->averageDailyBulkUse($items->where('kind', ItemKind::Bulk)->pluck('id')),
            'lastAudit' => $lastAudit,
            'business' => $business,
        ]);
    }

    public function create(Request $request): View
    {
        $kind = ItemKind::tryFrom((string) $request->query('kind')) ?? ItemKind::Menu;

        return view('admin.inventory.item', $this->formData($request, new Item(['kind' => $kind])));
    }

    public function store(SaveItemRequest $request, ItemService $items): RedirectResponse
    {
        $item = $items->save($request->user()->business, $request->user(), $this->allowedData($request));

        return redirect()
            ->route('admin.inventory', ['tab' => $this->tabFor($item)])
            ->with('status', "{$item->name} added.");
    }

    public function edit(Request $request, Item $item): View
    {
        return view('admin.inventory.item', $this->formData($request, $item->load(['variants', 'recipeLines'])));
    }

    public function update(SaveItemRequest $request, Item $item, ItemService $items): RedirectResponse
    {
        $item = $items->save($request->user()->business, $request->user(), $this->allowedData($request), $item);

        return redirect()
            ->route('admin.inventory', ['tab' => $this->tabFor($item)])
            ->with('status', "{$item->name} saved.");
    }

    /**
     * Hide from the register and lists. Past orders keep pointing at it.
     */
    public function archive(Item $item): RedirectResponse
    {
        $item->update(['archived_at' => now()]);

        return redirect()
            ->route('admin.inventory', ['tab' => $this->tabFor($item)])
            ->with('status', "{$item->name} archived. Find it under “Show archived”.");
    }

    public function restore(Item $item): RedirectResponse
    {
        $item->update(['archived_at' => null]);

        return redirect()
            ->route('admin.inventory', ['tab' => $this->tabFor($item)])
            ->with('status', "{$item->name} is back.");
    }

    /**
     * Permanent delete, only for an item that was never part of the shop's history:
     * no sales, no stock movements, no audits, and not linked into a recipe.
     * Anything else must be archived, so past orders and reports stay explainable.
     */
    public function destroy(Item $item): RedirectResponse
    {
        $blockers = $this->deleteBlockers($item);

        if ($blockers !== []) {
            throw ValidationException::withMessages([
                'item' => "{$item->name} can't be deleted: ".implode(' ', $blockers).' Archive it instead.',
            ]);
        }

        $tab = $this->tabFor($item);
        $name = $item->name;

        DB::transaction(function () use ($item): void {
            $item->recipeLines()->delete();
            $item->variants()->delete();
            $item->delete();
        });

        return redirect()
            ->route('admin.inventory', ['tab' => $tab])
            ->with('status', "{$name} deleted.");
    }

    /**
     * Why an item can't be deleted, in the owner's words. Empty means it can.
     *
     * @return list<string>
     */
    private function deleteBlockers(Item $item): array
    {
        $blockers = [];

        $sold = (int) OrderLine::query()->where('item_id', $item->id)->sum('qty');

        if ($sold > 0) {
            $blockers[] = "it has been sold ({$sold} so far).";
        }

        if ($item->stockMovements()->exists()) {
            $blockers[] = 'it has stock movements on record.';
        }

        if (AuditLine::query()->where('item_id', $item->id)->exists()) {
            $blockers[] = 'it has been counted in a closing audit.';
        }

        $usedInRecipes = RecipeLine::query()->where('piece_item_id', $item->id)->count();

        if ($usedInRecipes > 0) {
            $blockers[] = 'it is linked to '.$usedInRecipes.' '.str('recipe')->plural($usedInRecipes).'.';
        }

        return $blockers;
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Request $request, Item $item): array
    {
        $business = $request->user()->business;

        $pieces = Item::query()
            ->active()
            ->ofKind(ItemKind::Piece)
            ->orderBy('name')
            ->get(['id', 'name', 'unit', 'unit_cost']);

        $variants = $item->relationLoaded('variants') ? $item->variants : collect();

        return [
            'item' => $item,
            'canDelete' => $item->exists && $this->deleteBlockers($item) === [],
            'categories' => Category::query()->orderBy('sort')->orderBy('name')->get(),
            'pieces' => $pieces,
            'recipesEnabled' => $business->hasFeature('recipes'),
            'formState' => [
                'kind' => old('kind', $item->kind?->value ?? ItemKind::Menu->value),
                'name' => old('name', $item->name ?? ''),
                'variants' => old('variants', $variants->map(fn ($variant) => [
                    'id' => $variant->id,
                    'label' => $variant->label,
                    'cost' => (float) $variant->cost,
                    'price' => (float) $variant->price,
                ])->values()->all() ?: [['id' => null, 'label' => 'Regular', 'cost' => null, 'price' => null]]),
                'recipe' => old('recipe', $item->relationLoaded('recipeLines') ? $item->recipeLines->map(fn ($line) => [
                    'piece_item_id' => $line->piece_item_id,
                    'qty' => (float) $line->qty,
                    'variant_index' => $line->item_variant_id !== null ? $variants->search(fn ($variant) => $variant->id === $line->item_variant_id) : null,
                ])->values()->all() : []),
            ],
            'pieceCosts' => $pieces->mapWithKeys(fn (Item $piece) => [$piece->id => (float) $piece->unit_cost])->all(),
        ];
    }

    /**
     * Drop recipe links when the plan doesn't include them.
     *
     * @return array<string, mixed>
     */
    private function allowedData(SaveItemRequest $request): array
    {
        $data = $request->validated();

        if (! $request->user()->business->hasFeature('recipes')) {
            unset($data['recipe']);
        }

        return $data;
    }

    private function tabFor(Item $item): string
    {
        return match ($item->kind) {
            ItemKind::Menu => 'menu',
            ItemKind::Piece => 'pieces',
            ItemKind::Bulk => 'bulk',
        };
    }

    /**
     * Average units used per audited day, over the last 7 audits.
     *
     * @param  Collection<int, int>  $itemIds
     * @return Collection<int, float>
     */
    private function averageDailyBulkUse(Collection $itemIds): Collection
    {
        $recentAuditIds = Audit::query()->latest('date')->limit(7)->pluck('id');

        if ($recentAuditIds->isEmpty()) {
            return collect();
        }

        return AuditLine::query()
            ->whereIn('audit_id', $recentAuditIds)
            ->whereIn('item_id', $itemIds)
            ->selectRaw('item_id, avg(used) as average_used')
            ->groupBy('item_id')
            ->pluck('average_used', 'item_id')
            ->map(fn ($average) => round((float) $average, 2));
    }
}
