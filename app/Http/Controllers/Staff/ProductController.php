<?php

namespace App\Http\Controllers\Staff;

use App\Enums\ItemKind;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\UpdateProductLinksRequest;
use App\Models\Item;
use App\Services\Inventory\ItemService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The cashier's products screen: restock counts and link pieces, when the owner allows it.
 * Names, prices and costs are never shown or changed here.
 */
class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $canRestock = $user->can('restock-stock');
        $canLink = $user->can('link-pieces') && $user->business->hasFeature('recipes');

        abort_unless($canRestock || $canLink, 403);

        $search = trim((string) $request->query('q'));

        $items = Item::query()
            ->whereNull('archived_at')
            ->when($search !== '', fn ($query) => $query->whereLike('name', '%'.$search.'%'))
            ->with(['containers', 'variants', 'recipeLines.piece', 'recipeLines.variant'])
            ->orderBy('name')
            ->get();

        return view('staff.products.index', [
            'search' => $search,
            'canRestock' => $canRestock,
            'canLink' => $canLink,
            'stockItems' => $canRestock ? $items->filter(fn (Item $item) => $item->kind !== ItemKind::Menu || $item->tracksStock())->values() : collect(),
            'menuItems' => $canLink ? $items->where('kind', ItemKind::Menu)->values() : collect(),
        ]);
    }

    public function editLinks(Item $item): View
    {
        abort_unless($item->kind === ItemKind::Menu && $item->archived_at === null, 404);

        $item->load(['variants', 'recipeLines']);

        $pieces = Item::query()
            ->whereIn('kind', [ItemKind::Piece, ItemKind::Bulk])
            ->whereNull('archived_at')
            ->orderByRaw('case when kind = ? then 0 else 1 end', [ItemKind::Piece->value])
            ->orderBy('name')
            ->get(['id', 'kind', 'name', 'unit']);

        return view('staff.products.links', [
            'item' => $item,
            'pieces' => $pieces,
            'variants' => $item->variants->map(fn ($variant) => ['label' => $variant->label])->values(),
            'recipe' => old('recipe', $item->recipeLines->map(fn ($line) => [
                'piece_item_id' => $line->piece_item_id,
                'qty' => (float) $line->qty,
                'variant_index' => $line->item_variant_id !== null ? $item->variants->search(fn ($variant) => $variant->id === $line->item_variant_id) : null,
            ])->values()->all()),
            'pieceUnits' => $pieces->mapWithKeys(fn (Item $piece) => [$piece->id => $piece->unit ?: ($piece->kind === ItemKind::Piece ? 'pc' : '')])->all(),
        ]);
    }

    public function updateLinks(UpdateProductLinksRequest $request, Item $item, ItemService $items): RedirectResponse
    {
        $items->saveRecipe($item, $request->validated('recipe') ?? []);

        return redirect()->route('staff.products')->with('status', "Links for {$item->name} saved.");
    }
}
