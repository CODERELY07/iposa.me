<?php

namespace App\Http\Controllers\Pos;

use App\Enums\ItemKind;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pos\StoreOrderRequest;
use App\Models\Item;
use App\Models\ItemVariant;
use App\Models\RecipeLine;
use App\Services\Pos\CheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class RegisterController extends Controller
{
    /**
     * Show "N left" on a tile when this many or fewer sales remain.
     */
    private const LOW_AVAILABILITY = 10;

    /**
     * The register: menu tiles for the cashier. Never includes cost prices.
     */
    public function index(Request $request): View
    {
        $business = $request->user()->business;

        $items = Item::query()
            ->active()
            ->ofKind(ItemKind::Menu)
            ->whereHas('variants')
            ->with(['category', 'variants', 'recipeLines.piece'])
            ->orderBy(
                fn ($query) => $query->select('sort')->from('categories')->whereColumn('categories.id', 'items.category_id'),
            )
            ->orderBy('name')
            ->get();

        $menu = $items->map(fn (Item $item) => [
            'id' => $item->id,
            'name' => $item->name,
            'category' => $item->category?->name ?? 'Other',
            'color' => $item->category?->color ?? 'ink',
            'variants' => $item->variants->map(fn (ItemVariant $variant) => [
                'id' => $variant->id,
                'label' => $variant->label,
                'price' => (float) $variant->price,
            ])->values()->all(),
            'stockLeft' => $this->salesLeft($item),
        ])->values()->all();

        return view('pos.index', [
            'menu' => $menu,
            'paymentMethods' => array_map(fn (PaymentMethod $method) => ['value' => $method->value, 'label' => $method->label()], $business->enabledPaymentMethods()),
            'nextOrderNumber' => $business->last_order_number + 1,
            'cashierName' => $request->user()->name,
        ]);
    }

    /**
     * Ring up a sale (JSON). Safe to retry with the same uuid.
     */
    public function store(StoreOrderRequest $request, CheckoutService $checkout): JsonResponse
    {
        $paidAt = $request->filled('offline_created_at')
            ? Carbon::parse($request->validated('offline_created_at'))->setTimezone(config('app.timezone'))
            : null;

        $order = $checkout->checkout($request->user()->business, $request->user(), $request->validated(), $paidAt);

        return response()->json([
            'order' => [
                'id' => $order->id,
                'number' => $order->number,
                'total' => (float) $order->subtotal,
                'change' => $order->change !== null ? (float) $order->change : null,
                'receipt_url' => route('pos.orders.receipt', $order),
            ],
        ], $order->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * How many more can be sold before a linked piece (or the item itself) runs out, when that's low.
     */
    private function salesLeft(Item $item): ?int
    {
        $sharedRecipe = $item->recipeLines->whereNull('item_variant_id');

        if ($sharedRecipe->isNotEmpty()) {
            $left = $sharedRecipe
                ->filter(fn (RecipeLine $line) => $line->piece?->tracksStock() && (float) $line->qty > 0)
                ->map(fn (RecipeLine $line) => (int) floor(max(0, (float) $line->piece->on_hand) / (float) $line->qty))
                ->min();
        } elseif ($item->recipeLines->isEmpty() && $item->tracksStock()) {
            $left = (int) floor(max(0, (float) $item->on_hand));
        } else {
            return null;
        }

        return $left !== null && $left <= self::LOW_AVAILABILITY ? $left : null;
    }
}
