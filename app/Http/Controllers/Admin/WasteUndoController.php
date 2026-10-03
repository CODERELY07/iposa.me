<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StockMovement;
use App\Services\Inventory\WasteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WasteUndoController extends Controller
{
    /**
     * Cancel a waste entry made today: the stock goes back and the loss leaves profit.
     */
    public function __invoke(Request $request, StockMovement $movement, WasteService $waste): RedirectResponse
    {
        $waste->undo($movement, $request->user());

        return back()->with('status', 'Waste undone. The stock is back on the shelf and the loss is out of profit.');
    }
}
