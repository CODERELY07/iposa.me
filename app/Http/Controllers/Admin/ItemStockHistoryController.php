<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ItemStockHistoryController extends Controller
{
    /**
     * Every change to one item's stock, newest first, with a running balance and a
     * link back to whatever caused it: an order, or a closing audit.
     */
    public function __invoke(Request $request, Item $item): View
    {
        // An item that counts each size separately has one history per size.
        $sizes = $item->variants()->get()->filter->tracksStock()->values();
        $variant = $sizes->firstWhere('id', (int) $request->query('size')) ?? ($item->tracksStockPerSize() ? $sizes->first() : null);
        $onHand = $variant !== null ? $variant->on_hand : $item->on_hand;

        $scope = fn ($query) => $query
            ->where('item_id', $item->id)
            ->when($variant !== null, fn ($query) => $query->where('item_variant_id', $variant->id), fn ($query) => $query->whereNull('item_variant_id'));

        $movements = $scope(StockMovement::query())
            ->with('order', 'audit', 'undoneBy')
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        $precedingSum = (float) $scope(StockMovement::query())
            ->when($movements->isNotEmpty(), fn ($query) => $query->where('id', '>', $movements->first()->id))
            ->sum('qty_change');

        $balance = round((float) ($onHand ?? 0) - $precedingSum, 3);

        foreach ($movements as $movement) {
            $movement->balance_after = $balance;
            $balance = round($balance - (float) $movement->qty_change, 3);
        }

        return view('admin.inventory.history', [
            'item' => $item,
            'movements' => $movements,
            'sizes' => $sizes,
            'variant' => $variant,
            'tracked' => $onHand !== null,
            'onHand' => $onHand,
        ]);
    }
}
