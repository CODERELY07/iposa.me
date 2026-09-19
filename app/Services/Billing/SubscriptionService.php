<?php

namespace App\Services\Billing;

use App\Enums\BusinessStatus;
use App\Enums\SubscriptionPaymentStatus;
use App\Models\Business;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Manual billing: owners submit a GCash / bank reference, the platform operator confirms it.
 */
class SubscriptionService
{
    /**
     * @throws ValidationException
     */
    public function changePlan(Business $business, string $plan): Business
    {
        $details = config('plans.plans.'.$plan);

        if ($details === null) {
            throw ValidationException::withMessages(['plan' => 'Unknown plan.']);
        }

        $staffCount = $business->members()->where('role', User::ROLE_STAFF)->count();

        if ($details['staff_limit'] !== null && $staffCount > $details['staff_limit']) {
            throw ValidationException::withMessages([
                'plan' => "{$details['name']} allows {$details['staff_limit']} staff. Remove ".($staffCount - $details['staff_limit']).' first.',
            ]);
        }

        $business->update(['plan' => $plan]);

        return $business;
    }

    /**
     * @param  array{method: string, reference: string}  $data
     *
     * @throws ValidationException
     */
    public function submitPayment(Business $business, User $owner, array $data): SubscriptionPayment
    {
        $hasPending = $business->subscriptionPayments()->where('status', SubscriptionPaymentStatus::Pending)->exists();

        if ($hasPending) {
            throw ValidationException::withMessages([
                'reference' => 'You already have a payment waiting for confirmation. We\'ll review it shortly.',
            ]);
        }

        return $business->subscriptionPayments()->create([
            'plan' => $business->plan,
            'amount' => $business->planDetails()['price'],
            'method' => $data['method'],
            'reference' => $data['reference'],
            'status' => SubscriptionPaymentStatus::Pending,
            'submitted_by' => $owner->id,
        ]);
    }

    /**
     * Payment received: the business is active for one more billing period.
     * Paying early extends from the current due date; paying late starts from today.
     *
     * @throws ValidationException
     */
    public function confirmPayment(SubscriptionPayment $payment, User $operator): SubscriptionPayment
    {
        $this->ensurePending($payment);

        return DB::transaction(function () use ($payment, $operator): SubscriptionPayment {
            $business = $payment->business;
            $periodStart = $business->due_date !== null && $business->due_date->isFuture() ? $business->due_date : now();

            $business->update([
                'status' => BusinessStatus::Active,
                'plan' => $payment->plan,
                'start_date' => $business->start_date ?? now(),
                'due_date' => $periodStart->copy()->addDays((int) config('plans.billing_period_days')),
                'suspended_at' => null,
                'suspension_reason' => null,
            ]);

            $payment->update([
                'status' => SubscriptionPaymentStatus::Paid,
                'reviewed_by' => $operator->id,
                'reviewed_at' => now(),
            ]);

            return $payment;
        });
    }

    /**
     * @throws ValidationException
     */
    public function rejectPayment(SubscriptionPayment $payment, User $operator, ?string $note): SubscriptionPayment
    {
        $this->ensurePending($payment);

        $payment->update([
            'status' => SubscriptionPaymentStatus::Rejected,
            'reviewed_by' => $operator->id,
            'reviewed_at' => now(),
            'review_note' => $note,
        ]);

        return $payment;
    }

    /**
     * Give a trial more days. Reopens an expired trial.
     *
     * @throws ValidationException
     */
    public function extendTrial(Business $business, int $days): Business
    {
        if (! in_array($business->status, [BusinessStatus::Trial, BusinessStatus::PastDue], true) || $business->subscriptionPayments()->where('status', SubscriptionPaymentStatus::Paid)->exists()) {
            throw ValidationException::withMessages(['days' => 'Only businesses that have never paid can get a longer trial.']);
        }

        $from = $business->due_date !== null && $business->due_date->isFuture() ? $business->due_date : now();

        $business->update([
            'status' => BusinessStatus::Trial,
            'due_date' => $from->copy()->addDays($days),
        ]);

        return $business;
    }

    public function suspend(Business $business, string $reason): Business
    {
        $business->update([
            'status' => BusinessStatus::Suspended,
            'suspended_at' => now(),
            'suspension_reason' => $reason,
        ]);

        return $business;
    }

    /**
     * Lift a suspension: back to past due if the bill is still open, otherwise active or trial.
     */
    public function unsuspend(Business $business): Business
    {
        $hasPaid = $business->subscriptionPayments()->where('status', SubscriptionPaymentStatus::Paid)->exists();
        $status = $business->isOverdue() ? BusinessStatus::PastDue : ($hasPaid ? BusinessStatus::Active : BusinessStatus::Trial);

        $business->update([
            'status' => $status,
            'suspended_at' => null,
            'suspension_reason' => null,
        ]);

        return $business;
    }

    /**
     * Daily job: trials and subscriptions past their due date become past due.
     */
    public function markOverdueBusinesses(): int
    {
        return Business::query()
            ->whereIn('status', [BusinessStatus::Trial, BusinessStatus::Active])
            ->whereNotNull('due_date')
            ->where('due_date', '<', now())
            ->update(['status' => BusinessStatus::PastDue]);
    }

    /**
     * @throws ValidationException
     */
    private function ensurePending(SubscriptionPayment $payment): void
    {
        if ($payment->status !== SubscriptionPaymentStatus::Pending) {
            throw ValidationException::withMessages(['payment' => 'This payment was already reviewed.']);
        }
    }
}
