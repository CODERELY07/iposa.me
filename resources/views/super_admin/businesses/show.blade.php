@use('App\Enums\BusinessStatus')
@use('App\Enums\SubscriptionPaymentStatus')

@php
    $statusStyles = [
        BusinessStatus::Trial->value => 'bg-brand-400/15 text-brand-700 dark:text-brand-300',
        BusinessStatus::Active->value => 'bg-gain-500/15 text-gain-700 dark:text-gain-300',
        BusinessStatus::PastDue->value => 'bg-loss-500/15 text-loss-700 dark:text-loss-300',
        BusinessStatus::Suspended->value => 'bg-ink-200 text-ink-600 dark:bg-white/10 dark:text-ink-300',
    ];

    $owner = $business->owner;
    $daysUntilDue = $business->daysUntilDue();
    $canExtendTrial = in_array($business->status, [BusinessStatus::Trial, BusinessStatus::PastDue], true)
        && ! $business->subscriptionPayments->contains(fn ($payment) => $payment->status === SubscriptionPaymentStatus::Paid);
@endphp

<x-app-layout :title="$business->business_name">
    <div class="mx-auto max-w-6xl space-y-6 px-4 py-8 sm:px-8">
        <a href="{{ route('super_admin.businesses.index') }}" class="inline-flex items-center gap-1 text-sm text-ink-500 hover:text-ink-900 dark:hover:text-white">
            <x-icon name="chevron-right" class="size-4 rotate-180" /> Businesses
        </a>

        <x-page-header :eyebrow="$business->business_type.' · '.$business->planDetails()['name'].' plan'" :title="$business->business_name">
            <x-slot:actions>
                <span class="pill {{ $statusStyles[$business->status->value] }}">{{ $business->status->label() }}</span>
                @unless ($business->isSuspended())
                    <a href="{{ route('super_admin.businesses.edit', $business) }}" class="btn-ghost">Edit</a>
                @endunless
            </x-slot:actions>
        </x-page-header>

        @if ($business->isSuspended())
            <div class="rounded-2xl border border-ink-300 bg-ink-100 p-4 text-sm dark:border-white/10 dark:bg-white/[0.05]">
                <span class="font-semibold">Suspended {{ $business->suspended_at?->diffForHumans() }}.</span> {{ $business->suspension_reason }}
            </div>
        @elseif ($business->requiresPayment())
            <div class="rounded-2xl border border-loss-500/30 bg-loss-500/10 p-4 text-sm">
                <span class="font-semibold">Due date passed.</span>
                {{ $business->status === BusinessStatus::Trial ? 'The trial' : 'Payment' }} was due {{ $business->due_date?->diffForHumans() }}. Their register is paused until they pay.
            </div>
        @elseif ($health['audits'] === 0 && $health['orders'] > 0)
            <div class="rounded-2xl border border-brand-400/40 bg-brand-400/10 p-4 text-sm">
                <span class="font-semibold">Not activated yet.</span>
                Sales this week but no closing audit. Their profit leaves out oil and sauces. Send the 60-second audit walkthrough.
            </div>
        @elseif ($owner && ! $owner->email_verified_at)
            <div class="rounded-2xl border border-brand-400/40 bg-brand-400/10 p-4 text-sm">
                <span class="font-semibold">Owner hasn't verified their email.</span>
                They can't open the app until they click the link we sent to {{ $owner->email }}.
            </div>
        @endif

        <dl class="grid grid-cols-2 gap-px overflow-hidden rounded-2xl border border-ink-200 bg-ink-200 lg:grid-cols-5 dark:border-white/[0.07] dark:bg-white/[0.07]">
            <div class="bg-white p-5 dark:bg-ink-900">
                <dt class="text-xs text-ink-500">Orders · 7d</dt>
                <dd class="num mt-1 text-xl font-semibold">{{ number_format($health['orders']) }}</dd>
            </div>
            <div class="bg-white p-5 dark:bg-ink-900">
                <dt class="text-xs text-ink-500">Sales · 7d</dt>
                <dd class="num mt-1 text-xl font-semibold">₱{{ number_format($health['sales']) }}</dd>
            </div>
            <div class="bg-white p-5 dark:bg-ink-900">
                <dt class="text-xs text-ink-500">Closing audits · 7d</dt>
                <dd class="num mt-1 text-xl font-semibold">{{ $health['audits'] }} of 7</dd>
            </div>
            <div class="bg-white p-5 dark:bg-ink-900">
                <dt class="text-xs text-ink-500">Staff</dt>
                <dd class="num mt-1 text-xl font-semibold">{{ $health['staff'] }}{{ $health['staffLimit'] !== null ? ' of '.$health['staffLimit'] : '' }}</dd>
            </div>
            <div class="bg-white p-5 dark:bg-ink-900">
                <dt class="text-xs text-ink-500">{{ $daysUntilDue !== null && $daysUntilDue < 0 ? 'Days overdue' : 'Days until due' }}</dt>
                <dd @class(['num mt-1 text-xl font-semibold', 'text-loss-600 dark:text-loss-400' => $daysUntilDue !== null && $daysUntilDue < 0])>{{ $daysUntilDue === null ? '—' : abs($daysUntilDue) }}</dd>
            </div>
        </dl>

        <div class="grid gap-6 lg:grid-cols-[1fr_340px]">
            <section class="surface p-6">
                <h2 class="font-semibold">Activity</h2>
                <ol class="mt-5 space-y-5 border-l border-ink-200 pl-5 dark:border-white/10">
                    @foreach ($timeline as $entry)
                        <li class="relative">
                            <span class="absolute -left-[25px] top-1.5 size-2.5 rounded-full ring-4 ring-white dark:ring-ink-900 {{ $entry['tone'] }}"></span>
                            <p class="text-sm">{{ $entry['event'] }}</p>
                            <p class="text-xs text-ink-500">{{ $entry['when']->format('M j, Y g:i A') }} · {{ $entry['when']->diffForHumans() }}</p>
                        </li>
                    @endforeach
                </ol>
            </section>

            <aside class="space-y-6">
                <section class="surface p-5 text-sm">
                    <p class="eyebrow">Owner</p>
                    @if ($owner)
                        <p class="mt-3 font-medium">{{ $owner->name }}</p>
                        <p class="text-ink-500">{{ $owner->email }}</p>
                        <p class="mt-3">
                            <span @class(['pill', 'bg-gain-500/15 text-gain-700 dark:text-gain-300' => $owner->email_verified_at, 'bg-brand-400/15 text-brand-700 dark:text-brand-300' => ! $owner->email_verified_at])>
                                {{ $owner->email_verified_at ? 'Email verified' : 'Email not verified' }}
                            </span>
                        </p>
                    @else
                        <p class="mt-3 text-ink-500">No owner linked.</p>
                    @endif
                    <dl class="mt-4 space-y-2 border-t border-ink-100 pt-4 dark:border-white/[0.06]">
                        <div class="flex justify-between"><dt class="text-ink-500">Signed up</dt><dd class="num">{{ $business->created_at?->format('M j, Y') }}</dd></div>
                        <div class="flex justify-between"><dt class="text-ink-500">Started</dt><dd class="num">{{ $business->start_date?->format('M j, Y') ?? '—' }}</dd></div>
                        <div class="flex justify-between"><dt class="text-ink-500">Due</dt><dd class="num">{{ $business->due_date?->format('M j, Y') ?? '—' }}</dd></div>
                        <div class="flex justify-between"><dt class="text-ink-500">Price</dt><dd class="num">₱{{ number_format($business->monthlyPrice()) }} / mo</dd></div>
                    </dl>
                </section>

                <section class="surface space-y-5 p-5 text-sm">
                    <p class="eyebrow">Actions</p>

                    @if ($canExtendTrial)
                        <form method="POST" action="{{ route('super_admin.businesses.extend-trial', $business) }}" class="flex items-end gap-2">
                            @csrf
                            <div class="flex-1">
                                <label class="field-label" for="days">Extend trial by</label>
                                <select id="days" name="days" class="field">
                                    @foreach ([3, 7, 14, 30] as $days)
                                        <option value="{{ $days }}" @selected($days === 7)>{{ $days }} days</option>
                                    @endforeach
                                </select>
                            </div>
                            <button type="submit" class="btn-ghost" data-loading-text="Extending…">Extend</button>
                        </form>
                    @endif

                    @if ($business->isSuspended())
                        <form method="POST" action="{{ route('super_admin.businesses.unsuspend', $business) }}">
                            @csrf
                            <button type="submit" class="btn-primary w-full" data-loading-text="Restoring…">Lift suspension</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('super_admin.businesses.suspend', $business) }}" x-data="{ open: {{ $errors->hasAny(['reason', 'password']) ? 'true' : 'false' }} }" class="space-y-3">
                            @csrf
                            <button type="button" x-show="! open" @click="open = true" class="btn-quiet w-full text-loss-600 dark:text-loss-400">Suspend this business…</button>
                            <div x-show="open" x-cloak class="space-y-3 rounded-xl border border-loss-500/30 p-3">
                                <p class="text-xs text-ink-500">Their owner and cashiers are logged out and can't use the register until you lift it.</p>
                                <div>
                                    <label class="field-label" for="reason">Reason</label>
                                    <input id="reason" name="reason" type="text" value="{{ old('reason') }}" maxlength="255" class="field" placeholder="e.g. Chargeback, abuse report">
                                </div>
                                <div>
                                    <label class="field-label" for="password">Your password</label>
                                    <input id="password" name="password" type="password" autocomplete="current-password" class="field">
                                </div>
                                <div class="flex gap-2">
                                    <button type="button" @click="open = false" class="btn-ghost flex-1">Cancel</button>
                                    <button type="submit" class="btn flex-1 bg-loss-600 text-white hover:bg-loss-500" data-loading-text="Suspending…">Suspend</button>
                                </div>
                            </div>
                        </form>
                    @endif
                </section>

                @if ($business->subscriptionPayments->isNotEmpty())
                    <section class="surface p-5 text-sm">
                        <p class="eyebrow">Payments</p>
                        <ul class="mt-3 space-y-2">
                            @foreach ($business->subscriptionPayments as $payment)
                                <li class="flex items-center justify-between gap-2">
                                    <span class="text-ink-500">{{ $payment->created_at->format('M j') }} · <span class="num">{{ $payment->reference }}</span></span>
                                    <span class="num">₱{{ number_format((float) $payment->amount) }} · {{ $payment->status->label() }}</span>
                                </li>
                            @endforeach
                        </ul>
                        <a href="{{ route('super_admin.plans', ['status' => 'pending']) }}" class="mt-3 inline-block text-xs font-medium text-brand-600 hover:underline dark:text-brand-300">Review payments →</a>
                    </section>
                @endif
            </aside>
        </div>
    </div>
</x-app-layout>
