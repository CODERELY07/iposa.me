<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\RestockProductRequest;
use App\Models\Item;
use App\Services\Inventory\RestockService;
use Illuminate\Http\RedirectResponse;

class ProductRestockController extends Controller
{
    /**
     * Put a delivery on the shelf. The cost stays what the owner last paid.
     */
    public function __invoke(RestockProductRequest $request, Item $item, RestockService $restocks): RedirectResponse
    {
        $result = $restocks->restock($item, $request->user(), (float) $request->validated('quantity'), $request->container(), null, false);

        $item->refresh();

        return redirect()
            ->route('staff.products', array_filter(['q' => $request->query('q')]))
            ->with('status', "Added {$item->describeQuantity($result['added'])} to {$item->name}. On hand: {$item->describeQuantity($item->on_hand)}.");
    }
}
