<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use Illuminate\View\View;

class HomeController extends Controller
{
    /**
     * The public landing page. Pricing comes from the plans the operator manages,
     * so the page can never advertise a price the app doesn't charge.
     */
    public function __invoke(): View
    {
        $plans = Plan::selectable();
        $featured = $plans->firstWhere('key', config('plans.default')) ?? $plans->last();

        return view('welcome', [
            'plans' => $plans->map(fn (Plan $plan): array => [
                'name' => $plan->name,
                'price' => (float) $plan->price,
                'for' => $plan->pitch,
                'features' => $plan->feature_list ?? [],
                'featured' => $featured !== null && $plan->is($featured),
            ])->all(),
        ]);
    }
}
