<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Pos\VoidOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class OrderController extends Controller
{
    /**
     * Printable 58mm receipt.
     */
    public function receipt(Request $request, Order $order): View
    {
        return view('pos.receipt', [
            'order' => $order->load('lines'),
            'business' => $request->user()->business,
        ]);
    }

    /**
     * Void a paid order, or ask the owner to when this cashier isn't allowed to.
     */
    public function void(Request $request, Order $order, VoidOrderService $voids): JsonResponse|RedirectResponse
    {
        $user = $request->user();

        if (Gate::allows('void-orders')) {
            $voids->void($order, $user);
            $message = "Order #{$order->number} voided. Stock was put back.";
        } else {
            $voids->request($order, $user);
            $message = "Void request for order #{$order->number} sent to the owner.";
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'status' => $order->status->value]);
        }

        return back()->with('status', $message);
    }
}
