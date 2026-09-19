@php
    // Static demo tenant regardless of the {tenant} id in the URL.
    $business = $business ?? [
        'id' => request()->route('tenant'),
        'name' => "Kape't Burger",
        'type' => 'Burger & fast food',
        'city' => 'Marikina City',
        'owner' => 'Maria Santos',
        'email' => 'maria@kapetburger.ph',
        'phone' => '0917 555 0142',
        'plan' => 'Negosyo',
        'status' => 'Trial',
        'trialEnds' => 'Sep 27',
        'signedUp' => 'Sep 13, 2026',
    ];

    $health = [
        ['label' => 'Orders · 7d', 'value' => '412'],
        ['label' => 'Sales volume · 7d', 'value' => '₱58,930'],
        ['label' => 'Closing audits', 'value' => '0 of 6 days'],
        ['label' => 'Staff accounts', 'value' => '2 of 3'],
    ];

    $timeline = $timeline ?? [
        ['when' => 'Today 7:48 PM', 'event' => 'Sale #1047 by Jessa', 'tone' => 'bg-ink-400'],
        ['when' => 'Sep 16', 'event' => 'Invited cashier Paolo Cruz', 'tone' => 'bg-ink-400'],
        ['when' => 'Sep 15', 'event' => 'Linked ingredients to 6 burgers', 'tone' => 'bg-gain-500'],
        ['when' => 'Sep 14', 'event' => 'First sale on the register', 'tone' => 'bg-gain-500'],
        ['when' => 'Sep 13', 'event' => 'Imported 23 menu items from Excel', 'tone' => 'bg-gain-500'],
        ['when' => 'Sep 13', 'event' => 'Signed up · Negosyo trial', 'tone' => 'bg-brand-400'],
    ];
@endphp

<x-app-layout :title="$business['name']">
    <div class="mx-auto max-w-6xl space-y-6 px-4 py-8 sm:px-8">
        <a href="{{ route('super_admin.tenants') }}" class="inline-flex items-center gap-1 text-sm text-ink-500 hover:text-ink-900 dark:hover:text-white">
            <x-icon name="chevron-right" class="size-4 rotate-180" /> Businesses
        </a>

        <x-page-header :eyebrow="$business['type'].' · '.$business['city']" :title="$business['name']">
            <x-slot:actions>
                <span class="pill bg-brand-400/15 text-brand-700 dark:text-brand-300">{{ $business['status'] }} · ends {{ $business['trialEnds'] }}</span>
                <button type="button" class="btn-ghost">Extend trial</button>
                <button type="button" class="btn-ghost">View as owner</button>
                <button type="button" class="btn-quiet text-loss-600 dark:text-loss-400">Suspend</button>
            </x-slot:actions>
        </x-page-header>

        <div class="rounded-2xl border border-brand-400/40 bg-brand-400/10 p-4 text-sm">
            <span class="font-semibold">Not activated yet.</span>
            Lots of sales but no closing audit in 6 days. Their profit numbers leave out oil and sauces. Send the 60-second audit walkthrough.
        </div>

        <dl class="grid grid-cols-2 gap-px overflow-hidden rounded-2xl border border-ink-200 bg-ink-200 lg:grid-cols-4 dark:border-white/[0.07] dark:bg-white/[0.07]">
            @foreach ($health as $stat)
                <div class="bg-white p-5 dark:bg-ink-900">
                    <dt class="text-xs text-ink-500">{{ $stat['label'] }}</dt>
                    <dd class="num mt-1 text-xl font-semibold">{{ $stat['value'] }}</dd>
                </div>
            @endforeach
        </dl>

        <div class="grid gap-6 lg:grid-cols-[1fr_320px]">
            <section class="surface p-6">
                <h2 class="font-semibold">Activity</h2>
                <ol class="mt-5 space-y-5 border-l border-ink-200 pl-5 dark:border-white/10">
                    @foreach ($timeline as $entry)
                        <li class="relative">
                            <span class="absolute -left-[25px] top-1.5 size-2.5 rounded-full ring-4 ring-white dark:ring-ink-900 {{ $entry['tone'] }}"></span>
                            <p class="text-sm">{{ $entry['event'] }}</p>
                            <p class="text-xs text-ink-500">{{ $entry['when'] }}</p>
                        </li>
                    @endforeach
                </ol>
            </section>

            <aside class="space-y-6">
                <section class="surface p-5 text-sm">
                    <p class="eyebrow">Owner</p>
                    <p class="mt-3 font-medium">{{ $business['owner'] }}</p>
                    <p class="text-ink-500">{{ $business['email'] }}</p>
                    <p class="num text-ink-500">{{ $business['phone'] }}</p>
                    <p class="mt-3 text-xs text-ink-400">Signed up {{ $business['signedUp'] }}</p>
                </section>
                <section class="surface p-5 text-sm">
                    <p class="eyebrow">Subscription</p>
                    <dl class="mt-3 space-y-2">
                        <div class="flex justify-between"><dt class="text-ink-500">Plan</dt><dd>{{ $business['plan'] }}</dd></div>
                        <div class="flex justify-between"><dt class="text-ink-500">Price</dt><dd class="num">₱999 / mo</dd></div>
                        <div class="flex justify-between"><dt class="text-ink-500">Payment method</dt><dd>None yet</dd></div>
                        <div class="flex justify-between"><dt class="text-ink-500">Branches</dt><dd class="num">1 of 1</dd></div>
                    </dl>
                </section>
            </aside>
        </div>
    </div>
</x-app-layout>
