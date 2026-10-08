/**
 * Receipts printed from data kept on the device, for when the server can't be reached.
 *
 * A sale rung up offline has no order number yet, so its receipt carries a ticket number that is
 * only unique on this device that day (L1, L2, ...). The real order number shows in My orders once
 * the sale has synced.
 */
import { printViaBluetooth } from './thermal-printer';

const peso = (amount) => `₱${Number(amount).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char]);

/**
 * The next ticket number for this device today: L1, L2, ... Falls back to the time if storage is blocked.
 */
export function nextTicketNumber() {
    const key = `iposa-ticket-${new Date().toLocaleDateString('en-CA')}`;

    try {
        const next = (parseInt(localStorage.getItem(key) ?? '0', 10) || 0) + 1;
        localStorage.setItem(key, String(next));

        return `L${next}`;
    } catch (e) {
        return `L${new Date().toTimeString().slice(0, 5).replace(':', '')}`;
    }
}

/**
 * The same plain-data shape the server-rendered receipt hands to the Bluetooth printer.
 */
export function buildReceipt({ header, cashierName, number, paidAt, payment, paymentLabel, cart, subtotal, tendered, change, offline }) {
    return {
        ...header,
        number,
        offline,
        paidAt: new Date(paidAt).toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' }),
        cashierName,
        voided: false,
        paymentLabel,
        subtotal,
        tendered: payment === 'cash' ? tendered : subtotal,
        change: payment === 'cash' ? change : null,
        lines: cart.map((line) => ({ name: line.name, variantLabel: line.variant, qty: line.qty, price: line.price, total: Math.round(line.qty * line.price * 100) / 100 })),
    };
}

function receiptHtml(receipt) {
    const lines = receipt.lines.map((line) => {
        const label = line.variantLabel && line.variantLabel !== 'Regular' ? `${line.name} · ${line.variantLabel}` : line.name;

        return `<div>${escapeHtml(label)}</div><div class="row muted"><span>${line.qty} × ${peso(line.price)}</span><span>${peso(line.total)}</span></div>`;
    }).join('');

    return `<!DOCTYPE html><html><head><meta charset="utf-8"><title>Receipt</title><style>
        @page { size: 58mm auto; margin: 3mm; }
        * { box-sizing: border-box; }
        body { margin: 0; padding: 12px; font: 12px/1.45 ui-monospace, Menlo, Consolas, monospace; color: #111; background: #fff; }
        .receipt { width: 100%; max-width: 280px; margin: 0 auto; }
        .center { text-align: center; } .muted { color: #555; }
        .rule { border-top: 1px dashed #999; margin: 8px 0; }
        .row { display: flex; justify-content: space-between; gap: 8px; } .row span:last-child { text-align: right; white-space: nowrap; }
        .total { font-size: 15px; font-weight: 700; }
        .order-number { margin: 10px 0; } .order-number .label { font-size: 10px; letter-spacing: .2em; text-transform: uppercase; color: #555; } .order-number .big { font-size: 44px; font-weight: 800; line-height: 1.1; }
    </style></head><body><div class="receipt">
        <div class="center"><strong style="letter-spacing:.12em;text-transform:uppercase;">${escapeHtml(receipt.businessName)}</strong>
        ${receipt.address ? `<div class="muted">${escapeHtml(receipt.address)}</div>` : ''}${receipt.tin ? `<div class="muted">TIN ${escapeHtml(receipt.tin)}</div>` : ''}</div>
        <div class="rule"></div>
        <div class="center order-number"><div class="label">${receipt.offline ? 'Ticket number' : 'Order number'}</div><div class="big">#${escapeHtml(receipt.number)}</div></div>
        <div class="rule"></div>
        <div class="muted">${escapeHtml(receipt.paidAt)}</div><div class="muted">Cashier: ${escapeHtml(receipt.cashierName)}</div>
        ${receipt.voided ? '<div class="center" style="color:#b91c1c;font-weight:700;">VOIDED</div>' : ''}
        <div class="rule"></div>${lines}<div class="rule"></div>
        <div class="row total"><span>TOTAL</span><span>${peso(receipt.subtotal)}</span></div>
        <div class="row"><span>${escapeHtml(receipt.paymentLabel)}</span><span>${peso(receipt.tendered)}</span></div>
        ${receipt.change !== null ? `<div class="row"><span>Change</span><span>${peso(receipt.change)}</span></div>` : ''}
        <div class="rule"></div>
        <div class="center muted">${escapeHtml(receipt.footer || 'Salamat po!')}</div>
        <div class="center muted" style="margin-top:4px;font-size:10px;">This is not an official receipt.</div>
        ${receipt.offline ? '<div class="center muted" style="margin-top:4px;font-size:10px;">Saved offline. The order number comes after it syncs.</div>' : ''}
    </div></body></html>`;
}

let printing = false;

/**
 * Print to the paired Bluetooth printer, else through the browser's print dialog in a hidden frame
 * (no pop-up, so a blocker can't stop it).
 */
export async function printReceiptData(receipt) {
    if (printing) {
        return;
    }

    printing = true;

    try {
        const printedSilently = window.ThermalPrinter?.isSupported() && window.ThermalPrinter?.isPaired()
            ? await printViaBluetooth(receipt)
            : false;

        if (printedSilently) {
            return;
        }

        const frame = document.createElement('iframe');
        frame.setAttribute('aria-hidden', 'true');
        frame.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;';
        document.body.appendChild(frame);
        frame.srcdoc = receiptHtml(receipt);
        frame.onload = () => {
            frame.contentWindow.focus();
            frame.contentWindow.print();
            setTimeout(() => frame.remove(), 60000);
        };
    } finally {
        printing = false;
    }
}
