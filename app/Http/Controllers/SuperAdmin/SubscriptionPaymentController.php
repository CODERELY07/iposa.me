<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPayment;
use App\Services\Billing\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SubscriptionPaymentController extends Controller
{
    /**
     * Money received: activate the business for another billing period.
     */
    public function confirm(Request $request, SubscriptionPayment $payment, SubscriptionService $subscriptions): RedirectResponse
    {
        $subscriptions->confirmPayment($payment, $request->user());

        return back()->with('status', "Payment confirmed. {$payment->business->business_name} is active until {$payment->business->refresh()->due_date->format('M j, Y')}.");
    }

    public function reject(Request $request, SubscriptionPayment $payment, SubscriptionService $subscriptions): RedirectResponse
    {
        $validated = $request->validate(['note' => ['nullable', 'string', 'max:255']]);

        $subscriptions->rejectPayment($payment, $request->user(), $validated['note'] ?? null);

        return back()->with('status', 'Payment rejected.');
    }
}
