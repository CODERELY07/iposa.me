# Module 12 · Installable app & offline selling

Philippine internet drops. A register that stops when the wifi does is useless, so iPOSa installs to the home screen and **keeps selling offline**, then syncs by itself.

| Feature | Status |
|---|---|
| [Install & icons](#install--icons) | ✅ Built |
| [Service worker](#service-worker) | ✅ Built |
| [Offline sales queue](#offline-sales-queue) | ✅ Built |
| [Sync](#sync) | ✅ Built |
| [Connection status](#connection-status) | ✅ Built |
| [Offline audits](#whats-left) | ⬜ Not built |

## Files

| File | Role |
|---|---|
| `public/manifest.webmanifest` | Name, icons, shortcuts, colors |
| `public/icons/*`, `public/favicon.svg`, `public/favicon.ico`, `public/apple-touch-icon.png` | App marks |
| `resources/pwa/sw.js` | The worker source, with `__VERSION__` / `__PRECACHE__` placeholders |
| `app/Http/Controllers/ServiceWorkerController.php` | Serves `/sw.js` (route `pwa.service-worker`) |
| `public/offline.html` | Standalone offline page (no build assets needed) |
| `resources/js/offline-queue.js` | IndexedDB queue + the Alpine store |

---

## Install & icons

Standalone display, `start_url = /dashboard?source=pwa`, dark theme color `#0c0a09`, `lang: en-PH`, and app shortcuts straight to **Register** and **Closing audit**. Icons ship in both `any` and `maskable` forms, so Android doesn't letterbox them.

The mark is an SVG (`<x-logo-mark>`), also used as the favicon, so it stays sharp at every size without a designer.

## Service worker

`/sw.js` is generated per deploy rather than shipped static:

- **Version** = the first 12 characters of `md5_file(public/build/manifest.json)` — every build produces a new worker, which precaches the new assets and deletes the old caches on activate.
- **Precache** = the offline page, the manifest, every icon, and every file in the Vite manifest (JS and CSS).
- It's served with `no-store` and `Service-Worker-Allowed: /`, so a stale worker can never pin an old build.

Fetch strategy (GET only):

| Request | Strategy |
|---|---|
| Navigating to `/pos` | **Network first**, keeping the last good copy — so the register opens offline |
| Any other navigation | Network, falling back to `/offline.html` |
| `/build/*`, `/icons/*`, favicons | Cache first |
| `fonts.bunny.net` | Stale while revalidate |
| Anything non-GET | Untouched — sales are the queue's job, never the cache's |

## Offline sales queue

Checkout posts to `/pos/orders`. If the request can't reach the server, the sale is written to **IndexedDB** (`iposa-offline`, store `orders`, keyed by the sale's `uuid`) instead of being lost. The cashier sees the receipt total and keeps working.

Rows are stored with the cashier's user id, so **two cashiers sharing one device never see each other's queue**.

## Sync

The queue flushes when the browser comes back online, on page load, and after a successful sale — oldest first:

| Outcome | What happens |
|---|---|
| Accepted | The row is removed |
| 422 (e.g. the item was removed from the menu) | Kept as **failed** with the reason, for the owner to review, retry or discard |
| 401 / 419 | "Log in again to sync the sales saved offline." |
| 402 (subscription unpaid) | "Sales saved offline will sync once the subscription is paid." |
| Connection problem | Stop; everything stays queued and the next flush retries |

Two protections on the server side:

- The sale's `uuid` is unique per shop, so **re-syncing the same sale never charges twice** ([05 › Checkout](05-pos.md#checkout)).
- `offline_created_at` is accepted up to 7 days old and never in the future, and becomes the order's `paid_at` — so a sale made during a blackout lands on the right day in the P&L, not on the day the wifi returned.

## Connection status

The sidebar pill shows **Online**, *Syncing offline sales…*, or **Offline · register still works**, plus how many sales are waiting. Failed rows are listed with their reason and per-row **Retry** / **Discard**.

## Logout on a shared device

Counter tablets get shared, so logging out does two extra things:

- If sales are still queued, it asks first — "*N sale(s) saved offline haven't synced yet. They'll sync the next time you log in on this device. Log out anyway?*" The queue survives; it just needs that cashier to sign back in.
- It posts `clear-user-pages` to the service worker, which deletes the page cache, so the next person can't open a cached signed-in register.

Registration is skipped entirely outside a secure context (`https`, or `localhost`), and a failed registration is ignored — offline support is a bonus, and the app works without it.

---

## Tests

`PwaTest`: the manifest is served with its icons · every page links the favicon, manifest and app icons · the service worker is never cached and precaches the offline page · an offline sale keeps the time it happened · a sale time far in the past or in the future is refused · syncing the same sale twice does not double-charge.

> The service worker itself can only be exercised in a real browser — it was verified manually in Chromium with the network emulated offline. The tests above cover everything reachable from the server side.

## What's left

- Offline closing audits (the register works offline; the audit still needs internet).
- Background Sync so the queue flushes even with the app closed.
- A push notification when a queued sale fails to sync.
