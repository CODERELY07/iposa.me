import Alpine from 'alpinejs';
import { offlineQueueStore } from './offline-queue';
import { buildReceipt, nextTicketNumber, printReceiptData } from './offline-receipt';
import './thermal-printer';

window.Alpine = Alpine;

const peso = new Intl.NumberFormat('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

window.formatPeso = (amount) => `₱${peso.format(amount)}`;

/**
 * Theme: dark by default, light only when the viewer chose it.
 */
Alpine.store('theme', {
    dark: document.documentElement.classList.contains('dark'),

    toggle() {
        this.dark = !this.dark;
        document.documentElement.classList.toggle('dark', this.dark);

        try {
            localStorage.setItem('theme', this.dark ? 'dark' : 'light');
        } catch (e) {
            // Storage blocked: the toggle still works for this page view.
        }
    },
});

/**
 * Page loader: a top progress bar right away, plus "Please wait a moment"
 * if the next page takes longer than half a second.
 */
Alpine.store('loader', {
    visible: false,
    slow: false,
    timer: null,

    show() {
        this.visible = true;
        this.slow = false;
        clearTimeout(this.timer);
        this.timer = setTimeout(() => (this.slow = true), 500);
    },

    hide() {
        this.visible = false;
        this.slow = false;
        clearTimeout(this.timer);
    },
});

/**
 * Confirmation dialog. Replaces window.confirm/prompt everywhere: a form with
 * data-confirm="…" is intercepted below, and code can await $store.confirm.ask().
 */
Alpine.store('confirm', {
    open: false,
    title: 'Are you sure?',
    message: '',
    action: 'Confirm',
    cancelAction: 'Cancel',
    danger: false,
    phrase: null,
    typed: '',
    promptLabel: null,
    promptPlaceholder: '',
    promptRequired: false,
    note: '',
    resolver: null,

    /**
     * Returns a promise: false when cancelled, otherwise { note }.
     */
    ask(options = {}) {
        this.settle(false);

        this.title = options.title || 'Are you sure?';
        this.message = options.message || '';
        this.action = options.action || 'Confirm';
        this.cancelAction = options.cancelAction || 'Cancel';
        this.danger = Boolean(options.danger);
        this.phrase = options.phrase || null;
        this.typed = '';
        this.promptLabel = options.promptLabel || null;
        this.promptPlaceholder = options.promptPlaceholder || '';
        this.promptRequired = Boolean(options.promptRequired);
        this.note = '';
        this.open = true;

        return new Promise((resolve) => (this.resolver = resolve));
    },

    get ready() {
        if (this.phrase && this.typed.trim() !== this.phrase) {
            return false;
        }

        return !(this.promptRequired && this.note.trim() === '');
    },

    accept() {
        if (this.ready) {
            this.settle({ note: this.note.trim() });
        }
    },

    cancel() {
        this.settle(false);
    },

    settle(result) {
        this.open = false;

        const resolve = this.resolver;
        this.resolver = null;
        resolve?.(result);
    },
});

/**
 * Full screen for the register: hides the browser's own bars on a counter
 * tablet. Uses the Fullscreen API, with the webkit-prefixed one for iPad Safari.
 * iPhone Safari has no element fullscreen, so the button hides itself there.
 */
Alpine.store('fullscreen', {
    supported: Boolean(document.fullscreenEnabled || document.webkitFullscreenEnabled),
    active: false,

    sync() {
        this.active = Boolean(document.fullscreenElement || document.webkitFullscreenElement);
    },

    async enter() {
        const root = document.documentElement;

        try {
            if (root.requestFullscreen) {
                await root.requestFullscreen({ navigationUI: 'hide' });
            } else if (root.webkitRequestFullscreen) {
                root.webkitRequestFullscreen();
            }
        } catch (e) {
            // Refused by the browser (e.g. not triggered by a tap): stay as we are.
        }

        this.sync();
    },

    async exit() {
        try {
            if (document.exitFullscreen && document.fullscreenElement) {
                await document.exitFullscreen();
            } else if (document.webkitExitFullscreen && document.webkitFullscreenElement) {
                document.webkitExitFullscreen();
            }
        } catch (e) {
            // Already out of full screen.
        }

        this.sync();
    },

    toggle() {
        return this.active ? this.exit() : this.enter();
    },
});

['fullscreenchange', 'webkitfullscreenchange'].forEach((name) =>
    document.addEventListener(name, () => Alpine.store('fullscreen').sync()),
);

/**
 * Settings → pair (or forget) the Bluetooth thermal printer receipts print to
 * directly, bypassing the browser print dialog. See resources/js/thermal-printer.js.
 */
Alpine.data('thermalPrinterSettings', () => ({
    supported: false,
    paired: false,
    printerName: '',
    pairing: false,
    error: null,

    init() {
        this.supported = window.ThermalPrinter?.isSupported() ?? false;
        this.refresh();
    },

    refresh() {
        this.paired = window.ThermalPrinter?.isPaired() ?? false;
        this.printerName = window.ThermalPrinter?.pairedName() ?? '';
    },

    async pair() {
        this.pairing = true;
        this.error = null;

        try {
            await window.ThermalPrinter.pairPrinter();
            this.refresh();
        } catch (e) {
            // NotFoundError: the user closed the device picker without choosing one.
            if (e?.name !== 'NotFoundError') {
                this.error = "Couldn't pair. Make sure the printer is on, nearby, and in pairing mode, then try again.";
            }
        } finally {
            this.pairing = false;
        }
    },

    forget() {
        window.ThermalPrinter?.forgetPrinter();
        this.refresh();
    },
}));

const spinnerMarkup = '<svg class="size-4 shrink-0 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".25" stroke-width="3"/><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>';

const isPlainNavigation = (event, link) => {
    if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
        return false;
    }

    if (link.target && link.target !== '_self') {
        return false;
    }

    if (link.hasAttribute('download') || link.dataset.noLoader !== undefined || link.origin !== window.location.origin) {
        return false;
    }

    // Same-page anchors (#pricing) scroll; they don't load anything.
    return !(link.hash && link.pathname === window.location.pathname);
};

document.addEventListener('click', (event) => {
    const link = event.target.closest('a[href]');

    if (link && isPlainNavigation(event, link)) {
        Alpine.store('loader').show();
    }
});

/**
 * Any form carrying data-confirm asks in the modal first, then submits itself.
 * Runs in the capture phase, so the loading-state listener below never sees a
 * submission the person may still cancel.
 */
document.addEventListener('submit', (event) => {
    const form = event.target;

    if (!(form instanceof HTMLFormElement) || form.dataset.confirm === undefined || form.dataset.confirmed === 'yes') {
        return;
    }

    event.preventDefault();

    Alpine.store('confirm')
        .ask({
            title: form.dataset.confirmTitle,
            message: form.dataset.confirm,
            action: form.dataset.confirmAction,
            danger: form.dataset.confirmDanger !== undefined,
            phrase: form.dataset.confirmPhrase,
            promptLabel: form.dataset.confirmPromptLabel,
            promptPlaceholder: form.dataset.confirmPromptPlaceholder,
            promptRequired: form.dataset.confirmPromptRequired !== undefined,
        })
        .then((result) => {
            if (result === false) {
                return;
            }

            const noteField = form.dataset.confirmPromptName ? form.elements[form.dataset.confirmPromptName] : null;

            if (noteField) {
                noteField.value = result.note;
            }

            form.dataset.confirmed = 'yes';
            form.requestSubmit ? form.requestSubmit() : form.submit();
        });
}, true);

// Real form posts (login, sign-up, profile, logout). Forms handled by Alpine with
// @submit.prevent are already defaultPrevented here, so they are skipped.
document.addEventListener('submit', (event) => {
    const form = event.target;

    if (event.defaultPrevented || form.dataset.noLoader !== undefined) {
        return;
    }

    const button = event.submitter ?? form.querySelector('[type="submit"]');

    if (button && !button.dataset.originalHtml) {
        button.dataset.originalHtml = button.innerHTML;
        button.innerHTML = `${spinnerMarkup}<span>${button.dataset.loadingText ?? 'Please wait…'}</span>`;
        button.setAttribute('aria-busy', 'true');
        // Disable after the browser has captured the submission.
        setTimeout(() => (button.disabled = true));
    }

    Alpine.store('loader').show();
});

// Back/forward cache restores the old page as it was: reset loaders and buttons.
window.addEventListener('pageshow', (event) => {
    if (!event.persisted) {
        return;
    }

    Alpine.store('loader').hide();
    document.querySelectorAll('[data-original-html]').forEach((button) => {
        button.innerHTML = button.dataset.originalHtml;
        button.disabled = false;
        button.removeAttribute('aria-busy');
        delete button.dataset.originalHtml;
    });
});

/**
 * Busy button for prototype actions: idle → busy (spinner) → done (check) → idle.
 * Swap the timeout for a real request when the backend exists.
 */
Alpine.data('busyAction', (duration = 900, hasDoneState = true) => ({
    state: 'idle',

    get busy() {
        return this.state === 'busy';
    },

    run() {
        if (this.state !== 'idle') {
            return;
        }

        this.state = 'busy';
        setTimeout(() => {
            this.state = hasDoneState ? 'done' : 'idle';

            if (hasDoneState) {
                setTimeout(() => (this.state = 'idle'), 1600);
            }
        }, duration);
    },
}));


/**
 * JSON request with the CSRF token. Resolves { ok, status, data }; never throws on HTTP errors.
 * No answer within 12 seconds counts as "no connection" (status 0).
 */
window.sendJson = async (url, body, method = 'POST') => {
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), 12000);

    try {
        const response = await fetch(url, {
            method,
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(body),
            credentials: 'same-origin',
            signal: controller.signal,
        });
        const data = await response.json().catch(() => ({}));

        return { ok: response.ok, status: response.status, data };
    } catch (e) {
        return { ok: false, status: 0, data: {} };
    } finally {
        clearTimeout(timer);
    }
};

/**
 * First readable message from a failed JSON request.
 */
window.errorMessage = ({ status, data }) => {
    if (status === 0) {
        return 'No internet connection. Nothing was saved — check the Wi-Fi and try again.';
    }

    if (status === 419) {
        return 'Your session expired. Refresh the page and log in again.';
    }

    const firstError = data?.errors ? Object.values(data.errors).flat()[0] : null;

    return firstError || data?.message || 'Something went wrong. Nothing was saved — try again.';
};

const newUuid = () => (window.crypto?.randomUUID
    ? window.crypto.randomUUID()
    : 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
        const r = (Math.random() * 16) | 0;

        return (c === 'x' ? r : (r & 0x3) | 0x8).toString(16);
    }));

/**
 * POS terminal: cart in the browser, sale saved by the server.
 * Each attempt carries a uuid, so retrying after a dropped connection never charges twice.
 */
Alpine.data('posTerminal', ({ menu, paymentMethods, nextOrderNumber, storeUrl, receiptHeader, cashierName, warmPages }) => ({
    menu,
    paymentMethods,
    storeUrl,
    receiptHeader,
    cashierName,
    warmPages,
    category: 'All',
    search: '',
    cart: [],
    payment: paymentMethods[0]?.value ?? 'cash',
    orderType: 'dine_in',
    tendered: '',
    cartOpen: false,
    checkoutOpen: false,
    completed: false,
    processing: false,
    error: null,
    orderNumber: nextOrderNumber,
    lastOrder: null,
    uuid: newUuid(),
    flashItemId: null,
    flashLineKey: null,
    toast: null,
    toastTimer: null,

    init() {
        this.keepMyOrdersFresh();
        window.addEventListener('iposa-synced', () => this.keepMyOrdersFresh());
    },

    get categories() {
        return ['All', ...new Set(this.menu.map((item) => item.category))];
    },

    get visibleItems() {
        const term = this.search.trim().toLowerCase();

        return this.menu.filter((item) => {
            const matchesCategory = this.category === 'All' || item.category === this.category;

            return matchesCategory && (!term || item.name.toLowerCase().includes(term));
        });
    },

    get itemCount() {
        return this.cart.reduce((total, line) => total + line.qty, 0);
    },

    get subtotal() {
        return Math.round(this.cart.reduce((total, line) => total + line.qty * line.price, 0) * 100) / 100;
    },

    get isCash() {
        return this.payment === 'cash';
    },

    get paymentLabel() {
        return this.paymentMethods.find((method) => method.value === this.payment)?.label ?? this.payment;
    },

    get change() {
        return Math.max(0, (parseFloat(this.tendered) || 0) - this.subtotal);
    },

    get canComplete() {
        return !this.isCash || (parseFloat(this.tendered) || 0) >= this.subtotal;
    },

    cartChanged() {
        this.uuid = newUuid();
        this.error = null;
    },

    add(item, variant) {
        const key = `${item.id}-${variant.id}`;
        const line = this.cart.find((entry) => entry.key === key);

        if (line) {
            line.qty++;
        } else {
            this.cart.push({ key, variantId: variant.id, name: item.name, variant: variant.label, price: variant.price, qty: 1, tone: item.tone });
        }

        this.cartChanged();
        this.confirmTap(item, key, `${item.name} · ${variant.label}`);
    },

    /**
     * Every tap answers back: the tile flashes, the cart line lights up,
     * and a short "Added" note appears, so cashiers never tap twice to be sure.
     */
    confirmTap(item, key, label) {
        this.flashItemId = item.id;
        this.flashLineKey = key;
        this.toast = label;

        if (navigator.vibrate) {
            navigator.vibrate(12);
        }

        clearTimeout(this.toastTimer);
        this.toastTimer = setTimeout(() => {
            this.flashItemId = null;
            this.flashLineKey = null;
            this.toast = null;
        }, 1100);
    },

    increment(line) {
        line.qty++;
        this.cartChanged();
    },

    decrement(line) {
        line.qty--;

        if (line.qty <= 0) {
            this.cart = this.cart.filter((entry) => entry.key !== line.key);
        }

        this.cartChanged();
    },

    clearCart() {
        this.cart = [];
        this.cartChanged();
    },

    quickCash(amount) {
        this.tendered = String(amount);
    },

    openCheckout() {
        if (this.cart.length) {
            this.checkoutOpen = true;
            this.tendered = '';
            this.error = null;
        }
    },

    async complete() {
        if (this.processing || !this.canComplete) {
            return;
        }

        this.processing = true;
        this.error = null;

        const payload = {
            uuid: this.uuid,
            payment_method: this.payment,
            order_type: this.orderType,
            tendered: this.isCash ? parseFloat(this.tendered) : null,
            lines: this.cart.map((line) => ({ variant_id: line.variantId, qty: line.qty })),
        };

        const result = navigator.onLine ? await window.sendJson(this.storeUrl, payload) : { ok: false, status: 0, data: {} };

        if (result.ok) {
            this.processing = false;
            this.lastOrder = result.data.order;
            this.completed = true;
            Alpine.store('offlineQueue').flush();
            this.keepMyOrdersFresh();

            return;
        }

        if (result.status === 0) {
            await this.saveOffline(payload);

            return;
        }

        this.processing = false;
        this.error = window.errorMessage(result);
    },

    /**
     * No connection: keep the sale on this device and sync it later with the same uuid.
     */
    async saveOffline(payload) {
        try {
            const paidAt = new Date().toISOString();
            const ticket = nextTicketNumber();
            const receipt = buildReceipt({
                header: this.receiptHeader,
                cashierName: this.cashierName,
                number: ticket,
                paidAt,
                payment: this.payment,
                paymentLabel: this.paymentLabel,
                cart: this.cart,
                subtotal: this.subtotal,
                tendered: parseFloat(this.tendered) || 0,
                change: this.change,
                offline: true,
            });

            await Alpine.store('offlineQueue').queue(
                { ...payload, offline_created_at: paidAt },
                { total: this.subtotal, items: this.itemCount, ticket, lines: this.cart.map((line) => `${line.qty}× ${line.name} ${line.variant}`) },
                receipt,
            );

            this.lastOrder = { id: null, number: null, ticket, offline: true, total: this.subtotal, change: this.isCash ? this.change : null, receipt_url: null, receipt };
            this.completed = true;
        } catch (e) {
            this.error = 'No internet, and this device could not store the sale. Nothing was saved. Write it down and ring it up once online.';
        } finally {
            this.processing = false;
        }
    },

    printReceipt() {
        if (this.lastOrder?.receipt_url) {
            window.open(this.lastOrder.receipt_url, '_blank', 'width=420,height=640');
        } else if (this.lastOrder?.receipt) {
            printReceiptData(this.lastOrder.receipt);
        }
    },

    /**
     * Keep the cashier's other pages (My orders, Products) ready for when the connection drops:
     * fetch them now and again after each sale.
     */
    keepMyOrdersFresh() {
        if (navigator.onLine) {
            (this.warmPages ?? []).forEach((url) => navigator.serviceWorker?.controller?.postMessage({ type: 'cache-page', url }));
        }
    },

    newOrder() {
        this.orderNumber = (this.lastOrder?.number ?? this.orderNumber) + 1;
        this.cart = [];
        this.completed = false;
        this.processing = false;
        this.checkoutOpen = false;
        this.cartOpen = false;
        this.lastOrder = null;
        this.payment = this.paymentMethods[0]?.value ?? 'cash';
        this.cartChanged();
    },
}));

/**
 * My orders: works from the copy of the page kept on the device when the connection drops.
 * Sales and expenses saved offline are listed here and counted into the drawer total.
 */
Alpine.data('myOrders', ({ expected, loadedAt, receipts, canVoid }) => ({
    expected,
    loadedAt,
    receipts,
    canVoid,
    online: navigator.onLine,
    saving: false,
    error: null,
    savedOffline: false,
    form: { category: 'supplies', description: '', amount: '' },

    init() {
        window.addEventListener('online', () => (this.online = true));
        window.addEventListener('offline', () => (this.online = false));

        // Something just synced: reload for the real order numbers, unless the cashier is typing.
        window.addEventListener('iposa-synced', () => {
            if (navigator.onLine && !this.saving && !document.activeElement?.matches?.('input, select, textarea')) {
                window.location.reload();
            }
        });
    },

    get drawerNow() {
        const queue = Alpine.store('offlineQueue');

        return Math.round((this.expected + queue.cashWaiting - queue.cashVoidedWaiting - queue.expensesWaiting) * 100) / 100;
    },

    get hasWaiting() {
        const queue = Alpine.store('offlineQueue');

        return queue.cashWaiting > 0 || queue.cashVoidedWaiting > 0 || queue.expensesWaiting > 0;
    },

    /**
     * No connection: keep the void (or the request to the owner) on this device and send it after the sale.
     * `target` is the uuid of the sale, which the server finds even if the sale only just synced.
     */
    async queueVoid({ target, number, total, payment }) {
        const queue = Alpine.store('offlineQueue');

        if (queue.hasVoidFor(target)) {
            return;
        }

        try {
            await queue.queue(
                { uuid: newUuid() },
                { total, target, payment, direct: this.canVoid, display: this.canVoid ? 'Void' : 'Void request', lines: [`${this.canVoid ? 'Void' : 'Ask the owner to void'} ${number}`] },
                null,
                'void',
                `/pos/orders/uuid/${target}/void`,
            );
        } catch (e) {
            this.error = 'No internet, and this device could not store the void. Nothing was saved.';
        }
    },

    printSaved(orderId) {
        if (this.receipts[orderId]) {
            printReceiptData(this.receipts[orderId]);
        }
    },

    printEntry(entry) {
        if (entry.receipt) {
            printReceiptData(entry.receipt);
        }
    },

    async addExpense() {
        const amount = parseFloat(this.form.amount);

        if (this.saving || !this.form.description.trim() || !(amount > 0)) {
            return;
        }

        this.saving = true;
        this.error = null;
        this.savedOffline = false;

        const payload = {
            uuid: newUuid(),
            date: new Date().toLocaleDateString('en-CA'),
            category: this.form.category,
            description: this.form.description.trim(),
            amount,
        };
        const result = navigator.onLine ? await window.sendJson('/expenses', payload) : { ok: false, status: 0, data: {} };

        if (result.ok) {
            window.location.reload();

            return;
        }

        if (result.status === 0) {
            try {
                await Alpine.store('offlineQueue').queue(payload, { total: amount, lines: [payload.description] }, null, 'expense');
                this.savedOffline = true;
                this.form.description = '';
                this.form.amount = '';
            } catch (e) {
                this.error = 'No internet, and this device could not store the expense. Nothing was saved.';
            }
        } else {
            this.error = window.errorMessage(result);
        }

        this.saving = false;
    },
}));

/**
 * Products (cashier): restock counts. Works from the copy kept on the device when the connection drops;
 * a delivery added offline waits on this device and is sent later, with its own id so it is added once.
 */
Alpine.data('productsPage', () => ({
    online: navigator.onLine,
    saving: false,
    error: null,
    filter: '',

    init() {
        window.addEventListener('online', () => (this.online = true));
        window.addEventListener('offline', () => (this.online = false));

        window.addEventListener('iposa-synced', () => {
            if (navigator.onLine && !this.saving && !document.activeElement?.matches?.('input, select, textarea')) {
                window.location.reload();
            }
        });
    },

    /** What is waiting to sync for one product (and size), e.g. ['+2 Sack (25 kg)']. */
    waitingFor(itemId) {
        return Alpine.store('offlineQueue').restocks
            .filter((entry) => entry.summary.itemId === itemId && entry.status === 'pending')
            .map((entry) => entry.summary.display);
    },

    async restock(form, item) {
        const quantity = parseFloat(form.elements.quantity.value);

        if (this.saving || !(quantity > 0)) {
            return;
        }

        this.saving = true;
        this.error = null;

        const payload = { uuid: newUuid(), quantity };

        ['container_id', 'item_variant_id'].forEach((name) => {
            if (form.elements[name]?.value) {
                payload[name] = parseInt(form.elements[name].value, 10);
            }
        });

        const result = navigator.onLine ? await window.sendJson(form.dataset.url, payload) : { ok: false, status: 0, data: {} };

        if (result.ok) {
            window.location.reload();

            return;
        }

        if (result.status === 0) {
            const container = form.elements.container_id?.selectedOptions?.[0]?.text;
            const size = form.elements.item_variant_id?.selectedOptions?.[0]?.text;

            try {
                await Alpine.store('offlineQueue').queue(
                    payload,
                    { itemId: item.id, display: `+${quantity} ${container ?? item.unit}`, lines: [`${item.name}${size ? ` (${size})` : ''}`] },
                    null,
                    'restock',
                    form.dataset.url,
                );
                form.elements.quantity.value = 1;
            } catch (e) {
                this.error = 'No internet, and this device could not store the delivery. Nothing was saved.';
            }
        } else {
            this.error = window.errorMessage(result);
        }

        this.saving = false;
    },
}));

/**
 * Closing audit: staff type what they see on the shelf, in decimals. Saved by the server.
 */
Alpine.data('closingAudit', ({ items, storeUrl, alreadyClosed, correctionsCount, loadedAt }) => ({
    items: items.map((item) => ({
        ...item,
        counted: item.counted ?? item.expected,
        touched: item.counted !== null,
        // Containers: full ones plus how full the open one is. A correction starts from the saved number.
        rows: (item.containers ?? []).map((container) => ({ ...container, full: 0, open: 0 })),
        exact: item.counted ?? '',
        surplus: 'restock',
    })),
    storeUrl,
    loadedAt,
    uuid: newUuid(),
    online: navigator.onLine,
    discarding: false,
    startedAt: new Date().toISOString(),
    submitted: false,
    saving: false,
    error: null,
    usageCost: null,
    editing: !alreadyClosed,

    init() {
        window.addEventListener('online', () => (this.online = true));
        window.addEventListener('offline', () => (this.online = false));

        // The saved count reached the server: reload to show tonight as closed.
        this.$watch('waiting', (now, before) => {
            if (before && !before.failed && !now && !this.discarding && navigator.onLine) {
                window.location.reload();
            }
        });
    },

    /** A count saved on this device that hasn't reached the server (or was refused). */
    get waiting() {
        const entry = Alpine.store('offlineQueue').items.find((item) => item.kind === 'audit');

        return entry ? { failed: entry.status === 'failed', error: entry.error, uuid: entry.uuid } : null;
    },

    /** Sales saved on this device that go to the server before this count does. */
    get salesAhead() {
        return Alpine.store('offlineQueue').orders.filter((entry) => entry.status === 'pending').length;
    },

    async countAgain() {
        this.discarding = true;

        if (this.waiting) {
            await Alpine.store('offlineQueue').discard(this.waiting.uuid);
        }

        this.editing = true;
        this.uuid = newUuid();
    },

    async submit() {
        if (this.saving || this.touchedCount < 1) {
            return;
        }

        const remaining = this.items.length - this.touchedCount;
        const correctionNote = alreadyClosed ? ` This will be correction #${correctionsCount + 1} tonight.` : '';
        const confirmed = await Alpine.store('confirm').ask({
            title: alreadyClosed ? 'Save these corrections?' : 'Close the day?',
            message: (remaining > 0
                ? `${remaining} item${remaining === 1 ? '' : 's'} ${remaining === 1 ? "wasn't" : "weren't"} counted and will be saved as unchanged. This can't be undone tonight.`
                : `This saves tonight's counts and can't be undone tonight.`) + correctionNote,
            action: alreadyClosed ? 'Save corrections' : 'Close the day',
        });

        if (confirmed === false) {
            return;
        }

        this.saving = true;
        this.error = null;

        const payload = {
            uuid: this.uuid,
            started_at: this.startedAt,
            counts: this.items.map((item) => ({
                item_id: item.id,
                counted: item.counted,
                // Offline, "system says" may be out of date, so a liquid in recipes always carries its answer.
                surplus: item.inRecipes && (item.counted > item.expected || !navigator.onLine) ? item.surplus : null,
            })),
        };

        const result = navigator.onLine ? await window.sendJson(this.storeUrl, payload) : { ok: false, status: 0, data: {} };

        if (result.status === 0) {
            await this.saveOffline(payload);

            return;
        }

        this.saving = false;

        if (result.ok) {
            this.usageCost = result.data.audit?.usage_cost ?? null;
            this.submitted = true;

            return;
        }

        this.error = window.errorMessage(result);
    },

    /**
     * No connection: keep the count on this device. It is sent after this device's sales, with the
     * time the shelf was counted, so it closes the right day against the right numbers.
     */
    async saveOffline(payload) {
        try {
            await Alpine.store('offlineQueue').queue(
                { ...payload, counted_at: new Date().toISOString() },
                { total: 0, display: 'Closing audit', lines: [`${payload.counts.length} items counted`] },
                null,
                'audit',
                this.storeUrl,
            );
        } catch (e) {
            this.error = 'No internet, and this device could not store the count. Nothing was saved. Write the numbers down and enter them once online.';
        } finally {
            this.saving = false;
        }
    },

    get touchedCount() {
        return this.items.filter((item) => item.touched).length;
    },

    get usageValue() {
        return this.items.reduce((total, item) => total + Math.max(0, item.expected - item.counted) * item.unitCost, 0);
    },

    step(item, amount) {
        item.counted = Math.max(0, Math.round((item.counted + amount) * 100) / 100);
        item.touched = true;
    },

    /**
     * Container items: counted = Σ (full + open fraction) × size, unless an exact amount is typed.
     */
    recount(item) {
        const exact = parseFloat(item.exact);

        item.counted = item.exact !== '' && ! isNaN(exact)
            ? Math.max(0, exact)
            : Math.round(item.rows.reduce((total, row) => total + (row.full + row.open) * row.size, 0) * 1000) / 1000;
        item.touched = true;
    },

    bumpFull(item, row, delta) {
        row.full = Math.max(0, row.full + delta);
        item.exact = '';
        this.recount(item);
    },

    setOpen(item, row, fraction) {
        row.open = fraction;
        item.exact = '';
        this.recount(item);
    },

    setExact(item, value) {
        item.exact = value;
        this.recount(item);
    },

    amount(item, value) {
        return `${Number((Math.round(value * 100) / 100).toFixed(2)).toLocaleString()} ${item.unit}`;
    },

    set(item, value) {
        item.counted = Math.max(0, parseFloat(value) || 0);
        item.touched = true;
    },

    confirmUnchanged(item) {
        item.counted = item.expected;
        item.exact = '';
        item.rows.forEach((row) => { row.full = 0; row.open = 0; });
        item.touched = true;
    },
}));

/**
 * Offline sales: counted in the sidebar and register, synced whenever a connection is available.
 */
Alpine.store('offlineQueue', offlineQueueStore(window.sendJson, window.errorMessage));

const syncOfflineSales = () => Alpine.store('offlineQueue').flush();

window.addEventListener('online', syncOfflineSales);
document.addEventListener('visibilitychange', () => document.visibilityState === 'visible' && syncOfflineSales());
setInterval(syncOfflineSales, 60000);

/**
 * Logout: warn about unsynced sales, and remove the cached register page from this device.
 */
document.addEventListener('submit', (event) => {
    const form = event.target;

    if (!(form instanceof HTMLFormElement) || !form.action.endsWith('/logout')) {
        return;
    }

    const waiting = Alpine.store('offlineQueue').total;
    const clearCachedPages = () => navigator.serviceWorker?.controller?.postMessage('clear-user-pages');

    if (waiting === 0 || form.dataset.confirmed === 'yes') {
        clearCachedPages();

        return;
    }

    event.preventDefault();
    event.stopImmediatePropagation();

    Alpine.store('confirm')
        .ask({
            title: 'Offline sales not synced yet',
            message: `${waiting} sale(s) saved on this device haven't synced. They sync the next time you log in here. Log out anyway?`,
            action: 'Log out',
            danger: true,
        })
        .then((result) => {
            if (result === false) {
                return;
            }

            clearCachedPages();
            form.dataset.confirmed = 'yes';
            form.requestSubmit ? form.requestSubmit() : form.submit();
        });
}, true);

/**
 * PWA: installable app + offline register. Service workers need https (or localhost).
 */
if ('serviceWorker' in navigator && (window.isSecureContext || location.hostname === 'localhost')) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => {
            // Offline support is a bonus: the app works without it.
        });
    });
}

Alpine.start();

Alpine.store('offlineQueue').refresh().then(syncOfflineSales);
