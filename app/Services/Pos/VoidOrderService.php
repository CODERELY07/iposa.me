<?php

namespace App\Services\Pos;

use App\Enums\OrderStatus;
use App\Enums\StockMovementReason;
use App\Models\Order;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Inventory\StockService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VoidOrderService
{
    public function __construct(private StockService $stock) {}

    /**
     * A cashier without void permission asks the owner to void a paid order.
     *
     * @throws ValidationException
     */
    public function request(Order $order, User $requester): Order
    {
        if ($order->status !== OrderStatus::Paid) {
            throw ValidationException::withMessages(['order' => 'Only paid orders can be voided.']);
        }

        $order->update([
            'status' => OrderStatus::VoidRequested,
            'void_requested_by' => $requester->id,
        ]);

        return $order;
    }

    /**
     * Void the order and put its stock back, exactly as it was deducted.
     *
     * @throws ValidationException
     */
    public function void(Order $order, User $approver): Order
    {
        if ($order->isVoided()) {
            throw ValidationException::withMessages(['order' => 'This order is already voided.']);
        }

        return DB::transaction(function () use ($order, $approver): Order {
            $saleMovements = StockMovement::withoutGlobalScopes()
                ->where('order_id', $order->id)
                ->where('reason', StockMovementReason::Sale)
                ->get()
                ->groupBy('item_id');

            $restock = $saleMovements->map(fn ($movements) => -1 * $movements->sum(fn (StockMovement $movement) => (float) $movement->qty_change))->all();
            $costedRestock = $saleMovements->map(fn ($movements) => -1 * $movements->sum(fn (StockMovement $movement) => (float) $movement->costed_qty))->all();

            $this->stock->apply($order->business, $restock, StockMovementReason::Void, [
                'order_id' => $order->id,
                'user_id' => $approver->id,
            ], costedChanges: $costedRestock);

            $order->update([
                'status' => OrderStatus::Voided,
                'voided_by' => $approver->id,
                'voided_at' => now(),
            ]);

            return $order;
        });
    }

    /**
     * The owner keeps the sale: back to paid.
     *
     * @throws ValidationException
     */
    public function reject(Order $order): Order
    {
        if ($order->status !== OrderStatus::VoidRequested) {
            throw ValidationException::withMessages(['order' => 'There is no void request on this order.']);
        }

        $order->update(['status' => OrderStatus::Paid, 'void_requested_by' => null]);

        return $order;
    }
}
