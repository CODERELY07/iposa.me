<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RestockItemRequest;
use App\Models\Item;
use App\Services\Inventory\RestockService;
use Illuminate\Http\RedirectResponse;

class ItemRestockController extends Controller
{
    /**
     * Put bought stock on the shelf: the count goes up, the cost follows what was paid.
     */
    public function __invoke(RestockItemRequest $request, Item $item, RestockService $restocks): RedirectResponse
    {
        $result = $restocks->restock(
            $item,
            $request->user(),
            (float) $request->validated('quantity'),
            $request->container(),
            $request->paid(),
            $request->boolean('log_expense'),
        );

        $item->refresh()->load('containers');

        $message = "Added {$item->describeQuantity($result['added'])} to {$item->name}. On hand: {$item->describeQuantity($item->on_hand)}.";

        if ($result['expense_logged']) {
            $message .= ' The purchase is in your expenses.';
        }

        return redirect()->route('admin.inventory.edit', $item)->with('status', $message);
    }
}
