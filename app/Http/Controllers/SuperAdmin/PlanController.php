<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Enums\BusinessStatus;
use App\Enums\SubscriptionPaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\SavePlanRequest;
use App\Models\Business;
use App\Models\Plan;
use App\Models\SubscriptionPayment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PlanController extends Controller
{
    /**
     * Plans with their subscribers, and the payments waiting for review.
     */
    public function index(Request $request): View
    {
        $status = SubscriptionPaymentStatus::tryFrom((string) $request->query('status'));

        $payments = SubscriptionPayment::query()
            ->with(['business:id,business_name', 'submitter:id,name'])
            ->when($status, fn ($query) => $query->where('status', $status))
            ->orderByRaw('case when status = ? then 0 else 1 end', [SubscriptionPaymentStatus::Pending->value])
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('super_admin.plans.index', [
            'plans' => Plan::query()->orderBy('sort')->orderBy('price')->get(),
            'subscribers' => $this->subscriberCounts(),
            'payingSubscribers' => $this->subscriberCounts(BusinessStatus::Active),
            'planNames' => Plan::query()->pluck('name', 'key'),
            'payments' => $payments,
            'activeStatus' => $status,
        ]);
    }

    public function create(): View
    {
        return view('super_admin.plans.form', [
            'plan' => new Plan(['features' => [], 'feature_list' => [], 'sort' => (int) (Plan::max('sort') ?? 0) + 10]),
            'subscriberCount' => 0,
        ]);
    }

    public function store(SavePlanRequest $request): RedirectResponse
    {
        $plan = Plan::create($request->planAttributes());

        return redirect()->route('super_admin.plans')->with('status', "{$plan->name} is live. Owners can switch to it now.");
    }

    public function edit(Plan $plan): View
    {
        return view('super_admin.plans.form', [
            'plan' => $plan,
            'subscriberCount' => $plan->subscriberCount(),
        ]);
    }

    /**
     * Changing the price does not change what current shops pay: their price is
     * locked on `businesses.plan_price` and re-agreed at their next renewal.
     */
    public function update(SavePlanRequest $request, Plan $plan): RedirectResponse
    {
        $oldPrice = (float) $plan->price;
        $plan->update($request->planAttributes());

        $message = "{$plan->name} saved.";

        if (abs($oldPrice - (float) $plan->price) >= 0.01 && ($subscribers = $plan->subscriberCount()) > 0) {
            $message .= ' '.$subscribers.' '.str('shop')->plural($subscribers).' on it keep paying ₱'.number_format($oldPrice).' until their next renewal.';
        }

        return redirect()->route('super_admin.plans')->with('status', $message);
    }

    /**
     * Hide a plan from sign-ups and plan switching. Shops already on it keep it.
     */
    public function archive(Plan $plan): RedirectResponse
    {
        if (Plan::query()->available()->whereKeyNot($plan->id)->doesntExist()) {
            throw ValidationException::withMessages(['plan' => 'This is the only plan left. Add another one before archiving it.']);
        }

        $plan->forceFill(['archived_at' => now()])->save();

        $subscribers = $plan->subscriberCount();

        return back()->with('status', $subscribers > 0
            ? "{$plan->name} is archived. The {$subscribers} ".str('shop')->plural($subscribers).' on it keep working; nobody new can choose it.'
            : "{$plan->name} is archived.");
    }

    public function restore(Plan $plan): RedirectResponse
    {
        $plan->forceFill(['archived_at' => null])->save();

        return back()->with('status', "{$plan->name} is available again.");
    }

    /**
     * Only a plan nobody has ever been on can be deleted; otherwise archive it,
     * so past payments keep pointing at a plan that still exists.
     */
    public function destroy(Plan $plan): RedirectResponse
    {
        if ($plan->subscriberCount() > 0) {
            throw ValidationException::withMessages([
                'plan' => "Shops are on {$plan->name}. Archive it instead, or move them to another plan first.",
            ]);
        }

        if (SubscriptionPayment::query()->where('plan', $plan->key)->exists()) {
            throw ValidationException::withMessages([
                'plan' => "{$plan->name} has payments in its history. Archive it instead.",
            ]);
        }

        $name = $plan->name;
        $plan->delete();

        return redirect()->route('super_admin.plans')->with('status', "{$name} deleted.");
    }

    /**
     * Businesses per plan key, optionally only those in one status.
     *
     * @return Collection<string, int>
     */
    private function subscriberCounts(?BusinessStatus $status = null)
    {
        return Business::query()
            ->when($status, fn ($query) => $query->where('status', $status))
            ->selectRaw('plan, count(*) as total')
            ->groupBy('plan')
            ->pluck('total', 'plan');
    }
}
