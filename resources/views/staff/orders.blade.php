@php
    $shift = $shift ?? ['started' => '2:00 PM', 'orders' => 47, 'cash' => 4870.00, 'gcash' => 1935.00, 'maya' => 420.00];

    $orders = $orders ?? [
        ['number' => 1047, 'time' => '9:31 PM', 'items' => 'Cheeseburger, Fries Large, Iced Tea 22oz', 'payment' => 'Cash', 'total' => 258.00, 'status' => 'Paid'],
        ['number' => 1046, 'time' => '9:24 PM', 'items' => 'Tapsilog, Calamansi Juice 16oz', 'payment' => 'GCash', 'total' => 188.00, 'status' => 'Paid'],
        ['number' => 1045, 'time' => '9:12 PM', 'items' => 'Double Cheese ×2, Iced Coffee 22oz ×2', 'payment' => 'Cash', 'total' => 516.00, 'status' => 'Paid'],
        ['number' => 1044, 'time' => '9:05 PM', 'items' => 'Classic Burger', 'payment' => 'Cash', 'total' => 89.00, 'status' => 'Void requested'],
        ['number' => 1043, 'time' => '8:58 PM', 'items' => 'Burger Steak, Bottled Water', 'payment' => 'Maya', 'total' => 144.00, 'status' => 'Paid'],
        ['number' => 1042, 'time' => '8:41 PM', 'items' => 'Nuggets 10 pc, Iced Tea 16oz ×3', 'payment' => 'Cash', 'total' => 284.00, 'status' => 'Paid'],
        ['number' => 1041, 'time' => '8:30 PM', 'items' => 'Bacon Burger, Onion Rings', 'payment' => 'GCash', 'total' => 228.00, 'status' => 'Paid'],
    ];
@endphp

<x-app-layout title="My orders">
    <div class="mx-auto max-w-4xl space-y-8 px-4 py-8 sm:px-8">
        <x-page-header eyebrow="Shift since {{ $shift['started'] }}" title="My orders today"
            description="Count your drawer against the cash total before you hand over.">
            <x-slot:actions>
                <a href="{{ route('audit') }}" class="btn-primary"><x-icon name="audit" class="size-4" /> Start closing audit</a>
            </x-slot:actions>
        </x-page-header>

        <dl class="grid grid-cols-2 gap-px overflow-hidden rounded-2xl border border-ink-200 bg-ink-200 sm:grid-cols-4 dark:border-white/[0.07] dark:bg-white/[0.07]">
            @foreach ([['Orders', $shift['orders'], false], ['Cash in drawer', $shift['cash'], true], ['GCash', $shift['gcash'], true], ['Maya', $shift['maya'], true]] as [$label, $value, $isMoney])
                <div class="bg-white p-4 dark:bg-ink-900">
                    <dt class="text-xs text-ink-500">{{ $label }}</dt>
                    <dd class="num mt-1 text-xl font-semibold">{{ $isMoney ? '₱'.number_format($value, 2) : $value }}</dd>
                </div>
            @endforeach
        </dl>

        <ul class="surface divide-y divide-ink-100 dark:divide-white/[0.06]">
            @foreach ($orders as $order)
                <li class="flex items-center gap-4 px-4 py-3.5 sm:px-5">
                    <span class="num w-14 shrink-0 text-sm font-semibold">#{{ $order['number'] }}</span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm">{{ $order['items'] }}</p>
                        <p class="text-xs text-ink-500">{{ $order['time'] }} · {{ $order['payment'] }}</p>
                    </div>
                    @if ($order['status'] !== 'Paid')
                        <span class="pill hidden bg-brand-400/15 text-brand-700 sm:inline-flex dark:text-brand-300">{{ $order['status'] }}</span>
                    @endif
                    <span @class(['num w-24 text-right text-sm font-semibold', 'text-ink-400 line-through' => $order['status'] !== 'Paid'])>₱{{ number_format($order['total'], 2) }}</span>
                    <x-busy-button class="btn-quiet hidden size-9 !px-0 sm:inline-flex" loading-text="" title="Reprint receipt" aria-label="Reprint receipt"><x-icon name="printer" class="size-4" /></x-busy-button>
                </li>
            @endforeach
        </ul>
    </div>
</x-app-layout>
