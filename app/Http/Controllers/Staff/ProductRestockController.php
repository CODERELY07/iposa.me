<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\RestockProductRequest;
use App\Models\Item;
use App\Services\Inventory\DeliveryService;
use App\Services\Inventory\RestockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class ProductRestockController extends Controller
{
    /**
     * Put a delivery on the shelf. The owner checks it against the receipt later,
     * which is when the price, the expense and any shortage are settled.
     */
    public function __invoke(RestockProductRequest $request, Item $item, RestockService $restocks, DeliveryService $deliveries): RedirectResponse
    {
        $quantity = (float) $request->validated('quantity');
        $container = $request->container();

        $result = DB::transaction(function () use ($item, $request, $restocks, $deliveries, $quantity, $container): array {
            $result = $restocks->restock($item, $request->user(), $quantity, $container, null, false);
            $deliveries->record($item, $request->user(), $quantity, $container, $result['added']);

            return $result;
        });

        $item->refresh();

        return redirect()
            ->route('staff.products', array_filter(['q' => $request->query('q')]))
            ->with('status', "Added {$item->describeQuantity($result['added'])} to {$item->name}. On hand: {$item->describeQuantity($item->on_hand)}.");
    }
}
