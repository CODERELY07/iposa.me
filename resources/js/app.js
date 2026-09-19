import Alpine from 'alpinejs';

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
 * POS terminal: UI-only cart state backed by static menu data.
 */
Alpine.data('posTerminal', (menu) => ({
    menu,
    category: 'All',
    search: '',
    cart: [],
    payment: 'Cash',
    tendered: '',
    cartOpen: false,
    checkoutOpen: false,
    completed: false,
    processing: false,
    orderNumber: 1048,
    flashItemId: null,
    flashLineKey: null,
    toast: null,
    toastTimer: null,

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
        return this.cart.reduce((total, line) => total + line.qty * line.price, 0);
    },

    get change() {
        return Math.max(0, (parseFloat(this.tendered) || 0) - this.subtotal);
    },

    get canComplete() {
        return this.payment !== 'Cash' || (parseFloat(this.tendered) || 0) >= this.subtotal;
    },

    add(item, variant) {
        const key = `${item.id}-${variant.label}`;
        const line = this.cart.find((entry) => entry.key === key);

        if (line) {
            line.qty++;
        } else {
            this.cart.push({ key, name: item.name, variant: variant.label, price: variant.price, qty: 1, tone: item.tone });
        }

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

    decrement(line) {
        line.qty--;

        if (line.qty <= 0) {
            this.cart = this.cart.filter((entry) => entry.key !== line.key);
        }
    },

    quickCash(amount) {
        this.tendered = String(amount);
    },

    openCheckout() {
        if (this.cart.length) {
            this.checkoutOpen = true;
            this.tendered = '';
        }
    },

    complete() {
        if (this.processing || !this.canComplete) {
            return;
        }

        // Stands in for the checkout request. Keep the button locked until it answers.
        this.processing = true;
        setTimeout(() => {
            this.processing = false;
            this.completed = true;
        }, 900);
    },

    newOrder() {
        this.cart = [];
        this.completed = false;
        this.processing = false;
        this.checkoutOpen = false;
        this.cartOpen = false;
        this.payment = 'Cash';
        this.orderNumber++;
    },
}));

/**
 * Closing audit: staff type what they see on the shelf, in decimals.
 */
Alpine.data('closingAudit', (items) => ({
    items: items.map((item) => ({ ...item, counted: item.expected, touched: false })),
    submitted: false,
    saving: false,

    submit() {
        if (this.saving || this.touchedCount < this.items.length) {
            return;
        }

        this.saving = true;
        setTimeout(() => {
            this.saving = false;
            this.submitted = true;
        }, 1000);
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

    set(item, value) {
        item.counted = Math.max(0, parseFloat(value) || 0);
        item.touched = true;
    },

    confirmUnchanged(item) {
        item.touched = true;
    },
}));

Alpine.start();
