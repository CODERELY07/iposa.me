<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateBusinessProfileRequest;
use App\Http\Requests\Admin\UpdateRegisterSettingsRequest;
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
            'plans' => config('plans.plans'),
            'manualPayment' => config('plans.manual_payment'),
            'payments' => $business->subscriptionPayments()->latest()->limit(5)->get(),
            'hasPendingPayment' => $business->subscriptionPayments()->where('status', 'pending')->exists(),
        ]);
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

        $business->update(['settings' => $settings]);

        return back()->with('status', 'Register settings saved.');
    }
}
