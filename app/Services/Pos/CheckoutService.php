<?php

namespace App\Services\Pos;

use App\Enums\ItemKind;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\StockMovementReason;
use App\Models\Business;
use App\Models\ItemVariant;
use App\Models\Order;
use App\Models\RecipeLine;
use App\Models\User;
use App\Services\Inventory\StockService;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckoutService
{
    public function __construct(private StockService $stock) {}

    /**
     * Record a sale: order + lines + stock deduction, all or nothing.
     * Prices and costs always come from the database, never from the browser.
     * Sending the same uuid twice returns the first order (double taps, retries).
     *
     * @param  array{uuid: string, payment_method: string, tendered?: float|string|null, lines: list<array{variant_id: int, qty: int}>}  $data
     *
     * @throws ValidationException
     */
    public function checkout(Business $business, User $cashier, array $data, ?CarbonInterface $paidAt = null): Order
    {
        return DB::transaction(function () use ($business, $cashier, $data, $paidAt): Order {
            $lockedBusiness = Business::whereKey($business->id)->lockForUpdate()->firstOrFail();

            $existing = Order::withoutGlobalScopes()
                ->where('business_id', $lockedBusiness->id)
                ->where('uuid', $data['uuid'])
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $quantities = [];

            foreach ($data['lines'] as $line) {
                $quantities[(int) $line['variant_id']] = ($quantities[(int) $line['variant_id']] ?? 0) + (int) $line['qty'];
            }

            $variants = ItemVariant::query()
                ->with('item.recipeLines.piece')
                ->whereKey(array_keys($quantities))
                ->whereHas('item', fn ($item) => $item->withoutGlobalScopes()
                    ->where('business_id', $lockedBusiness->id)
                    ->where('kind', ItemKind::Menu)
                    ->whereNull('archived_at'))
                ->get()
                ->keyBy('id');

            if ($variants->count() !== count($quantities)) {
                throw ValidationException::withMessages([
                    'lines' => 'Some items in this order are no longer on the menu. Refresh the register and try again.',
                ]);
            }

            $subtotal = round($variants->sum(fn (ItemVariant $variant) => (float) $variant->price * $quantities[$variant->id]), 2);
            $method = PaymentMethod::from($data['payment_method']);
            $tendered = isset($data['tendered']) && $data['tendered'] !== '' ? round((float) $data['tendered'], 2) : null;

            if ($method === PaymentMethod::Cash && ($tendered === null || $tendered < $subtotal)) {
                throw ValidationException::withMessages([
                    'tendered' => 'Cash received must cover the total of ₱'.number_format($subtotal, 2).'.',
                ]);
            }

            $lockedBusiness->increment('last_order_number');

            $order = Order::withoutGlobalScopes()->create([
                'business_id' => $lockedBusiness->id,
                'number' => $lockedBusiness->last_order_number,
                'uuid' => $data['uuid'],
                'user_id' => $cashier->id,
                'cashier_name' => $cashier->name,
                'payment_method' => $method,
                'subtotal' => $subtotal,
                'tendered' => $method === PaymentMethod::Cash ? $tendered : null,
                'change' => $method === PaymentMethod::Cash ? round($tendered - $subtotal, 2) : null,
                'status' => OrderStatus::Paid,
                'paid_at' => $paidAt ?? now(),
            ]);

            $deductions = [];

            foreach ($variants as $variant) {
                $qty = $quantities[$variant->id];

                $order->lines()->create([
                    'item_id' => $variant->item_id,
                    'item_variant_id' => $variant->id,
                    'name' => $variant->item->name,
                    'variant_label' => $variant->label,
                    'price' => $variant->price,
                    'unit_cost' => $variant->costPerSale(),
                    'qty' => $qty,
                ]);

                $recipe = $variant->item->recipeLines->filter(fn (RecipeLine $line) => $line->appliesTo($variant));

                if ($recipe->isNotEmpty()) {
                    foreach ($recipe as $recipeLine) {
                        $deductions[$recipeLine->piece_item_id] = ($deductions[$recipeLine->piece_item_id] ?? 0) - (float) $recipeLine->qty * $qty;
                    }
                } elseif ($variant->item->tracksStock()) {
                    $deductions[$variant->item_id] = ($deductions[$variant->item_id] ?? 0) - $qty;
                }
            }

            $this->stock->apply($lockedBusiness, $deductions, StockMovementReason::Sale, [
                'order_id' => $order->id,
                'user_id' => $cashier->id,
            ], $order->paid_at);

            return $order->load('lines');
        });
    }
}
