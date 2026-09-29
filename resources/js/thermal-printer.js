/**
 * Direct Bluetooth printing to a 58mm ESC/POS thermal printer (e.g. Rongta RPP02N),
 * for shops on Chrome/Android who don't want to install a print-service app.
 *
 * Web Bluetooth only exists in Chromium browsers and needs a device paired once
 * (see the Settings page). Everywhere else — Safari, Firefox, a printer that was
 * never paired, one that's off or out of range — printViaBluetooth() resolves to
 * false, and the caller falls back to the normal browser print dialog.
 */

const SERVICE_UUID = '000018f0-0000-1000-8000-00805f9b34fb';
const CHARACTERISTIC_UUID = '00002af1-0000-1000-8000-00805f9b34fb';
const NAME_PREFIXES = ['RPP02N', 'Mprinter', 'POS', 'MTP', 'RP'];
const PAIRED_KEY = 'iposa-thermal-printer-paired';
const PAIRED_NAME_KEY = 'iposa-thermal-printer-name';
const LINE_WIDTH = 32;

export const isSupported = () => typeof navigator !== 'undefined' && 'bluetooth' in navigator;

export function isPaired() {
    try {
        return localStorage.getItem(PAIRED_KEY) === '1';
    } catch (e) {
        return false;
    }
}

export function pairedName() {
    try {
        return localStorage.getItem(PAIRED_NAME_KEY) || '';
    } catch (e) {
        return '';
    }
}

/**
 * Ask the user to pick their printer once, from a button press (Web Bluetooth
 * requires a direct user gesture). Chrome remembers the grant for this origin,
 * so every print after this can reconnect silently with no picker prompt.
 */
export async function pairPrinter() {
    const device = await navigator.bluetooth.requestDevice({
        filters: NAME_PREFIXES.map((namePrefix) => ({ namePrefix })),
        optionalServices: [SERVICE_UUID],
    });

    try {
        localStorage.setItem(PAIRED_KEY, '1');
        localStorage.setItem(PAIRED_NAME_KEY, device.name || '');
    } catch (e) {
        // Private browsing or storage blocked: pairing still works for this tab.
    }

    return device;
}

export function forgetPrinter() {
    try {
        localStorage.removeItem(PAIRED_KEY);
        localStorage.removeItem(PAIRED_NAME_KEY);
    } catch (e) {
        // Nothing to clean up if storage was never available.
    }
}

/**
 * Reconnect to a printer paired earlier, without a new picker prompt. Null
 * when nothing is paired or this browser can't silently list granted devices.
 */
async function findPairedDevice() {
    if (!isSupported() || !isPaired() || typeof navigator.bluetooth.getDevices !== 'function') {
        return null;
    }

    const devices = await navigator.bluetooth.getDevices();

    return devices[0] ?? null;
}

// ESC/POS formatting ---------------------------------------------------------

const ESC = 0x1b;
const GS = 0x1d;

const cmd = {
    init: [ESC, 0x40],
    alignLeft: [ESC, 0x61, 0x00],
    alignCenter: [ESC, 0x61, 0x01],
    boldOn: [ESC, 0x45, 0x01],
    boldOff: [ESC, 0x45, 0x00],
    doubleOn: [GS, 0x21, 0x11],
    doubleOff: [GS, 0x21, 0x00],
    feed: (lines) => [ESC, 0x64, lines],
};

/**
 * One line, a label on the left and a value packed flush right within the
 * printer's character width — the same shape the HTML receipt's rows use.
 */
function packLine(left, right, width = LINE_WIDTH) {
    const room = Math.max(0, width - right.length);
    const trimmedLeft = left.length > room ? left.slice(0, Math.max(0, room - 1)) + '…' : left;

    return trimmedLeft.padEnd(room) + right;
}

const money = (amount) => `P${Number(amount).toFixed(2)}`;

/**
 * Most cheap ESC/POS printers use a single-byte codepage, not UTF-8 — the ₱
 * sign would print as garbage bytes, so amounts use "P" here instead.
 */
function buildReceiptBytes(order) {
    const bytes = [...cmd.init];
    const push = (bunch) => bytes.push(...bunch);
    const line = (text = '') => bytes.push(...Array.from(new TextEncoder().encode(text)), 0x0a);
    const rule = () => line('-'.repeat(LINE_WIDTH));

    push(cmd.alignCenter);
    push(cmd.boldOn);
    line(order.businessName);
    push(cmd.boldOff);

    if (order.address) {
        line(order.address);
    }

    if (order.tin) {
        line(`TIN ${order.tin}`);
    }

    rule();
    push(cmd.doubleOn);
    line(`#${order.number}`);
    push(cmd.doubleOff);
    rule();

    push(cmd.alignLeft);
    line(order.paidAt);
    line(`Cashier: ${order.cashierName}`);

    if (order.voided) {
        push(cmd.alignCenter);
        push(cmd.boldOn);
        line('*** VOIDED ***');
        push(cmd.boldOff);
        push(cmd.alignLeft);
    }

    rule();

    order.lines.forEach((item) => {
        const label = item.variantLabel && item.variantLabel !== 'Regular' ? `${item.name} · ${item.variantLabel}` : item.name;
        line(label);
        line(packLine(`${item.qty} x ${money(item.price)}`, money(item.total)));
    });

    rule();
    push(cmd.boldOn);
    line(packLine('TOTAL', money(order.subtotal)));
    push(cmd.boldOff);
    line(packLine(order.paymentLabel, money(order.tendered)));

    if (order.change !== null) {
        line(packLine('Change', money(order.change)));
    }

    rule();
    push(cmd.alignCenter);
    line(order.footer || 'Salamat po!');
    line('Not an official receipt.');
    push(cmd.feed(4));

    return Uint8Array.from(bytes);
}

/**
 * GATT writes are capped well under a full receipt's size, so the bytes go
 * out in small pieces. writeWithoutResponse skips waiting for an ack per
 * chunk when the printer supports it, which is noticeably faster.
 */
async function writeInChunks(characteristic, bytes, chunkSize = 100) {
    const withoutResponse = characteristic.properties.writeWithoutResponse;

    for (let offset = 0; offset < bytes.length; offset += chunkSize) {
        const chunk = bytes.slice(offset, offset + chunkSize);

        if (withoutResponse) {
            await characteristic.writeValueWithoutResponse(chunk);
        } else {
            await characteristic.writeValue(chunk);
        }
    }
}

/**
 * Print silently to the printer paired earlier from Settings. Resolves false
 * whenever it can't — not paired, printer off or out of range, connection
 * failed — so the caller falls back to the normal browser print dialog.
 *
 * @param {object} order Plain data for one order: businessName, address, tin,
 *   number, paidAt, cashierName, voided, paymentLabel, subtotal, tendered,
 *   change, footer, lines: [{ name, variantLabel, qty, price, total }].
 */
export async function printViaBluetooth(order) {
    try {
        const device = await findPairedDevice();

        if (!device) {
            return false;
        }

        const server = device.gatt.connected ? device.gatt : await device.gatt.connect();
        const service = await server.getPrimaryService(SERVICE_UUID);
        const characteristic = await service.getCharacteristic(CHARACTERISTIC_UUID);

        await writeInChunks(characteristic, buildReceiptBytes(order));

        return true;
    } catch (e) {
        return false;
    }
}

if (typeof window !== 'undefined') {
    window.ThermalPrinter = { isSupported, isPaired, pairedName, pairPrinter, forgetPrinter, printViaBluetooth };
}
