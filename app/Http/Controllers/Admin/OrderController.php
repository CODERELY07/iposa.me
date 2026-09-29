<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\StockMovement;
use Illuminate\View\View;

class OrderController extends Controller
{
    /**
     * One order's lines and every stock movement it caused, so an owner can confirm
     * a recipe link actually deducted stock (and, if voided, put it back).
     */
    public function show(Order $order): View
    {
        return view('admin.orders.show', [
            'order' => $order->load('lines'),
            'movements' => StockMovement::query()
                ->where('order_id', $order->id)
                ->with('item')
                ->oldest('id')
                ->get(),
        ]);
    }
}
