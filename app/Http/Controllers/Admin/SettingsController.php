<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateBusinessProfileRequest;
use App\Http\Requests\Admin\UpdateRegisterSettingsRequest;
use App\Models\Business;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(Request $request): View
    {
        $business = $request->user()->business;

        return view('admin.settings', [
            'business' => $business,
            'settings' => $business->resolvedSettings(),
            'businessTypes' => UpdateBusinessProfileRequest::BUSINESS_TYPES,
            'paymentMethods' => PaymentMethod::cases(),
            'plans' => $this->plansFor($business),
            'manualPayment' => config('plans.manual_payment'),
            'payments' => $business->subscriptionPayments()->latest()->limit(5)->get(),
            'hasPendingPayment' => $business->subscriptionPayments()->where('status', 'pending')->exists(),
        ]);
    }

    /**
     * Plans an owner can pick, plus their own plan when it has since been archived.
     *
     * @return array<string, array<string, mixed>>
     */
    private function plansFor(Business $business): array
    {
        $plans = Plan::selectable()->keyBy('key')->map(fn (Plan $plan): array => $plan->details());

        if (! $plans->has($business->plan) && ($current = $business->planRecord()) !== null) {
            $plans = $plans->put($current->key, $current->details());
        }

        return $plans->all();
    }

    public function updateBusiness(UpdateBusinessProfileRequest $request): RedirectResponse
    {
        $request->user()->business->update($request->validated());

        return back()->with('status', 'Business details saved.');
    }

    public function updateRegister(UpdateRegisterSettingsRequest $request): RedirectResponse
    {
        $business = $request->user()->business;
        $settings = $business->settings ?? [];

        $settings['payment_methods'] = array_values(array_unique($request->validated('payment_methods')));
        $settings['audit_reminder_time'] = $request->validated('audit_reminder_time');
        $settings['default_low_threshold'] = (float) $request->validated('default_low_threshold');
        $settings['audit_pieces'] = $request->boolean('audit_pieces');

        $business->update(['settings' => $settings]);

        return back()->with('status', 'Register settings saved.');
    }
}
