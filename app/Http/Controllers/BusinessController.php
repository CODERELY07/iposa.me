<?php

namespace App\Http\Controllers;

use App\Enums\BusinessStatus;
use App\Enums\OrderStatus;
use App\Http\Requests\Admin\UpdateBusinessProfileRequest;
use App\Http\Requests\SuperAdmin\SuspendBusinessRequest;
use App\Http\Requests\SuperAdmin\UpdateBusinessRequest;
use App\Models\Business;
use App\Models\Plan;
use App\Models\User;
use App\Services\Billing\SubscriptionService;
use App\Services\Platform\BusinessTrashService;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BusinessController extends Controller
{
    /**
     * Platform console: every business on iPOSa, filterable by status and searchable.
     */
    public function index(Request $request): View
    {
        $status = BusinessStatus::tryFrom((string) $request->query('status'));
        $search = trim((string) $request->query('q'));

        $businesses = Business::query()
            ->with('owner:id,name,email,email_verified_at')
            ->withCount(['orders as orders_last_7_days' => fn ($query) => $query->where('paid_at', '>=', now()->subDays(7))])
            ->withMax('orders', 'paid_at')
            ->search($search)
            ->when($status, fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        /** @var array<string, int> $statusCounts */
        $statusCounts = Business::query()
            ->search($search)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        return view('super_admin.businesses.index', [
            'businesses' => $businesses,
            'statusCounts' => $statusCounts,
            'trashedCount' => Business::onlyTrashed()->count(),
            'activeStatus' => $status,
            'search' => $search,
        ]);
    }

    /**
     * Platform console: one business, its health and its recent activity.
     */
    public function show(Business $business): View
    {
        $business->load(['owner', 'subscriptionPayments' => fn ($query) => $query->latest()->limit(5)]);

        $weekOrders = $business->orders()
            ->whereIn('status', OrderStatus::countedAsSales())
            ->where('paid_at', '>=', now()->subDays(7));

        return view('super_admin.businesses.show', [
            'business' => $business,
            'health' => [
                'orders' => (clone $weekOrders)->count(),
                'sales' => round((float) (clone $weekOrders)->sum('subtotal'), 2),
                'audits' => $business->audits()->where('date', '>=', now()->subDays(6)->toDateString())->count(),
                'staff' => $business->members()->where('role', User::ROLE_STAFF)->count(),
                'staffLimit' => $business->planDetails()['staff_limit'],
            ],
            'timeline' => $this->timeline($business),
        ]);
    }

    public function edit(Business $business): View
    {
        return view('super_admin.businesses.edit', [
            'business' => $business->load('owner'),
            'businessTypes' => UpdateBusinessProfileRequest::BUSINESS_TYPES,
            'plans' => Plan::query()->orderBy('sort')->orderBy('price')->get(),
            'editableStatuses' => UpdateBusinessRequest::EDITABLE_STATUSES,
        ]);
    }

    /**
     * Fix a shop's details, its subscription, or the owner's name and email.
     * Changing the email un-verifies it: the new address has not been proven,
     * and the operator can verify it with one button if they know it is right.
     */
    public function update(UpdateBusinessRequest $request, Business $business): RedirectResponse
    {
        if ($business->isSuspended()) {
            throw ValidationException::withMessages([
                'status' => "{$business->business_name} is suspended. Unsuspend it before editing.",
            ]);
        }

        $data = $request->validated();
        $notes = [];

        DB::transaction(function () use ($business, $data, &$notes): void {
            $business->update([
                'business_name' => $data['business_name'],
                'business_type' => $data['business_type'],
                'address' => $data['address'],
                'tin' => $data['tin'],
                'receipt_footer' => $data['receipt_footer'],
                'plan' => $data['plan'],
                'plan_price' => $data['plan_price'],
                'status' => BusinessStatus::from($data['status']),
                'start_date' => $data['start_date'],
                'due_date' => $data['due_date'],
            ]);

            $owner = $business->owner;

            if ($owner === null) {
                return;
            }

            $emailChanged = isset($data['owner_email']) && $data['owner_email'] !== $owner->email;

            $owner->forceFill(array_filter([
                'name' => $data['owner_name'] ?? null,
                'email' => $data['owner_email'] ?? null,
            ]))->save();

            if ($emailChanged) {
                $owner->forceFill(['email_verified_at' => null])->save();
                $notes[] = "{$owner->email} still needs to be verified — verify it for them under Verifications.";
            }
        });

        return redirect()
            ->route('super_admin.businesses.show', $business)
            ->with('status', trim('Saved. '.implode(' ', $notes)));
    }

    /**
     * The trash: shops the operator removed. Nothing here can trade or sign in.
     */
    public function trash(BusinessTrashService $trash): View
    {
        $businesses = Business::onlyTrashed()
            ->with('owner')
            ->orderByDesc('deleted_at')
            ->paginate(25);

        return view('super_admin.businesses.trash', [
            'businesses' => $businesses,
            'contents' => $businesses->mapWithKeys(fn (Business $business) => [$business->id => $trash->contents($business)]),
        ]);
    }

    /**
     * Move a shop to the trash. Reversible: its data stays, but everyone there
     * is locked out on their next click.
     */
    public function destroy(Request $request, Business $business, BusinessTrashService $trash): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        $trash->moveToTrash($business, $validated['reason'] ?? null);

        return redirect()
            ->route('super_admin.businesses.index')
            ->with('status', "{$business->business_name} is in the trash. Restore it any time, or erase it from there.");
    }

    public function restore(Business $business, BusinessTrashService $trash): RedirectResponse
    {
        $trash->restore($business);

        return redirect()
            ->route('super_admin.businesses.show', $business)
            ->with('status', "{$business->business_name} is back, as {$business->status->label()}.");
    }

    /**
     * Erase a trashed shop for good. The operator types the shop's name to confirm,
     * so this cannot happen from a stray click.
     */
    public function forceDestroy(Request $request, Business $business, BusinessTrashService $trash): RedirectResponse
    {
        $request->validate(
            ['confirmation' => ['required', 'string']],
            [],
            ['confirmation' => 'confirmation']
        );

        if (trim((string) $request->input('confirmation')) !== $business->business_name) {
            throw ValidationException::withMessages([
                'confirmation' => "Type the shop's name exactly to erase it.",
            ]);
        }

        $name = $business->business_name;
        $erased = $trash->eraseForever($business);

        return redirect()
            ->route('super_admin.businesses.trash')
            ->with('status', "{$name} is erased: {$erased['orders']} orders, {$erased['items']} items and {$erased['users']} accounts are gone.");
    }

    public function extendTrial(Request $request, Business $business, SubscriptionService $subscriptions): RedirectResponse
    {
        $validated = $request->validate(['days' => ['required', 'integer', 'min:1', 'max:60']]);

        $subscriptions->extendTrial($business, (int) $validated['days']);

        return back()->with('status', "Trial extended to {$business->due_date->format('M j, Y')}.");
    }

    public function suspend(SuspendBusinessRequest $request, Business $business, SubscriptionService $subscriptions): RedirectResponse
    {
        $subscriptions->suspend($business, $request->validated('reason'));

        return back()->with('status', "{$business->business_name} is suspended. Its users are logged out on their next click.");
    }

    public function unsuspend(Business $business, SubscriptionService $subscriptions): RedirectResponse
    {
        $subscriptions->unsuspend($business);

        return back()->with('status', "{$business->business_name} is {$business->status->label()} again.");
    }

    /**
     * Key moments, derived from real records (newest first).
     *
     * @return Collection<int, array{when: CarbonInterface, event: string, tone: string}>
     */
    private function timeline(Business $business): Collection
    {
        $events = collect([['when' => $business->created_at, 'event' => "Signed up · {$business->planDetails()['name']} trial", 'tone' => 'bg-brand-400']]);

        $firstItem = $business->items()->oldest()->first();
        $firstOrder = $business->orders()->oldest('paid_at')->first();
        $lastOrder = $business->orders()->latest('paid_at')->first();
        $firstAudit = $business->audits()->oldest('date')->first();
        $lastAudit = $business->audits()->latest('date')->first();

        if ($firstItem) {
            $events->push(['when' => $firstItem->created_at, 'event' => 'Added the first menu item', 'tone' => 'bg-gain-500']);
        }

        if ($firstOrder) {
            $events->push(['when' => $firstOrder->paid_at, 'event' => 'First sale on the register', 'tone' => 'bg-gain-500']);
        }

        if ($firstAudit) {
            $events->push(['when' => $firstAudit->submitted_at, 'event' => 'First closing audit (activated)', 'tone' => 'bg-gain-500']);
        }

        foreach ($business->members()->where('role', User::ROLE_STAFF)->get() as $staff) {
            $events->push(['when' => $staff->created_at, 'event' => "Added cashier {$staff->name}", 'tone' => 'bg-ink-400']);
        }

        foreach ($business->subscriptionPayments as $payment) {
            $events->push(['when' => $payment->created_at, 'event' => 'Payment submitted · ₱'.number_format((float) $payment->amount).' · '.$payment->status->label(), 'tone' => 'bg-sky-400']);
        }

        if ($lastOrder && $lastOrder->isNot($firstOrder)) {
            $events->push(['when' => $lastOrder->paid_at, 'event' => "Latest sale · order #{$lastOrder->number}", 'tone' => 'bg-ink-400']);
        }

        if ($lastAudit && $lastAudit->isNot($firstAudit)) {
            $events->push(['when' => $lastAudit->submitted_at, 'event' => "Latest closing audit by {$lastAudit->counted_by}", 'tone' => 'bg-ink-400']);
        }

        if ($business->suspended_at) {
            $events->push(['when' => $business->suspended_at, 'event' => "Suspended: {$business->suspension_reason}", 'tone' => 'bg-loss-500']);
        }

        return $events->filter(fn (array $event) => $event['when'] !== null)->sortByDesc('when')->values();
    }
}
