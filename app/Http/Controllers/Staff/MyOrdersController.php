<?php

namespace App\Http\Controllers\Staff;

use App\Enums\ExpenseCategory;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class MyOrdersController extends Controller
{
    /**
     * Today's orders rung up by this cashier, with drawer totals per payment method.
     */
    public function __invoke(Request $request): View
    {
        $orders = Order::query()
            ->where('user_id', $request->user()->id)
            ->whereBetween('paid_at', [today(), now()->endOfDay()])
            ->with('lines')
            ->latest('paid_at')
            ->get();

        $counted = $orders->whereIn('status', OrderStatus::countedAsSales());

        $totals = collect(PaymentMethod::cases())->mapWithKeys(fn (PaymentMethod $method) => [
            $method->value => [
                'label' => $method->label(),
                'amount' => round($counted->where('payment_method', $method)->sum(fn (Order $order) => (float) $order->subtotal), 2),
            ],
        ])->filter(fn (array $total) => $total['amount'] > 0 || $total['label'] === 'Cash');

        $business = $request->user()->business;

        return view('staff.orders', [
            'orders' => $orders,
            'shift' => [
                'started' => $orders->last()?->paid_at,
                'orders' => $counted->count(),
                'totals' => $totals,
            ],
            'canLogExpenses' => Gate::allows('log-expenses') && $business->hasFeature('expenses'),
            'canVoid' => Gate::allows('void-orders'),
            'expenseCategories' => ExpenseCategory::selectable(),
        ]);
    }
}
