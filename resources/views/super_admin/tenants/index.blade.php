@php
    $statusStyles = [
        'Active' => 'bg-gain-500/15 text-gain-700 dark:text-gain-300',
        'Trial' => 'bg-brand-400/15 text-brand-700 dark:text-brand-300',
        'Past due' => 'bg-loss-500/15 text-loss-700 dark:text-loss-300',
        'Suspended' => 'bg-ink-200 text-ink-600 dark:bg-white/10 dark:text-ink-300',
    ];

    $tenants = $tenants ?? [
        ['id' => 128, 'name' => "Kape't Burger", 'type' => 'Burger & fast food', 'owner' => 'Maria Santos', 'city' => 'Marikina', 'plan' => 'Negosyo', 'status' => 'Trial', 'orders7d' => 412, 'lastSale' => '2 min ago', 'mrr' => 0],
        ['id' => 126, 'name' => 'Sizzle Stop', 'type' => 'Burger & fast food', 'owner' => 'Ramon Dizon', 'city' => 'Quezon City', 'plan' => 'Negosyo', 'status' => 'Trial', 'orders7d' => 861, 'lastSale' => '5 min ago', 'mrr' => 0],
        ['id' => 97, 'name' => 'Kapihan sa Kanto', 'type' => 'Café / coffee', 'owner' => 'Lea Villanueva', 'city' => 'Baguio', 'plan' => 'Negosyo', 'status' => 'Active', 'orders7d' => 1204, 'lastSale' => '1 min ago', 'mrr' => 999],
        ['id' => 88, 'name' => 'Boba Lab', 'type' => 'Milk tea', 'owner' => 'Kevin Tan', 'city' => 'Makati', 'plan' => 'Negosyo', 'status' => 'Active', 'orders7d' => 1530, 'lastSale' => '3 min ago', 'mrr' => 999],
        ['id' => 77, 'name' => "Tita Nena's Eatery", 'type' => 'Carinderia', 'owner' => 'Nena Garcia', 'city' => 'Batangas', 'plan' => 'Tindahan', 'status' => 'Active', 'orders7d' => 0, 'lastSale' => '6 days ago', 'mrr' => 499],
        ['id' => 64, 'name' => 'Pandesal Republic', 'type' => 'Bakery', 'owner' => 'Joy Mendoza', 'city' => 'Pasig', 'plan' => 'Tindahan', 'status' => 'Active', 'orders7d' => 947, 'lastSale' => '12 min ago', 'mrr' => 499],
        ['id' => 41, 'name' => 'Brew Haven', 'type' => 'Café / coffee', 'owner' => 'Carlo Ramos', 'city' => 'Cebu City', 'plan' => 'Negosyo', 'status' => 'Active', 'orders7d' => 38, 'lastSale' => '4 days ago', 'mrr' => 999],
        ['id' => 12, 'name' => 'Milky Way Tea', 'type' => 'Milk tea', 'owner' => 'Grace Lim', 'city' => 'Davao', 'plan' => 'Negosyo', 'status' => 'Past due', 'orders7d' => 211, 'lastSale' => '3 days ago', 'mrr' => 999],
        ['id' => 9, 'name' => 'Sisig Station', 'type' => 'Burger & fast food', 'owner' => 'Mark Aquino', 'city' => 'Pampanga', 'plan' => 'Tindahan', 'status' => 'Suspended', 'orders7d' => 0, 'lastSale' => 'Aug 2', 'mrr' => 0],
    ];
@endphp

<x-app-layout title="Businesses">
    <div x-data="{ status: 'All' }" class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-8">
        <x-page-header eyebrow="Tenants" title="Businesses" description="One business, one branch, one subscription each.">
            <x-slot:actions>
                <button type="button" class="btn-ghost"><x-icon name="download" class="size-4" /> Export CSV</button>
            </x-slot:actions>
        </x-page-header>

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="inline-flex gap-1 overflow-x-auto rounded-xl bg-ink-100 p-1 dark:bg-white/[0.05]">
                @foreach (['All' => 128, 'Active' => 101, 'Trial' => 22, 'Past due' => 3, 'Suspended' => 2] as $label => $count)
                    <button type="button" @click="status = '{{ $label }}'" :class="status === '{{ $label }}' ? 'tab-active' : ''" class="tab flex shrink-0 items-center gap-2">
                        {{ $label }} <span class="num text-xs text-ink-400">{{ $count }}</span>
                    </button>
                @endforeach
            </div>
            <div class="relative sm:w-72">
                <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-ink-400" />
                <input type="search" placeholder="Business, owner or city" class="field pl-9">
            </div>
        </div>

        <div class="surface overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[860px] text-sm">
                    <thead class="table-head">
                        <tr class="border-b border-ink-200 dark:border-white/[0.07]">
                            <th class="px-5 py-3 font-semibold">Business</th>
                            <th class="px-3 py-3 font-semibold">Owner</th>
                            <th class="px-3 py-3 font-semibold">Plan</th>
                            <th class="px-3 py-3 font-semibold">Status</th>
                            <th class="px-3 py-3 text-right font-semibold">Orders · 7d</th>
                            <th class="px-3 py-3 font-semibold">Last sale</th>
                            <th class="px-5 py-3 text-right font-semibold">MRR</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink-100 dark:divide-white/[0.05]">
                        @foreach ($tenants as $tenant)
                            <tr x-show="status === 'All' || status === @js($tenant['status'])" class="hover:bg-ink-50 dark:hover:bg-white/[0.02]">
                                <td class="px-5 py-3">
                                    <a href="{{ route('super_admin.tenants.show', $tenant['id']) }}" class="font-medium hover:underline">{{ $tenant['name'] }}</a>
                                    <p class="text-xs text-ink-500">{{ $tenant['type'] }} · {{ $tenant['city'] }}</p>
                                </td>
                                <td class="px-3 py-3 text-ink-600 dark:text-ink-300">{{ $tenant['owner'] }}</td>
                                <td class="px-3 py-3">{{ $tenant['plan'] }}</td>
                                <td class="px-3 py-3"><span class="pill {{ $statusStyles[$tenant['status']] }}">{{ $tenant['status'] }}</span></td>
                                <td class="num px-3 py-3 text-right">{{ number_format($tenant['orders7d']) }}</td>
                                <td @class(['px-3 py-3 text-xs', 'text-loss-600 dark:text-loss-400' => str_contains($tenant['lastSale'], 'days') || str_contains($tenant['lastSale'], 'Aug'), 'text-ink-500' => ! (str_contains($tenant['lastSale'], 'days') || str_contains($tenant['lastSale'], 'Aug'))])>{{ $tenant['lastSale'] }}</td>
                                <td class="num px-5 py-3 text-right">{{ $tenant['mrr'] ? '₱'.number_format($tenant['mrr']) : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="flex items-center justify-between border-t border-ink-100 px-5 py-3 text-xs text-ink-500 dark:border-white/[0.05]">
                <span>Showing 9 of 128</span>
                <div class="flex gap-1">
                    <button type="button" class="btn-ghost px-3 py-1.5 text-xs" disabled>Previous</button>
                    <button type="button" class="btn-ghost px-3 py-1.5 text-xs">Next</button>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
