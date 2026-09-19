<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Enums\BusinessStatus;
use App\Enums\SubscriptionPaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\SubscriptionPayment;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class PlatformDashboardController extends Controller
{
    /**
     * Operator overview: revenue, trial funnel, and who needs a call.
     */
    public function __invoke(): View
    {
        $statusCounts = Business::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $activeBusinesses = Business::query()->where('status', BusinessStatus::Active)->get(['id', 'plan']);
        $mrr = $activeBusinesses->sum(fn (Business $business) => $business->planDetails()['price']);

        return view('super_admin.dashboard', [
            'metrics' => [
                'mrr' => $mrr,
                'paying' => (int) ($statusCounts[BusinessStatus::Active->value] ?? 0),
                'trials' => (int) ($statusCounts[BusinessStatus::Trial->value] ?? 0),
                'pastDue' => (int) ($statusCounts[BusinessStatus::PastDue->value] ?? 0),
                'suspended' => (int) ($statusCounts[BusinessStatus::Suspended->value] ?? 0),
                'total' => (int) $statusCounts->sum(),
                'pendingPayments' => SubscriptionPayment::query()->where('status', SubscriptionPaymentStatus::Pending)->count(),
            ],
            'collected' => $this->collectedByMonth(),
            'funnel' => $this->trialFunnel(),
            'activationRate' => $this->activationToPaidRate(),
            'goneQuiet' => $this->goneQuiet(),
            'trialsEnding' => $this->trialsEnding(),
            'byType' => Business::query()
                ->selectRaw('business_type, count(*) as total')
                ->groupBy('business_type')
                ->orderByDesc('total')
                ->pluck('total', 'business_type'),
        ]);
    }

    /**
     * Confirmed payments per month, last 12 months (oldest first).
     *
     * @return Collection<int, array{label: string, amount: float}>
     */
    private function collectedByMonth(): Collection
    {
        $payments = SubscriptionPayment::query()
            ->where('status', SubscriptionPaymentStatus::Paid)
            ->where('reviewed_at', '>=', now()->startOfMonth()->subMonths(11))
            ->get(['amount', 'reviewed_at']);

        return collect(range(11, 0))->map(function (int $monthsAgo) use ($payments): array {
            $month = now()->startOfMonth()->subMonths($monthsAgo);

            return [
                'label' => $month->format('M Y'),
                'amount' => round($payments
                    ->filter(fn (SubscriptionPayment $payment) => $payment->reviewed_at->isSameMonth($month))
                    ->sum(fn (SubscriptionPayment $payment) => (float) $payment->amount), 2),
            ];
        });
    }

    /**
     * Sign-ups in the last 30 days and how far each got. The closing audit is the activation step.
     *
     * @return list<array{label: string, count: int, activation?: bool}>
     */
    private function trialFunnel(): array
    {
        $recent = Business::query()->where('created_at', '>=', now()->subDays(30));

        return [
            ['label' => 'Signed up', 'count' => (clone $recent)->count()],
            ['label' => 'Added menu', 'count' => (clone $recent)->whereHas('items')->count()],
            ['label' => 'First sale', 'count' => (clone $recent)->whereHas('orders')->count()],
            ['label' => 'First closing audit', 'count' => (clone $recent)->whereHas('audits')->count(), 'activation' => true],
            ['label' => 'Paid', 'count' => (clone $recent)->whereHas('subscriptionPayments', fn ($query) => $query->where('status', SubscriptionPaymentStatus::Paid))->count()],
        ];
    }

    /**
     * Of the shops that ever finished a closing audit, the share that paid. Null when none activated yet.
     */
    private function activationToPaidRate(): ?int
    {
        $activated = Business::query()->whereHas('audits');
        $activatedCount = (clone $activated)->count();

        if ($activatedCount === 0) {
            return null;
        }

        $paid = (clone $activated)->whereHas('subscriptionPayments', fn ($query) => $query->where('status', SubscriptionPaymentStatus::Paid))->count();

        return (int) round($paid / $activatedCount * 100);
    }

    /**
     * Paying businesses with no sale for 3+ days.
     *
     * @return Collection<int, Business>
     */
    private function goneQuiet(): Collection
    {
        return Business::query()
            ->where('status', BusinessStatus::Active)
            ->withMax('orders', 'paid_at')
            ->withMax('audits', 'date')
            ->get()
            ->filter(fn (Business $business) => $business->orders_max_paid_at === null || now()->diffInDays($business->orders_max_paid_at, true) >= 3)
            ->sortBy('orders_max_paid_at')
            ->take(6)
            ->values();
    }

    /**
     * Trials ending within 7 days; "activated" means they finished a closing audit.
     *
     * @return Collection<int, Business>
     */
    private function trialsEnding(): Collection
    {
        return Business::query()
            ->where('status', BusinessStatus::Trial)
            ->whereBetween('due_date', [now(), now()->addDays(7)])
            ->withCount(['orders', 'audits'])
            ->orderBy('due_date')
            ->limit(6)
            ->get();
    }
}
