/**
 * Offline sales queue (IndexedDB).
 *
 * When the register can't reach the server, the sale is stored here with its uuid and
 * replayed later. The server treats a repeated uuid as the same order, so a sale that
 * actually got through before the connection dropped is never charged twice.
 */
const DB_NAME = 'iposa-offline';
const STORE = 'orders';

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

export function saveOrder(entry) {
    return withStore('readwrite', (store) => store.put(entry));
}

export function removeOrder(uuid) {
    return withStore('readwrite', (store) => store.delete(uuid));
}

export async function ordersFor(userId) {
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
        syncing: false,
        notice: null,

        get total() {
            return this.pending + this.failed.length;
        },

        async refresh() {
            const userId = currentUserId();

            if (!userId) {
                return;
            }

            try {
                const entries = await ordersFor(userId);
                this.pending = entries.filter((entry) => entry.status === 'pending').length;
                this.failed = entries.filter((entry) => entry.status === 'failed');
            } catch (e) {
                // IndexedDB unavailable (private mode on some browsers): nothing to show.
            }
        },

        async queue(payload, summary) {
            await saveOrder({
                uuid: payload.uuid,
                userId: currentUserId(),
                payload,
                summary,
                status: 'pending',
                error: null,
                createdAt: payload.offline_created_at,
            });
            await this.refresh();
        },

        /**
         * Send waiting sales, oldest first. Stops at the first connection problem.
         */
        async flush() {
            const userId = currentUserId();

            if (this.syncing || !userId || !navigator.onLine) {
                return;
            }

            this.syncing = true;
            this.notice = null;

            try {
                const entries = (await ordersFor(userId)).filter((entry) => entry.status === 'pending');

                for (const entry of entries) {
                    const result = await sendJson('/pos/orders', entry.payload);

                    if (result.ok) {
                        await removeOrder(entry.uuid);
                        continue;
                    }

                    if (result.status === 422) {
                        // The server refused this sale (e.g. item removed from the menu). Keep it for review.
                        await saveOrder({ ...entry, status: 'failed', error: errorMessage(result) });
                        continue;
                    }

                    if (result.status === 401 || result.status === 419) {
                        this.notice = 'Log in again to sync the sales saved offline.';
                    } else if (result.status === 402) {
                        this.notice = 'Sales saved offline will sync once the subscription is paid.';
                    }

                    // Offline again, server error, or session problem: try later.
                    break;
                }
            } catch (e) {
                // Leave everything queued; the next flush retries.
            } finally {
                this.syncing = false;
                await this.refresh();
            }
        },

        async discard(uuid) {
            await removeOrder(uuid);
            await this.refresh();
        },

        async retry(entry) {
            await saveOrder({ ...entry, status: 'pending', error: null });
            await this.refresh();
            await this.flush();
        },
    };
}
