@use('App\Enums\BusinessStatus')
@use('App\Enums\SubscriptionPaymentStatus')

@php
    $daysLeft = $business->daysUntilDue();
    $billingSummary = match (true) {
        $business->status === BusinessStatus::Trial && $daysLeft !== null && $daysLeft >= 0 => 'Free trial ends '.$business->due_date->format('M j').' ('.$daysLeft.' '.\Illuminate\Support\Str::plural('day', $daysLeft).' left).',
        $business->requiresPayment() => 'Payment needed. Your register is paused until we confirm your payment.',
        $business->status === BusinessStatus::Active && $business->due_date => 'Paid until '.$business->due_date->format('M j, Y').'.',
        default => $business->status->label(),
    };
@endphp

<x-app-layout title="Settings">
    <div class="mx-auto max-w-5xl space-y-10 px-4 py-8 sm:px-8">
        <x-page-header eyebrow="Settings" :title="$business->business_name" />

        @if (session('billing_required') || $business->requiresPayment())
            <div class="rounded-2xl border border-loss-500/40 bg-loss-500/10 p-4 text-sm" role="alert">
                <span class="font-semibold">Your {{ $business->status === BusinessStatus::Trial ? 'trial has ended' : 'subscription needs payment' }}.</span>
                The register, inventory and reports are paused. Your data is safe, and you can still download it below. Send your payment to continue.
            </div>
        @endif

        @if (session('upgrade_feature'))
            <div class="rounded-2xl border border-brand-400/40 bg-brand-400/10 p-4 text-sm">
                <span class="font-semibold">That's a Negosyo feature.</span> Switch plans below to unlock expenses, the P&L ledger and ingredient links.
            </div>
        @endif

        <section class="grid gap-6 lg:grid-cols-[240px_1fr]">
            <div>
                <h2 class="font-semibold">Business</h2>
                <p class="mt-1 text-sm text-ink-500">Shows on receipts and exports.</p>
            </div>
            <form method="POST" action="{{ route('admin.settings.business') }}" class="surface grid gap-5 p-6 sm:grid-cols-2">
                @csrf
                @method('PATCH')
                <div>
                    <label class="field-label" for="business_name">Business name</label>
                    <input id="business_name" name="business_name" type="text" value="{{ old('business_name', $business->business_name) }}" required maxlength="255" class="field">
                </div>
                <div>
                    <label class="field-label" for="business_type">Type</label>
                    <select id="business_type" name="business_type" class="field">
                        @foreach ($businessTypes as $type)
                            <option @selected(old('business_type', $business->business_type) === $type)>{{ $type }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label class="field-label" for="address">Address</label>
                    <input id="address" name="address" type="text" value="{{ old('address', $business->address) }}" maxlength="255" class="field" placeholder="Street, barangay, city">
                </div>
                <div>
                    <label class="field-label" for="tin">TIN <span class="text-ink-400">(optional)</span></label>
                    <input id="tin" name="tin" type="text" value="{{ old('tin', $business->tin) }}" maxlength="30" class="field num" placeholder="000-000-000-000">
                </div>
                <div>
                    <label class="field-label" for="receipt_footer">Receipt footer</label>
                    <input id="receipt_footer" name="receipt_footer" type="text" value="{{ old('receipt_footer', $business->receipt_footer) }}" maxlength="120" class="field" placeholder="Salamat po! Balik kayo">
                </div>
                <div class="sm:col-span-2 sm:text-right">
                    <button type="submit" class="btn-primary" data-loading-text="Saving…">Save</button>
                </div>
            </form>
        </section>

        <section class="grid gap-6 lg:grid-cols-[240px_1fr]">
            <div>
                <h2 class="font-semibold">Register & closing</h2>
                <p class="mt-1 text-sm text-ink-500">How the counter works day to day.</p>
            </div>
            <form method="POST" action="{{ route('admin.settings.register') }}" class="surface grid gap-5 p-6 sm:grid-cols-2">
                @csrf
                @method('PATCH')
                <fieldset>
                    <legend class="field-label">Payment methods on the register</legend>
                    <div class="space-y-2">
                        @foreach ($paymentMethods as $method)
                            <label class="flex items-center gap-2.5 text-sm">
                                <input type="checkbox" name="payment_methods[]" value="{{ $method->value }}" @checked(in_array($method->value, old('payment_methods', $settings['payment_methods']), true))
                                    class="rounded border-ink-300 text-brand-500 focus:ring-brand-400 dark:border-white/20 dark:bg-ink-900">
                                {{ $method->label() }}
                            </label>
                        @endforeach
                    </div>
                </fieldset>
                <div class="space-y-5">
                    <div>
                        <label class="field-label" for="audit_reminder_time">Closing audit time</label>
                        <input id="audit_reminder_time" name="audit_reminder_time" type="time" value="{{ old('audit_reminder_time', $settings['audit_reminder_time']) }}" required class="field num">
                    </div>
                    <div>
                        <label class="field-label" for="default_low_threshold">Default low-stock alert</label>
                        <input id="default_low_threshold" name="default_low_threshold" type="number" step="0.25" min="0" value="{{ old('default_low_threshold', $settings['default_low_threshold']) }}" required class="field num">
                        <p class="mt-1 text-xs text-ink-500">Used for items without their own alert level.</p>
                    </div>
                </div>
                <label class="flex items-start gap-3 sm:col-span-2">
                    <input type="hidden" name="audit_pieces" value="0">
                    <input type="checkbox" name="audit_pieces" value="1" @checked(old('audit_pieces', $settings['audit_pieces']))
                        class="mt-0.5 rounded border-ink-300 text-brand-500 focus:ring-brand-400 dark:border-white/20 dark:bg-ink-900">
                    <span>
                        <span class="block text-sm font-medium">Count pieces at closing</span>
                        <span class="text-xs text-ink-500">Buns, patties and cups are counted with the liquids. Missing pieces show up the same night and are costed in your profit. Adds a few minutes to closing.</span>
                    </span>
                </label>
                <div class="sm:col-span-2 sm:text-right">
                    <button type="submit" class="btn-primary" data-loading-text="Saving…">Save</button>
                </div>
            </form>
        </section>

        <section id="billing" class="grid scroll-mt-8 gap-6 lg:grid-cols-[240px_1fr]">
            <div>
                <h2 class="font-semibold">Plan & billing</h2>
                <p class="mt-1 text-sm text-ink-500">{{ $billingSummary }}</p>
            </div>
            <div class="space-y-4">
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach ($plans as $planKey => $plan)
                        @php($isCurrent = $business->plan === $planKey)
                        <div @class(['flex flex-col rounded-2xl border p-5', 'border-brand-400 bg-brand-400/[0.06]' => $isCurrent, 'border-ink-200 dark:border-white/[0.07]' => ! $isCurrent])>
                            <div class="flex items-center justify-between">
                                <p class="font-semibold">{{ $plan['name'] }}</p>
                                @if ($isCurrent)<span class="pill bg-brand-400 text-ink-950">Current</span>@endif
                            </div>
                            <p class="mt-2"><span class="num text-2xl font-semibold">₱{{ number_format($plan['price']) }}</span><span class="text-sm text-ink-500"> / month</span></p>
                            <ul class="mt-3 flex-1 space-y-1.5 text-sm text-ink-600 dark:text-ink-300">
                                @foreach ($plan['feature_list'] as $feature)
                                    <li class="flex gap-2"><x-icon name="check" class="mt-0.5 size-4 text-gain-500" /> {{ $feature }}</li>
                                @endforeach
                            </ul>
                            @unless ($isCurrent)
                                <form method="POST" action="{{ route('admin.billing.plan') }}" class="mt-4">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="plan" value="{{ $planKey }}">
                                    <button type="submit" class="btn-ghost w-full" data-loading-text="Switching…">Switch to {{ $plan['name'] }}</button>
                                </form>
                            @endunless
                        </div>
                    @endforeach
                </div>

                <div class="surface p-5">
                    <h3 class="font-semibold">Pay for {{ $business->planDetails()['name'] }} · <span class="num">₱{{ number_format($business->monthlyPrice()) }}</span> / month</h3>
                    @if (($renewalPrice = $business->priceAtRenewal()) !== null)
                        <p class="mt-1 text-sm text-ink-500">From your next renewal: <span class="num font-medium text-ink-900 dark:text-white">₱{{ number_format($renewalPrice) }}</span> / month.</p>
                    @endif
                    <p class="mt-1 text-sm text-ink-500">
                        Send via GCash to <span class="num font-medium text-ink-900 dark:text-white">{{ $manualPayment['gcash_number'] }}</span> ({{ $manualPayment['gcash_name'] }})
                        or bank transfer to <span class="font-medium text-ink-900 dark:text-white">{{ $manualPayment['bank'] }}</span>, then enter the reference number.
                        We confirm within one business day.
                    </p>
                    @if ($hasPendingPayment)
                        <p class="mt-4 rounded-xl bg-sky-500/10 px-4 py-3 text-sm text-sky-800 dark:text-sky-200">Your payment is waiting for confirmation.</p>
                    @else
                        <form method="POST" action="{{ route('admin.billing.payments.store') }}" class="mt-4 grid gap-3 sm:grid-cols-[140px_1fr_auto] sm:items-end">
                            @csrf
                            <div>
                                <label class="field-label" for="payment_method">Paid with</label>
                                <select id="payment_method" name="method" class="field">
                                    <option value="gcash" @selected(old('method') === 'gcash')>GCash</option>
                                    <option value="bank" @selected(old('method') === 'bank')>Bank transfer</option>
                                </select>
                            </div>
                            <div>
                                <label class="field-label" for="payment_reference">Reference number</label>
                                <input id="payment_reference" name="reference" type="text" value="{{ old('reference') }}" required minlength="4" maxlength="60" class="field num" placeholder="e.g. 1009 234 567 890">
                            </div>
                            <button type="submit" class="btn-primary" data-loading-text="Sending…">Send reference</button>
                        </form>
                    @endif

                    @if ($payments->isNotEmpty())
                        <ul class="mt-5 divide-y divide-ink-100 border-t border-ink-100 text-sm dark:divide-white/[0.06] dark:border-white/[0.06]">
                            @foreach ($payments as $payment)
                                <li class="flex items-center justify-between gap-3 py-2.5">
                                    <span class="text-ink-500">{{ $payment->created_at->format('M j, Y') }} · {{ strtoupper($payment->method) }} · <span class="num">{{ $payment->reference }}</span></span>
                                    <span class="flex items-center gap-3">
                                        <span class="num">₱{{ number_format((float) $payment->amount, 2) }}</span>
                                        <span @class(['pill', 'bg-gain-500/15 text-gain-700 dark:text-gain-300' => $payment->status === SubscriptionPaymentStatus::Paid, 'bg-sky-500/15 text-sky-700 dark:text-sky-300' => $payment->status === SubscriptionPaymentStatus::Pending, 'bg-loss-500/15 text-loss-700 dark:text-loss-300' => $payment->status === SubscriptionPaymentStatus::Rejected])>{{ $payment->status->label() }}</span>
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </section>

        <section class="grid gap-6 lg:grid-cols-[240px_1fr]">
            <div>
                <h2 class="font-semibold">Your data</h2>
                <p class="mt-1 text-sm text-ink-500">It's yours. Take it anywhere, on every plan.</p>
            </div>
            <div class="surface divide-y divide-ink-100 dark:divide-white/[0.06]">
                <div class="flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm font-medium">Download everything</p>
                        <p class="text-xs text-ink-500">Menu & prices, stock, orders, closing audits, expenses and the daily ledger for this year. One zip, one CSV each.</p>
                    </div>
                    <a href="{{ route('admin.exports.download', 'all') }}" download class="btn-ghost shrink-0"><x-icon name="download" class="size-4" /> Download .zip</a>
                </div>
                <div class="flex flex-wrap gap-2 p-5 text-sm">
                    @foreach (['menu' => 'Menu & prices', 'stock' => 'Stock', 'orders' => 'Orders (this month)', 'audits' => 'Closing audits (this month)', 'expenses' => 'Expenses (this month)', 'ledger' => 'Ledger (this month)'] as $dataset => $label)
                        <a href="{{ route('admin.exports.download', $dataset) }}" download class="btn-quiet px-3 py-1.5 text-xs"><x-icon name="download" class="size-3.5" /> {{ $label }}</a>
                    @endforeach
                </div>
            </div>
        </section>
    </div>
</x-app-layout>
