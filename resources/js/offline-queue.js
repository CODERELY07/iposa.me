/**
 * Offline outbox (IndexedDB).
 *
 * When a cashier screen can't reach the server, what they did (a sale, a small expense, a delivery, a void) is stored
 * here with its uuid and replayed later, oldest first. The server treats a repeated uuid as the
 * same record, so something that actually got through before the connection dropped is never
 * recorded twice.
 *
 * Entries without a kind are sales: they were queued before expenses could be.
 */
const DB_NAME = 'iposa-offline';
const STORE = 'orders';

const ENDPOINTS = { order: '/pos/orders', expense: '/expenses' };

// Refused for a reason retrying won't fix; the cashier reviews these.
const REFUSED = [403, 404, 422];

let dbPromise = null;

function openDb() {
    if (!('indexedDB' in window)) {
        return Promise.reject(new Error('IndexedDB is not available.'));
    }

    dbPromise ??= new Promise((resolve, reject) => {
        const request = indexedDB.open(DB_NAME, 1);

        request.onupgradeneeded = () => {
            const store = request.result.createObjectStore(STORE, { keyPath: 'uuid' });
            store.createIndex('userId', 'userId');
        };
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });

    return dbPromise;
}

async function withStore(mode, callback) {
    const db = await openDb();

    return new Promise((resolve, reject) => {
        const transaction = db.transaction(STORE, mode);
        const result = callback(transaction.objectStore(STORE));

        transaction.oncomplete = () => resolve(result?.result ?? result);
        transaction.onerror = () => reject(transaction.error);
    });
}

export const currentUserId = () => document.querySelector('meta[name="user-id"]')?.content ?? null;

export const kindOf = (entry) => entry.kind ?? 'order';

export function saveEntry(entry) {
    return withStore('readwrite', (store) => store.put(entry));
}

export function removeEntry(uuid) {
    return withStore('readwrite', (store) => store.delete(uuid));
}

export async function entriesFor(userId) {
    const all = await withStore('readonly', (store) => store.getAll());

    return (all ?? []).filter((entry) => String(entry.userId) === String(userId)).sort((a, b) => a.createdAt.localeCompare(b.createdAt));
}

/**
 * Alpine store: counts for the UI and the sync loop.
 */
export function offlineQueueStore(sendJson, errorMessage) {
    return {
        pending: 0,
        failed: [],
        items: [],
        syncing: false,
        notice: null,

        get total() {
            return this.pending + this.failed.length;
        },

        /** Sales waiting to sync, oldest first. */
        get orders() {
            return this.items.filter((entry) => kindOf(entry) === 'order');
        },

        /** Small expenses waiting to sync, oldest first. */
        get expenses() {
            return this.items.filter((entry) => kindOf(entry) === 'expense');
        },

        /** Voids waiting to sync, oldest first. */
        get voids() {
            return this.items.filter((entry) => kindOf(entry) === 'void');
        },

        /** Deliveries added to the count offline, waiting to sync. */
        get restocks() {
            return this.items.filter((entry) => kindOf(entry) === 'restock');
        },

        /** Sales a void is waiting for, so the sale is shown as cancelled. */
        get voidedOrderUuids() {
            return new Set(this.voids.map((entry) => entry.summary.target));
        },

        hasVoidFor(orderUuid) {
            return this.voidedOrderUuids.has(orderUuid);
        },

        /** Cash taken in sales that haven't reached the server yet (and aren't being voided). */
        get cashWaiting() {
            return this.orders
                .filter((entry) => entry.status === 'pending' && entry.payload.payment_method === 'cash')
                .filter((entry) => !this.voids.some((voided) => voided.summary.target === entry.uuid && voided.summary.direct))
                .reduce((sum, entry) => sum + entry.summary.total, 0);
        },

        /** Cash coming back out of the drawer for already-recorded sales the cashier voided offline. */
        get cashVoidedWaiting() {
            const waiting = new Set(this.orders.map((entry) => entry.uuid));

            return this.voids
                .filter((entry) => entry.summary.direct && entry.summary.payment === 'cash' && !waiting.has(entry.summary.target))
                .reduce((sum, entry) => sum + entry.summary.total, 0);
        },

        /** Money paid out in expenses that haven't reached the server yet. */
        get expensesWaiting() {
            return this.expenses
                .filter((entry) => entry.status === 'pending')
                .reduce((sum, entry) => sum + entry.summary.total, 0);
        },

        async refresh() {
            const userId = currentUserId();

            if (!userId) {
                return;
            }

            try {
                this.items = await entriesFor(userId);
                this.pending = this.items.filter((entry) => entry.status === 'pending').length;
                this.failed = this.items.filter((entry) => entry.status === 'failed');
            } catch (e) {
                // IndexedDB unavailable (private mode on some browsers): nothing to show.
            }
        },

        async queue(payload, summary, receipt = null, kind = 'order', url = null) {
            await saveEntry({
                uuid: payload.uuid,
                userId: currentUserId(),
                kind,
                url,
                payload,
                summary,
                receipt,
                status: 'pending',
                error: null,
                createdAt: new Date().toISOString(),
            });
            await this.refresh();
        },

        /**
         * Send everything waiting, oldest first. Stops at the first connection problem.
         */
        async flush() {
            const userId = currentUserId();

            if (this.syncing || !userId || !navigator.onLine) {
                return;
            }

            this.syncing = true;
            this.notice = null;
            let sent = 0;

            try {
                const entries = (await entriesFor(userId)).filter((entry) => entry.status === 'pending');

                for (const entry of entries) {
                    const result = await sendJson(entry.url ?? ENDPOINTS[kindOf(entry)], entry.payload);

                    if (result.ok) {
                        await removeEntry(entry.uuid);
                        sent++;
                        continue;
                    }

                    if (REFUSED.includes(result.status)) {
                        // The server refused this one (e.g. item removed from the menu). Keep it for review.
                        await saveEntry({ ...entry, status: 'failed', error: errorMessage(result) });
                        continue;
                    }

                    if (result.status === 401 || result.status === 419) {
                        this.notice = 'Log in again to sync what was saved offline.';
                    } else if (result.status === 402) {
                        this.notice = 'What was saved offline will sync once the subscription is paid.';
                    }

                    // Offline again, server error, or session problem: try later.
                    break;
                }
            } catch (e) {
                // Leave everything queued; the next flush retries.
            } finally {
                this.syncing = false;
                await this.refresh();

                if (sent > 0) {
                    window.dispatchEvent(new CustomEvent('iposa-synced', { detail: { sent } }));
                }
            }
        },

        async discard(uuid) {
            await removeEntry(uuid);
            await this.refresh();
        },

        async retry(entry) {
            await saveEntry({ ...entry, status: 'pending', error: null });
            await this.refresh();
            await this.flush();
        },
    };
}
