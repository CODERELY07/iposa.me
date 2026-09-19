<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Enums\BusinessStatus;
use App\Enums\SubscriptionPaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\SubscriptionPayment;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlanController extends Controller
{
    /**
     * Plans (from config/plans.php) with subscribers, and payments waiting for review.
     */
    public function __invoke(Request $request): View
    {
        $subscribers = Business::query()
            ->where('status', BusinessStatus::Active)
            ->selectRaw('plan, count(*) as total')
            ->groupBy('plan')
            ->pluck('total', 'plan');

        $status = SubscriptionPaymentStatus::tryFrom((string) $request->query('status'));

        $payments = SubscriptionPayment::query()
            ->with(['business:id,business_name', 'submitter:id,name'])
            ->when($status, fn ($query) => $query->where('status', $status))
            ->orderByRaw('case when status = ? then 0 else 1 end', [SubscriptionPaymentStatus::Pending->value])
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('super_admin.plans', [
            'plans' => config('plans.plans'),
            'subscribers' => $subscribers,
            'payments' => $payments,
            'activeStatus' => $status,
        ]);
    }
}
