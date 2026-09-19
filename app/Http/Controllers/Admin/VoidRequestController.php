<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Pos\VoidOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class VoidRequestController extends Controller
{
    public function approve(Request $request, Order $order, VoidOrderService $voids): RedirectResponse
    {
        $voids->void($order, $request->user());

        return back()->with('status', "Order #{$order->number} voided. Stock was put back.");
    }

    public function reject(Order $order, VoidOrderService $voids): RedirectResponse
    {
        $voids->reject($order);

        return back()->with('status', "Order #{$order->number} kept as paid.");
    }
}
