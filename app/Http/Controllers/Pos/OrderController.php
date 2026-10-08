<?php

namespace App\Http\Controllers\Pos;

use App\Enums\OrderStatus;
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
        $message = $this->voidOrRequest($request, $order, $voids);

        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'status' => $order->status->value]);
        }

        return back()->with('status', $message);
    }

    /**
     * The same, found by the sale's uuid: a void saved offline can name a sale that only reached
     * the server a moment ago. Safe to retry; a sale already voided or already awaiting the owner is left alone.
     */
    public function voidByUuid(Request $request, string $uuid, VoidOrderService $voids): JsonResponse
    {
        $order = Order::query()->where('uuid', $uuid)->firstOrFail();

        $alreadyDone = Gate::allows('void-orders') ? $order->isVoided() : $order->status !== OrderStatus::Paid;

        $message = $alreadyDone
            ? "Order #{$order->number} was already handled."
            : $this->voidOrRequest($request, $order, $voids);

        return response()->json(['message' => $message, 'status' => $order->status->value]);
    }

    private function voidOrRequest(Request $request, Order $order, VoidOrderService $voids): string
    {
        $user = $request->user();

        if (Gate::allows('void-orders')) {
            $voids->void($order, $user);

            return "Order #{$order->number} voided. Stock was put back.";
        }

        $voids->request($order, $user);

        return "Void request for order #{$order->number} sent to the owner.";
    }
}
