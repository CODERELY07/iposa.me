<x-app-layout title="Verifications">
    <div class="mx-auto max-w-6xl space-y-6 px-4 py-8 sm:px-8">
        <x-page-header eyebrow="Accounts" title="Email verifications"
            description="People who can't open iPOSa yet because their email isn't verified. When the verification email doesn't arrive, they ask an agent here. Check it's really them (e.g. they messaged you from that email), then verify." />

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm text-ink-500">
                <span class="num font-semibold text-ink-900 dark:text-white">{{ $requestedCount }}</span> waiting for an agent ·
                <span class="num">{{ $users->total() }}</span> unverified in total
            </p>
            <form method="GET" action="{{ route('super_admin.verifications') }}" class="relative sm:w-80">
                <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-ink-400" />
                <input type="search" name="q" value="{{ $search }}" placeholder="Name or email" class="field pl-9" aria-label="Search accounts">
            </form>
        </div>

        <div class="surface overflow-hidden">
            @if ($users->isEmpty())
                <div class="px-6 py-16 text-center">
                    <p class="font-medium">{{ $search !== '' ? 'No unverified account matches.' : 'Everyone is verified.' }}</p>
                    <p class="mt-1 text-sm text-ink-500">Requests appear here when someone taps “Ask an agent to verify me”.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[760px] text-sm">
                        <thead class="table-head">
                            <tr class="border-b border-ink-200 dark:border-white/[0.07]">
                                <th class="px-5 py-3 font-semibold">Person</th>
                                <th class="px-3 py-3 font-semibold">Shop</th>
                                <th class="px-3 py-3 font-semibold">Signed up</th>
                                <th class="px-3 py-3 font-semibold">Request</th>
                                <th class="px-5 py-3"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ink-100 dark:divide-white/[0.05]">
                            @foreach ($users as $person)
                                <tr class="hover:bg-ink-50 dark:hover:bg-white/[0.02]">
                                    <td class="px-5 py-3">
                                        <p class="font-medium">{{ $person->name }}</p>
                                        <p class="text-xs text-ink-500">{{ $person->email }} · {{ ucfirst(str_replace('_', ' ', $person->role)) }}</p>
                                    </td>
                                    <td class="px-3 py-3">
                                        @if ($person->business)
                                            <a href="{{ route('super_admin.businesses.show', $person->business) }}" class="hover:underline">{{ $person->business->business_name }}</a>
                                            <p class="text-xs text-ink-500">{{ $person->business->business_type }}</p>
                                        @else
                                            <span class="text-ink-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-3 text-xs text-ink-500" title="{{ $person->created_at?->format('M j, Y g:i A') }}">{{ $person->created_at?->diffForHumans() }}</td>
                                    <td class="px-3 py-3">
                                        @if ($person->verification_requested_at)
                                            <span class="pill bg-brand-400/15 text-brand-700 dark:text-brand-300">Asked {{ $person->verification_requested_at->diffForHumans() }}</span>
                                        @else
                                            <span class="text-xs text-ink-400">No request</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 text-right">
                                        <form method="POST" action="{{ route('super_admin.users.verify', $person) }}"
                                            onsubmit="return confirm('Verify {{ e(addslashes($person->email)) }}? Only do this if you are sure the email belongs to them.')">
                                            @csrf
                                            <button type="submit" class="btn-primary px-3 py-1.5 text-xs" data-loading-text="Verifying…"><x-icon name="check" class="size-3.5" /> Verify</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($users->hasPages())
                    <div class="flex items-center justify-between border-t border-ink-100 px-5 py-3 text-xs text-ink-500 dark:border-white/[0.05]">
                        <span class="num">Showing {{ $users->firstItem() }}–{{ $users->lastItem() }} of {{ $users->total() }}</span>
                        <div class="flex gap-1">
                            @if (! $users->onFirstPage())<a href="{{ $users->previousPageUrl() }}" class="btn-ghost px-3 py-1.5 text-xs">Previous</a>@endif
                            @if ($users->hasMorePages())<a href="{{ $users->nextPageUrl() }}" class="btn-ghost px-3 py-1.5 text-xs">Next</a>@endif
                        </div>
                    </div>
                @endif
            @endif
        </div>
    </div>
</x-app-layout>
