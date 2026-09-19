{{-- Signature element: the day's P&L printed as a thermal receipt. Static sample figures. --}}
@php
    $lines = [
        ['label' => 'Sales · 132 orders', 'amount' => 18420.00, 'note' => null],
        ['label' => 'Ingredients used', 'amount' => -5730.00, 'note' => 'auto, from recipes'],
        ['label' => 'Bulk & liquids used', 'amount' => -380.00, 'note' => 'oil 0.5 btl · mayo 0.3 tub'],
        ['label' => 'Expenses today', 'amount' => -2300.00, 'note' => 'LPG, ice, wages'],
    ];
    $trueProfit = array_sum(array_column($lines, 'amount'));
@endphp

<div {{ $attributes->merge(['class' => 'relative w-full max-w-sm']) }}>
    <div class="relative rounded-t-md bg-[#fbf8f1] px-6 pb-8 pt-6 font-mono text-[12.5px] leading-relaxed text-ink-800 shadow-2xl shadow-black/40">
        <div class="text-center">
            <p class="font-semibold tracking-[0.2em]">KAPE'T BURGER</p>
            <p class="text-ink-500">Marikina · Closing report</p>
            <p class="text-ink-500">Fri 18 Sep 2026 · 9:42 PM</p>
        </div>

        <div class="my-4 border-t border-dashed border-ink-300"></div>

        <dl class="space-y-2.5">
            @foreach ($lines as $line)
                <div>
                    <div class="flex justify-between gap-4">
                        <dt>{{ $line['label'] }}</dt>
                        <dd class="tabular-nums {{ $line['amount'] < 0 ? 'text-ink-600' : '' }}">
                            {{ $line['amount'] < 0 ? '−' : '' }}₱{{ number_format(abs($line['amount']), 2) }}
                        </dd>
                    </div>
                    @if ($line['note'])
                        <p class="text-[11px] text-ink-400">{{ $line['note'] }}</p>
                    @endif
                </div>
            @endforeach
        </dl>

        <div class="my-4 border-t border-dashed border-ink-300"></div>

        <div class="flex items-end justify-between">
            <span class="font-semibold tracking-[0.15em]">TRUE PROFIT</span>
            <span class="text-xl font-semibold tabular-nums text-gain-700">₱{{ number_format($trueProfit, 2) }}</span>
        </div>
        <p class="mt-1 text-right text-[11px] text-ink-500">margin {{ number_format($trueProfit / $lines[0]['amount'] * 100, 1) }}%</p>

        <p class="mt-5 text-center text-[11px] text-ink-400">— audit done in 48 sec by Jessa —</p>
    </div>
    {{-- Torn edge --}}
    <div class="h-3 w-full bg-[#fbf8f1] [mask-image:linear-gradient(135deg,#000_50%,transparent_50%),linear-gradient(225deg,#000_50%,transparent_50%)] [mask-position:0_0] [mask-repeat:repeat-x] [mask-size:12px_12px]"></div>
</div>
