<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Receipt #{{ $order->number }} · {{ $business->business_name }}</title>
        @vite(['resources/js/thermal-printer.js'])
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
            .order-number { margin: 10px 0; }
            .order-number .label { font-size: 10px; letter-spacing: .2em; text-transform: uppercase; color: #555; }
            .order-number .big { font-size: 44px; font-weight: 800; line-height: 1.1; }
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

            <div class="center order-number">
                <div class="label">Order number</div>
                <div class="big">#{{ $order->number }}</div>
            </div>

            <div class="rule"></div>

            <div class="row muted"><span>{{ $order->paid_at->format('M j, Y g:i A') }}</span><span>Cashier: {{ $order->cashier_name }}</span></div>

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
            <button type="button" class="primary" onclick="printNow()">Print</button>
        </div>

        <script>
            window.__receiptOrder = @js([
                'businessName' => $business->business_name,
                'address' => $business->address,
                'tin' => $business->tin,
                'number' => $order->number,
                'paidAt' => $order->paid_at->format('M j, Y g:i A'),
                'cashierName' => $order->cashier_name,
                'voided' => $order->isVoided(),
                'paymentLabel' => $order->payment_method->label(),
                'subtotal' => (float) $order->subtotal,
                'tendered' => (float) ($order->tendered ?? $order->subtotal),
                'change' => $order->change !== null ? (float) $order->change : null,
                'footer' => $business->receipt_footer,
                'lines' => $order->lines->map(fn ($line) => [
                    'name' => $line->name,
                    'variantLabel' => $line->variant_label,
                    'qty' => $line->qty,
                    'price' => (float) $line->price,
                    'total' => $line->lineTotal(),
                ])->values(),
            ]);

            let printing = false;

            /**
             * Tries the Bluetooth printer paired in Settings first; falls back to the
             * normal browser print dialog whenever that isn't set up or doesn't work.
             */
            async function printNow() {
                if (printing) {
                    return;
                }

                printing = true;
                const printer = window.ThermalPrinter;
                const printedSilently = printer?.isSupported() && printer?.isPaired()
                    ? await printer.printViaBluetooth(window.__receiptOrder)
                    : false;

                if (!printedSilently) {
                    window.print();
                }

                printing = false;
            }

            window.addEventListener('load', () => setTimeout(printNow, 150));
        </script>
    </body>
</html>
