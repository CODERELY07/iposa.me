@php
    $members = $members ?? [
        ['name' => 'Maria Santos', 'email' => 'maria@kapetburger.ph', 'role' => 'Owner', 'lastActive' => 'Now', 'shiftSales' => null],
        ['name' => 'Jessa Reyes', 'email' => 'jessa@kapetburger.ph', 'role' => 'Cashier', 'lastActive' => '2 min ago', 'shiftSales' => 7225.00],
        ['name' => 'Paolo Cruz', 'email' => 'paolo@kapetburger.ph', 'role' => 'Cashier', 'lastActive' => 'Yesterday', 'shiftSales' => null],
    ];

    $cashierPermissions = [
        ['label' => 'Run the closing audit', 'hint' => 'Recommended. Whoever closes, counts.', 'enabled' => true],
        ['label' => 'See cost prices and margins', 'hint' => 'Off keeps your margins private.', 'enabled' => false],
        ['label' => 'Void a paid order', 'hint' => 'Off sends a void request to you instead.', 'enabled' => false],
        ['label' => 'Log expenses', 'hint' => 'For ice, LPG and small cash buys.', 'enabled' => true],
    ];
@endphp

<x-app-layout title="Team">
    <div class="mx-auto max-w-5xl space-y-6 px-4 py-8 sm:px-8">
        <x-page-header eyebrow="Team" title="Who can use the register"
            description="Your plan includes 3 staff accounts. Cashiers only see the register, the closing audit and their own orders." />

        <section class="surface divide-y divide-ink-100 dark:divide-white/[0.06]">
            @foreach ($members as $member)
                <div class="flex flex-wrap items-center gap-4 px-5 py-4">
                    <span class="flex size-10 items-center justify-center rounded-full bg-ink-100 text-sm font-semibold dark:bg-white/[0.07]">{{ \Illuminate\Support\Str::of($member['name'])->explode(' ')->map(fn ($part) => $part[0])->join('') }}</span>
                    <div class="min-w-0 flex-1">
                        <p class="font-medium">{{ $member['name'] }}</p>
                        <p class="truncate text-xs text-ink-500">{{ $member['email'] }}</p>
                    </div>
                    <span @class(['pill', 'bg-brand-400/15 text-brand-700 dark:text-brand-300' => $member['role'] === 'Owner', 'bg-ink-100 text-ink-600 dark:bg-white/[0.07] dark:text-ink-300' => $member['role'] !== 'Owner'])>{{ $member['role'] }}</span>
                    <p class="w-28 text-right text-xs text-ink-500">{{ $member['lastActive'] }}</p>
                </div>
            @endforeach
        </section>

        <form @submit.prevent class="surface grid gap-3 p-5 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
            <div>
                <label class="field-label" for="invite_name">Name</label>
                <input id="invite_name" type="text" class="field" placeholder="Cashier's name">
            </div>
            <div>
                <label class="field-label" for="invite_email">Email</label>
                <input id="invite_email" type="email" class="field" placeholder="name@email.com">
            </div>
            <x-busy-button type="submit" class="btn-primary" loading-text="Sending…" done-text="Invite sent">Send invite</x-busy-button>
        </form>

        <section class="surface p-6">
            <h2 class="font-semibold">What cashiers can do</h2>
            <ul class="mt-4 divide-y divide-ink-100 dark:divide-white/[0.06]">
                @foreach ($cashierPermissions as $permission)
                    <li x-data="{ on: @js($permission['enabled']) }" class="flex items-center justify-between gap-4 py-3.5">
                        <div>
                            <p class="text-sm font-medium">{{ $permission['label'] }}</p>
                            <p class="text-xs text-ink-500">{{ $permission['hint'] }}</p>
                        </div>
                        <button type="button" role="switch" :aria-checked="on" @click="on = ! on"
                            :class="on ? 'bg-brand-400' : 'bg-ink-200 dark:bg-white/10'" class="relative h-6 w-11 shrink-0 rounded-full transition">
                            <span :class="on ? 'translate-x-5' : 'translate-x-0.5'" class="absolute left-0 top-0.5 size-5 rounded-full bg-white shadow transition"></span>
                        </button>
                    </li>
                @endforeach
            </ul>
        </section>
    </div>
</x-app-layout>
