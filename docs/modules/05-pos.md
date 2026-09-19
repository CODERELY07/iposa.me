# Module 05 · Register (POS)

The cashier's screen. It must be fast, readable at arm's length, and impossible to double-charge.

| Feature | Status |
|---|---|
| [Menu grid](#feature-menu-grid) | 🎨 UI only |
| [Cart](#feature-cart) | 🎨 UI only (client-side, complete) |
| [Checkout & payment](#feature-checkout--payment) | 🎨 UI only |
| [Stock deduction](#feature-stock-deduction) | ⬜ Not started |
| [Receipts](#feature-receipts) | 🎨 UI only (button) |
| [My orders (shift summary)](#feature-my-orders) | 🎨 UI only |
| [Void requests](#feature-void-requests) | 🎨 UI only (status pill) |
| [Offline selling](#feature-offline-selling) | ⬜ Not started |

**Who:** `staff` and `admin` (`role:staff|admin`).

### Routes (existing)

| URI | Name | View | Demo variables |
|---|---|---|---|
| `/pos` | `pos` | `pos/index` | `$menu` |
| `/staff/orders` | `staff.orders` | `staff/orders` | `$shift`, `$orders` |

Client state: `Alpine.data('posTerminal')` in `resources/js/app.js`.

### Data model (to build)

| Table | Columns |
|---|---|
| `orders` | `id`, `business_id`, `number` (per business, sequential), `uuid` (client-generated, unique, for idempotency), `user_id` (cashier), `payment_method` (enum: `Cash`, `GCash`, `Maya`), `subtotal`, `tendered` nullable, `change` nullable, `status` (enum: `Paid`, `VoidRequested`, `Voided`), `paid_at`, `voided_by` nullable, `voided_at` nullable, timestamps |
| `order_lines` | `id`, `order_id`, `item_id`, `item_variant_id`, copies of `name` and `variant_label`, `price`, `unit_cost`, `qty` |

**Copy the name, price and cost onto each line at sale time.** Reports must never change when a menu price is edited later.

---

## Feature: Menu grid

**Status:** 🎨 UI only

Colored text tiles grouped by category (no photos, for speed). Search with the `/` key; category chips; sized items show one button per size; an "N left" pill appears when stock is low.

### Backend to build

- [ ] A controller for `pos` passing `$menu` in the shape the view expects:
  `[id, name, category, variants: [[label, price]], stockLeft]`.
- [ ] Only `kind = Menu`, not archived; eager-load `variants` and `category` (no N+1 queries).
- [ ] Tile color comes from the category (currently a map in the view; move it to `categories.color`).
- [ ] **Never send cost prices to the register.** The payload must not contain `cost`.

---

## Feature: Cart

**Status:** 🎨 UI only (works fully in the browser)

Add / + / − / clear; running total; payment method tabs. Every tap gives feedback: a tile ring, a highlighted cart line, an "Added …" toast, and a short vibration on phones.

### Backend to build

Nothing server-side until checkout. The cart lives in the browser and prices are re-checked on the server when the order is saved.

---

## Feature: Checkout & payment

**Status:** 🎨 UI only (`complete()` fakes a 900 ms request)

The cash flow asks for the cash received (Exact / ₱100 / ₱500 / ₱1000 buttons) and shows the change. GCash/Maya ask the cashier to confirm on the customer's phone. The button locks and shows "Processing sale, please wait…" until the server answers.

### Backend to build

- [ ] `POST /pos/orders` (name `pos.orders.store`, `role:staff|admin`), JSON:
  ```json
  { "uuid": "…", "payment_method": "Cash", "tendered": 500, "lines": [{ "variant_id": 12, "qty": 2 }] }
  ```
- [ ] `StoreOrderRequest`: `lines` min 1; `variant_id` exists **in this business**; `qty` integer ≥ 1; `tendered` required for Cash and ≥ the total.
- [ ] **Recompute every price from the DB.** Never trust prices sent by the browser.
- [ ] One `DB::transaction`: create the order + lines → [stock deduction](#feature-stock-deduction) → return `{ number, total, change }`.
- [ ] Idempotency: if `uuid` already exists, return that order instead of creating a second one (protects against double taps and retries).
- [ ] Order number: lock the business row (`lockForUpdate`) and increment `last_order_number`.
- [ ] Replace the `setTimeout` in `posTerminal.complete()` with `fetch()`; show server errors in the modal.

### Done when

Double-tapping "Complete sale" or retrying after a network drop still creates exactly one order.

---

## Feature: Stock deduction

**Status:** ⬜ Not started

For each order line:

| Menu item has recipe lines? | Deduct |
|---|---|
| Yes | Each linked piece: `recipe.qty × line.qty` |
| No | The menu item itself: `line.qty` |

### Backend to build

- [ ] Inside the checkout transaction: `lockForUpdate` the affected `items`, decrement `on_hand`, and write one `stock_movements` row per item (`reason = Sale`, `order_id`).
- [ ] Stock may go **negative** (a real kitchen sells before it counts). Allow it, and flag it on the dashboard.
- [ ] Store `order_lines.unit_cost` = the variant cost at the time of sale (used for COGS in [Reports](08-reports.md)).

### Tests

Selling 2 Cheeseburgers (bun, patty, cheese) lowers each piece by 2 and writes 3 movements; selling 1 Bottled Water (no recipe) lowers water by 1.

---

## Feature: Receipts

**Status:** 🎨 UI only (Receipt / Reprint buttons)

### Backend to build

- [ ] `GET /pos/orders/{order}/receipt`: a print view sized for 58mm paper using `@media print`, opened with `window.print()`.
- [ ] It shows the business name, address, TIN (if set), order number, lines, total, payment, change and receipt footer (from settings).
- [ ] Bluetooth / ESC-POS printers come after the MVP.

---

## Feature: My orders

**Status:** 🎨 UI only · **Who:** `staff`

Today's orders for the logged-in cashier, with shift totals per payment method, so they can count the drawer before handover.

### Backend to build

- [ ] A controller for `staff.orders`: orders where `user_id = me` and `paid_at` is today, newest first.
- [ ] `$shift`: `started` (first order time), `orders` count, `cash`, `gcash`, `maya` sums (paid only).
- [ ] Reprint → the receipt route above.

---

## Feature: Void requests

**Status:** 🎨 UI only ("Void requested" pill on My orders)

### Backend to build

- [ ] A cashier with `orders.void` permission voids directly; one without it creates a request (`status = VoidRequested`). See [RBAC › Cashier permissions](02-access-control.md#feature-cashier-permissions).
- [ ] The owner approves on Today → "Needs you tonight": `status = Voided`, then reverse the stock movements (`reason = Void`).
- [ ] Voided orders are excluded from sales and COGS in every report.

---

## Feature: Offline selling

**Status:** ⬜ Not started · **The landing page promises this; either build it or change the copy before launch.**

### Backend to build

- [ ] PWA: `manifest.webmanifest`, icons, and a service worker caching the app shell.
- [ ] While offline, queue orders in IndexedDB with their `uuid`; sync when back online (the idempotent endpoint above makes retries safe).
- [ ] Show an "Offline · N orders waiting to sync" status in the sidebar, where "Online · synced" is shown today.
