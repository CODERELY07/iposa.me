<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Receipt #{{ $order->number }} · {{ $business->business_name }}</title>
        <style>
            /* 58mm thermal paper: ~48mm printable. Plain CSS so it prints the same everywhere. */
            @page { size: 58mm auto; margin: 3mm; }
            * { box-sizing: border-box; }
            body { margin: 0; padding: 12px; font: 12px/1.45 ui-monospace, "JetBrains Mono", Menlo, Consolas, monospace; color: #111; background: #fff; }
            .receipt { width: 100%; max-width: 280px; margin: 0 auto; }
            .center { text-align: center; }
            .muted { color: #555; }
            .rule { border-top: 1px dashed #999; margin: 8px 0; }
            .row { display: flex; justify-content: space-between; gap: 8px; }
            .row span:last-child { text-align: right; white-space: nowrap; }
            .total { font-size: 15px; font-weight: 700; }
            .void { border: 2px solid #b91c1c; color: #b91c1c; text-align: center; font-weight: 700; padding: 4px; margin: 6px 0; letter-spacing: .2em; }
            .actions { margin: 16px auto 0; max-width: 280px; display: flex; gap: 8px; }
            .actions button { flex: 1; padding: 10px; border-radius: 10px; border: 1px solid #ccc; background: #fff; font: inherit; cursor: pointer; }
            .actions button.primary { background: #ffad20; border-color: #ffad20; font-weight: 700; }
            @media print { .actions { display: none; } body { padding: 0; } }
        </style>
    </head>
    <body>
        <div class="receipt">
            <div class="center">
                <strong style="letter-spacing: .12em; text-transform: uppercase;">{{ $business->business_name }}</strong>
                @if ($business->address)<div class="muted">{{ $business->address }}</div>@endif
                @if ($business->tin)<div class="muted">TIN {{ $business->tin }}</div>@endif
            </div>

            <div class="rule"></div>

            <div class="row"><span>Order #{{ $order->number }}</span><span>{{ $order->paid_at->format('M j, Y g:i A') }}</span></div>
            <div class="muted">Cashier: {{ $order->cashier_name }}</div>

            @if ($order->isVoided())
                <div class="void">VOIDED</div>
            @endif

            <div class="rule"></div>

            @foreach ($order->lines as $line)
                <div>{{ $line->name }}{{ $line->variant_label !== 'Regular' ? ' · '.$line->variant_label : '' }}</div>
                <div class="row muted"><span>{{ $line->qty }} × ₱{{ number_format((float) $line->price, 2) }}</span><span>₱{{ number_format($line->lineTotal(), 2) }}</span></div>
            @endforeach

            <div class="rule"></div>

            <div class="row total"><span>TOTAL</span><span>₱{{ number_format((float) $order->subtotal, 2) }}</span></div>
            <div class="row"><span>{{ $order->payment_method->label() }}</span><span>₱{{ number_format((float) ($order->tendered ?? $order->subtotal), 2) }}</span></div>
            @if ($order->change !== null)
                <div class="row"><span>Change</span><span>₱{{ number_format((float) $order->change, 2) }}</span></div>
            @endif

            <div class="rule"></div>

            <div class="center muted">{{ $business->receipt_footer ?: 'Salamat po!' }}</div>
            <div class="center muted" style="margin-top: 4px; font-size: 10px;">This is not an official receipt.</div>
        </div>

        <div class="actions">
            <button type="button" onclick="window.close()">Close</button>
            <button type="button" class="primary" onclick="window.print()">Print</button>
        </div>

        <script>
            window.addEventListener('load', () => setTimeout(() => window.print(), 150));
        </script>
    </body>
</html>
