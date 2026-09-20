@use('App\Enums\SubscriptionPaymentStatus')

@php
    $paymentStyles = [
        SubscriptionPaymentStatus::Pending->value => 'bg-sky-500/15 text-sky-700 dark:text-sky-300',
        SubscriptionPaymentStatus::Paid->value => 'bg-gain-500/15 text-gain-700 dark:text-gain-300',
        SubscriptionPaymentStatus::Rejected->value => 'bg-loss-500/15 text-loss-700 dark:text-loss-300',
    ];
    $filters = array_merge([['label' => 'All', 'value' => null]], array_map(fn ($status) => ['label' => $status->label(), 'value' => $status->value], SubscriptionPaymentStatus::cases()));
@endphp

<x-app-layout title="Plans & billing">
    <div class="mx-auto max-w-6xl space-y-8 px-4 py-8 sm:px-8">
        <x-page-header eyebrow="Billing" title="Plans & billing"
            description="Plans live in config/plans.php (one branch each). Owners pay by GCash or bank transfer and send the reference; confirm it here to activate them for another month." />

        <div class="grid gap-4 md:grid-cols-2">
            @foreach ($plans as $planKey => $plan)
                @php($count = (int) ($subscribers[$planKey] ?? 0))
                <section class="surface p-6">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="font-semibold">{{ $plan['name'] }}</p>
                            <p class="mt-1"><span class="num text-3xl font-semibold">₱{{ number_format($plan['price']) }}</span><span class="text-sm text-ink-500"> / month</span></p>
                        </div>
                        <div class="text-right">
                            <p class="num text-xl font-semibold">{{ $count }}</p>
                            <p class="text-xs text-ink-500">paying</p>
                        </div>
                    </div>
                    <p class="num mt-4 text-xs text-ink-500">₱{{ number_format($plan['price'] * $count) }} MRR · {{ $plan['staff_limit'] === null ? 'Unlimited staff' : $plan['staff_limit'].' staff' }} · {{ config('plans.trial_days') }}-day trial</p>
                    <ul class="mt-5 space-y-2 border-t border-ink-100 pt-5 text-sm dark:border-white/[0.06]">
                        @foreach ($plan['feature_list'] as $feature)
                            <li class="flex items-center gap-2"><x-icon name="check" class="size-4 text-gain-500" /> {{ $feature }}</li>
                        @endforeach
                    </ul>
                </section>
            @endforeach
        </div>

        <section class="surface overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-ink-200 px-5 py-4 dark:border-white/[0.07]">
                <h2 class="font-semibold">Payments</h2>
                <nav class="inline-flex gap-1 rounded-xl bg-ink-100 p-1 dark:bg-white/[0.05]" aria-label="Filter payments">
                    @foreach ($filters as $filter)
                        <a href="{{ route('super_admin.plans', array_filter(['status' => $filter['value']])) }}" @class(['tab py-1 text-xs', 'tab-active' => $activeStatus?->value === $filter['value']])>{{ $filter['label'] }}</a>
                    @endforeach
                </nav>
            </div>

            @if ($payments->isEmpty())
                <p class="px-5 py-12 text-center text-sm text-ink-500">No payments here yet.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[860px] text-sm">
                        <thead class="table-head">
                            <tr class="border-b border-ink-200 dark:border-white/[0.07]">
                                <th class="px-5 py-3 font-semibold">Business</th>
                                <th class="px-3 py-3 font-semibold">Plan</th>
                                <th class="px-3 py-3 font-semibold">Method · reference</th>
                                <th class="px-3 py-3 font-semibold">Sent</th>
                                <th class="px-3 py-3 font-semibold">Status</th>
                                <th class="px-3 py-3 text-right font-semibold">Amount</th>
                                <th class="px-5 py-3"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ink-100 dark:divide-white/[0.05]">
                            @foreach ($payments as $payment)
                                <tr>
                                    <td class="px-5 py-3">
                                        <a href="{{ route('super_admin.businesses.show', $payment->business_id) }}" class="font-medium hover:underline">{{ $payment->business?->business_name }}</a>
                                        <p class="text-xs text-ink-500">by {{ $payment->submitter?->name ?? 'unknown' }}</p>
                                    </td>
                                    <td class="px-3 py-3 text-ink-500">{{ config('plans.plans.'.$payment->plan.'.name', $payment->plan) }}</td>
                                    <td class="px-3 py-3 text-ink-500">{{ strtoupper($payment->method) }} · <span class="num text-ink-900 dark:text-ink-100">{{ $payment->reference }}</span></td>
                                    <td class="num px-3 py-3 text-ink-500">{{ $payment->created_at->format('M j, g:i A') }}</td>
                                    <td class="px-3 py-3">
                                        <span class="pill {{ $paymentStyles[$payment->status->value] }}">{{ $payment->status->label() }}</span>
                                        @if ($payment->review_note)<p class="mt-1 text-xs text-ink-500">{{ $payment->review_note }}</p>@endif
                                    </td>
                                    <td class="num px-3 py-3 text-right">₱{{ number_format((float) $payment->amount, 2) }}</td>
                                    <td class="px-5 py-3">
                                        @if ($payment->status === SubscriptionPaymentStatus::Pending)
                                            <div class="flex justify-end gap-1">
                                                <form method="POST" action="{{ route('super_admin.payments.confirm', $payment) }}" onsubmit="return confirm('Confirm you received ₱{{ number_format((float) $payment->amount, 2) }} with reference {{ e(addslashes($payment->reference)) }}?')">
                                                    @csrf
                                                    <button type="submit" class="btn-primary px-3 py-1.5 text-xs" data-loading-text="Confirming…">Confirm</button>
                                                </form>
                                                <form method="POST" action="{{ route('super_admin.payments.reject', $payment) }}" onsubmit="const note = prompt('Why is it rejected? (the owner sees this)'); if (note === null) return false; this.note.value = note; return true;">
                                                    @csrf
                                                    <input type="hidden" name="note">
                                                    <button type="submit" class="btn-quiet px-3 py-1.5 text-xs text-loss-600 dark:text-loss-400" data-loading-text="…">Reject</button>
                                                </form>
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($payments->hasPages())
                    <div class="flex items-center justify-between border-t border-ink-100 px-5 py-3 text-xs text-ink-500 dark:border-white/[0.05]">
                        <span class="num">Showing {{ $payments->firstItem() }}–{{ $payments->lastItem() }} of {{ $payments->total() }}</span>
                        <div class="flex gap-1">
                            @if (! $payments->onFirstPage())<a href="{{ $payments->previousPageUrl() }}" class="btn-ghost px-3 py-1.5 text-xs">Previous</a>@endif
                            @if ($payments->hasMorePages())<a href="{{ $payments->nextPageUrl() }}" class="btn-ghost px-3 py-1.5 text-xs">Next</a>@endif
                        </div>
                    </div>
                @endif
            @endif
        </section>
    </div>
</x-app-layout>
