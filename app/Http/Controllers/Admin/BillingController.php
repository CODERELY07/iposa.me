<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SubmitSubscriptionPaymentRequest;
use App\Services\Billing\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BillingController extends Controller
{
    public function changePlan(Request $request, SubscriptionService $subscriptions): RedirectResponse
    {
        $validated = $request->validate([
            'plan' => ['required', Rule::in(array_keys(config('plans.plans')))],
        ]);

        $business = $subscriptions->changePlan($request->user()->business, $validated['plan']);

        return redirect()->to(route('admin.settings').'#billing')
            ->with('status', "You're now on {$business->planDetails()['name']}.");
    }

    public function submitPayment(SubmitSubscriptionPaymentRequest $request, SubscriptionService $subscriptions): RedirectResponse
    {
        $subscriptions->submitPayment($request->user()->business, $request->user(), $request->validated());

        return redirect()->to(route('admin.settings').'#billing')
            ->with('status', 'Payment reference sent. We’ll confirm it within one business day.');
    }
}
