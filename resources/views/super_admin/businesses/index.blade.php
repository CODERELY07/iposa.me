@use('App\Enums\BusinessStatus')

@php
    $statusStyles = [
        BusinessStatus::Trial->value => 'bg-brand-400/15 text-brand-700 dark:text-brand-300',
        BusinessStatus::Active->value => 'bg-gain-500/15 text-gain-700 dark:text-gain-300',
        BusinessStatus::PastDue->value => 'bg-loss-500/15 text-loss-700 dark:text-loss-300',
        BusinessStatus::Suspended->value => 'bg-ink-200 text-ink-600 dark:bg-white/10 dark:text-ink-300',
    ];

    $totalCount = array_sum($statusCounts);
    $tabs = [['label' => 'All', 'value' => null, 'count' => $totalCount]];

    foreach (BusinessStatus::cases() as $case) {
        $tabs[] = ['label' => $case->label(), 'value' => $case->value, 'count' => $statusCounts[$case->value] ?? 0];
    }
@endphp

<x-app-layout title="Businesses">
    <div class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-8">
        <x-page-header eyebrow="Tenants" title="Businesses" description="One business, one branch, one subscription each." />

        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <nav class="inline-flex gap-1 overflow-x-auto rounded-xl bg-ink-100 p-1 dark:bg-white/[0.05]" aria-label="Filter by status">
                @foreach ($tabs as $tab)
                    @php($isCurrent = $activeStatus?->value === $tab['value'])
                    <a href="{{ route('super_admin.businesses.index', array_filter(['status' => $tab['value'], 'q' => $search])) }}"
                        @class(['tab flex shrink-0 items-center gap-2', 'tab-active' => $isCurrent])
                        @if ($isCurrent) aria-current="page" @endif>
                        {{ $tab['label'] }} <span class="num text-xs text-ink-400">{{ $tab['count'] }}</span>
                    </a>
                @endforeach
            </nav>

            <form method="GET" action="{{ route('super_admin.businesses.index') }}" class="relative lg:w-80">
                @if ($activeStatus)
                    <input type="hidden" name="status" value="{{ $activeStatus->value }}">
                @endif
                <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-ink-400" />
                <input type="search" name="q" value="{{ $search }}" placeholder="Business, type, owner or email" class="field pl-9" aria-label="Search businesses">
            </form>
        </div>

        <div class="surface overflow-hidden">
            @if ($businesses->isEmpty())
                <div class="px-6 py-16 text-center">
                    <p class="font-medium">
                        {{ $search !== '' || $activeStatus ? 'No businesses match these filters.' : 'No businesses yet.' }}
                    </p>
                    <p class="mt-1 text-sm text-ink-500">
                        @if ($search !== '' || $activeStatus)
                            <a href="{{ route('super_admin.businesses.index') }}" class="font-medium text-brand-600 hover:underline dark:text-brand-300">Clear filters</a>
                        @else
                            New sign-ups will appear here.
                        @endif
                    </p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1000px] text-sm">
                        <thead class="table-head">
                            <tr class="border-b border-ink-200 dark:border-white/[0.07]">
                                <th class="px-5 py-3 font-semibold">Business</th>
                                <th class="px-3 py-3 font-semibold">Owner</th>
                                <th class="px-3 py-3 font-semibold">Status</th>
                                <th class="px-3 py-3 text-right font-semibold">Orders · 7d</th>
                                <th class="px-3 py-3 font-semibold">Last sale</th>
                                <th class="px-3 py-3 font-semibold">Started</th>
                                <th class="px-3 py-3 font-semibold">Due</th>
                                <th class="px-5 py-3 text-right font-semibold">Signed up</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ink-100 dark:divide-white/[0.05]">
                            @foreach ($businesses as $business)
                                <tr class="hover:bg-ink-50 dark:hover:bg-white/[0.02]">
                                    <td class="px-5 py-3">
                                        <a href="{{ route('super_admin.businesses.show', $business) }}" class="font-medium hover:underline">{{ $business->business_name }}</a>
                                        <p class="text-xs text-ink-500">{{ $business->business_type }}</p>
                                    </td>
                                    <td class="px-3 py-3">
                                        <p class="text-ink-700 dark:text-ink-200">{{ $business->owner?->name ?? '—' }}</p>
                                        <p class="text-xs text-ink-500">
                                            {{ $business->owner?->email }}
                                            @if ($business->owner && ! $business->owner->email_verified_at)
                                                <span class="text-brand-600 dark:text-brand-300">· unverified</span>
                                            @endif
                                        </p>
                                    </td>
                                    <td class="px-3 py-3">
                                        <span class="pill {{ $statusStyles[$business->status->value] }}">{{ $business->status->label() }}</span>
                                        <p class="mt-1 text-xs text-ink-500">{{ $business->planDetails()['name'] }}</p>
                                    </td>
                                    <td class="num px-3 py-3 text-right">{{ number_format($business->orders_last_7_days) }}</td>
                                    @php($lastSale = $business->orders_max_paid_at ? \Illuminate\Support\Carbon::parse($business->orders_max_paid_at) : null)
                                    <td @class(['px-3 py-3 text-xs', 'text-loss-600 dark:text-loss-400' => $lastSale?->lt(now()->subDays(3)), 'text-ink-500' => ! $lastSale?->lt(now()->subDays(3))])>
                                        {{ $lastSale?->diffForHumans() ?? 'No sales yet' }}
                                    </td>
                                    <td class="num px-3 py-3 text-ink-500">{{ $business->start_date?->format('M j, Y') ?? '—' }}</td>
                                    <td @class(['num px-3 py-3', 'text-loss-600 dark:text-loss-400' => $business->isOverdue(), 'text-ink-500' => ! $business->isOverdue()])>
                                        {{ $business->due_date?->format('M j, Y') ?? '—' }}
                                    </td>
                                    <td class="px-5 py-3 text-right text-xs text-ink-500" title="{{ $business->created_at?->format('M j, Y g:i A') }}">
                                        {{ $business->created_at?->diffForHumans() }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="flex items-center justify-between border-t border-ink-100 px-5 py-3 text-xs text-ink-500 dark:border-white/[0.05]">
                    <span class="num">
                        Showing {{ $businesses->firstItem() }}–{{ $businesses->lastItem() }} of {{ $businesses->total() }}
                    </span>
                    <div class="flex gap-1">
                        @if ($businesses->onFirstPage())
                            <span class="btn-ghost cursor-not-allowed px-3 py-1.5 text-xs opacity-40">Previous</span>
                        @else
                            <a href="{{ $businesses->previousPageUrl() }}" class="btn-ghost px-3 py-1.5 text-xs">Previous</a>
                        @endif

                        @if ($businesses->hasMorePages())
                            <a href="{{ $businesses->nextPageUrl() }}" class="btn-ghost px-3 py-1.5 text-xs">Next</a>
                        @else
                            <span class="btn-ghost cursor-not-allowed px-3 py-1.5 text-xs opacity-40">Next</span>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
