<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\RestockProductRequest;
use App\Models\Delivery;
use App\Models\Item;
use App\Services\Inventory\DeliveryService;
use App\Services\Inventory\RestockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class ProductRestockController extends Controller
{
    /**
     * Put a delivery on the shelf. The owner checks it against the receipt later,
     * which is when the price, the expense and any shortage are settled.
     * Safe to retry with the same uuid: the delivery is only added once.
     */
    public function __invoke(RestockProductRequest $request, Item $item, RestockService $restocks, DeliveryService $deliveries): RedirectResponse|JsonResponse
    {
        $quantity = (float) $request->validated('quantity');
        $container = $request->container();
        $variant = $request->variant();
        $uuid = $request->validated('uuid');

        $existing = $uuid !== null ? Delivery::query()->where('uuid', $uuid)->first() : null;

        $result = $existing !== null ? ['added' => (float) $existing->added] : DB::transaction(function () use ($item, $request, $restocks, $deliveries, $quantity, $container, $variant, $uuid): array {
            $result = $restocks->restock($item, $request->user(), $quantity, $container, null, false, null, $variant);
            $deliveries->record($item, $request->user(), $quantity, $container, $result['added'], $variant, $uuid);

            return $result;
        });

        $item->refresh();
        $variant?->refresh();

        $message = $variant !== null
            ? "Added {$item->describeQuantity($result['added'])} to {$item->name} ({$variant->label}). On hand: {$item->describeQuantity($variant->on_hand)}."
            : "Added {$item->describeQuantity($result['added'])} to {$item->name}. On hand: {$item->describeQuantity($item->on_hand)}.";

        if ($request->expectsJson()) {
            // The page reloads to show the new count, so the message rides along as a flash.
            session()->flash('status', $message);

            return response()->json(['message' => $message], $existing !== null ? 200 : 201);
        }

        return redirect()
            ->route('staff.products', array_filter(['q' => $request->query('q')]))
            ->with('status', $message);
    }
}
