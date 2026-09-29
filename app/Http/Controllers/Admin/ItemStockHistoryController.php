<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\StockMovement;
use Illuminate\View\View;

class ItemStockHistoryController extends Controller
{
    /**
     * Every change to one item's stock, newest first, with a running balance and a
     * link back to whatever caused it: an order, or a closing audit.
     */
    public function __invoke(Item $item): View
    {
        $movements = StockMovement::query()
            ->where('item_id', $item->id)
            ->with('order', 'audit')
            ->latest('id')
            ->paginate(50);

        $precedingSum = (float) StockMovement::query()
            ->where('item_id', $item->id)
            ->when($movements->isNotEmpty(), fn ($query) => $query->where('id', '>', $movements->first()->id))
            ->sum('qty_change');

        $balance = round((float) ($item->on_hand ?? 0) - $precedingSum, 3);

        foreach ($movements as $movement) {
            $movement->balance_after = $balance;
            $balance = round($balance - (float) $movement->qty_change, 3);
        }

        return view('admin.inventory.history', [
            'item' => $item,
            'movements' => $movements,
        ]);
    }
}
