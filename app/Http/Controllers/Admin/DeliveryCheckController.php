<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CheckDeliveryRequest;
use App\Models\Delivery;
use App\Models\Item;
use App\Services\Inventory\DeliveryService;
use Illuminate\Http\RedirectResponse;

class DeliveryCheckController extends Controller
{
    /**
     * Settle a cashier's restock against the supplier's receipt.
     */
    public function __invoke(CheckDeliveryRequest $request, Delivery $delivery, DeliveryService $deliveries): RedirectResponse
    {
        $result = $deliveries->check(
            $delivery,
            $request->user(),
            (float) $request->validated('receipt_quantity'),
            $request->paid(),
            $request->boolean('log_expense'),
        );

        $item = $delivery->item;
        $unit = $item->unit ?: 'pc';
        $message = "{$item->name} delivery checked.";

        if ($result['shortage'] > 0) {
            $message .= ' '.Item::trimNumber($result['shortage'])." {$unit} never reached the shelf";
            $message .= $result['missing_cost'] > 0 ? ': ₱'.number_format($result['missing_cost'], 2).' logged as missing stock.' : '.';
        }

        if ($result['overcount'] > 0) {
            $message .= ' The count was '.Item::trimNumber($result['overcount'])." {$unit} too high and was corrected.";
        }

        if ($result['expense_logged']) {
            $message .= ' The purchase is in your expenses.';
        }

        return back()->with('status', $message);
    }
}
