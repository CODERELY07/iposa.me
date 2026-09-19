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
    orderNumber: 1048,

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
        this.completed = true;
    },

    newOrder() {
        this.cart = [];
        this.completed = false;
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
