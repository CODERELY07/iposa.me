# iPOSa MVP Roadmap

Goal: ship a paid-ready MVP as fast as possible. Build in this order: each phase depends on the ones before it.
The UI already exists (branch `feature/ui-prototype`). Every phase is about replacing a view's static `@php` demo data with real data.

Rough sizing assumes one full-time developer: **S** = under 1 day, **M** = 1–3 days, **L** = 3–5 days.

---

## Decisions to lock before Phase 1

Make these once. Changing them later means migrations on live data.

- [ ] **Money:** store as `decimal(12,2)` in pesos, and never use floats in PHP math. Use `bcmath` or cast carefully in one helper.
- [ ] **Stock quantities:** `decimal(12,3)` so bulk counts like `4.5` or `1.25` work.
- [ ] **Tenancy:** a single database with a `business_id` column on every tenant table, plus a global scope. There is one branch per business, so there is no `branch_id`.
- [ ] **Order lines copy name, price and cost at sale time.** Reports must never change when a menu price is edited later.
- [ ] **Offline:** the landing page promises "keeps taking orders offline". Either build the offline queue (Phase 9, **L**) or change that copy before launch.

---

## Phase 0: Unblock what's broken · S

- [ ] Registration currently fails: `users.role` is required and Breeze never sets it. Registration must create a **Business** and an **admin** user (see Phase 1).
- [ ] Add a `role` default to `UserFactory` (e.g. `'admin'`) so the Breeze tests pass again.
- [ ] Turn roles into a PHP enum (`App\Enums\Role`: `SuperAdmin`, `Admin`, `Staff`) and use it in `RoleMiddleware`, routes and the user cast.
- [ ] Run the full suite green: `php artisan test --compact`.

**Done when:** a new person can sign up, lands on `/admin`, and all tests pass.

---

## Phase 1: Businesses (tenancy) · M

- [ ] Migration + model `Business`: `name`, `type`, `address`, `tin`, `receipt_footer`, `plan`, `status` (trial/active/past_due/suspended), `trial_ends_at`, `settings` (JSON: payment methods, audit reminder time, cashier permissions).
- [ ] `users.business_id` (nullable; super_admin has none).
- [ ] `BelongsToBusiness` trait: global scope on `business_id` + auto-fill on create.
- [ ] Middleware: block suspended businesses; show a trial-ended screen with export still allowed.
- [ ] Registration: in one DB transaction, create the Business (from `business_name`, `business_type`) and the admin user, with `trial_ends_at = now()->addDays(14)`.
- [ ] Tests: a user never sees another business's data.

**Done when:** two businesses signed up side by side cannot see each other's anything.

---

## Phase 2: Inventory · L

**Tables**
- [ ] `categories`: `name`, `sort`.
- [ ] `items`: `kind` (menu / piece / bulk), `name`, `category_id`, `unit` (e.g. "1L bottle"), `on_hand`, `low_threshold`, `unit_cost`, `archived_at`.
- [ ] `item_variants` (menu only): `label` (16oz, Large), `cost`, `price`, `sort`.
- [ ] `recipe_lines`: `item_id` (menu) → `piece_item_id`, `qty`.

**Screens to wire**
- [ ] `admin/inventory` tabs → real queries (Menu / Pieces / Bulk).
- [ ] Item editor → `StoreItemRequest` / `UpdateItemRequest`, save the variants and recipe lines in a single transaction.
- [ ] Archive (soft delete) instead of hard delete, so past orders stay valid.
- [ ] Low-stock flag = `on_hand <= low_threshold`.

**Defer:** Excel import (ship CSV import later). Owners can type 20–40 items on day one.

**Done when:** an owner can create a Cheeseburger with a recipe (bun, patty, cheese), and add oil as a bulk item.

---

## Phase 3: Register (POS) · L — *the core*

**Tables**
- [ ] `orders`: `number` (per business, sequential), `user_id`, `payment_method` (cash/gcash/maya), `subtotal`, `tendered`, `change`, `status` (paid / void_requested / voided), `paid_at`.
- [ ] `order_lines`: `order_id`, `item_id`, `variant_id`, a copy of `name`, `variant_label`, `price`, `unit_cost`, and `qty`.
- [ ] `stock_movements`: `item_id`, `qty_change`, `reason` (sale / audit / restock / void), `order_id` / `audit_id`. This is the audit trail, and it makes every stock number explainable.

**Checkout endpoint** (`POST /pos/orders`, JSON)
- [ ] Validate the cart server-side, and recompute prices from the DB. Never trust prices sent by the browser.
- [ ] In one transaction: create the order and its lines → for each line, deduct its recipe pieces (or the menu item itself if it has no recipe) with `lockForUpdate` → write `stock_movements`.
- [ ] Return the order number; the `posTerminal` Alpine component posts to it instead of the fake `complete()`.
- [ ] `pos.index` loads the menu from the DB (sellable, non-archived, with variants and "x left" for low items).
- [ ] `staff/orders` → today's orders for that cashier, with totals per payment method.
- [ ] Void request → admin approves → reverses the stock movements.
- [ ] Receipt: a printable page (`window.print()`, 58mm CSS). Skip Bluetooth printers for MVP.

**Done when:** selling 2 Cheeseburgers drops buns, patties and cheese by 2, and the order shows in My orders.

---

## Phase 4: Closing audit · M

- [ ] `audits`: `business_id`, `date` (unique per business per day), `user_id`, `submitted_at`, `duration_seconds`.
- [ ] `audit_lines`: `item_id`, `expected`, `counted`, `unit_cost` (copied at count time), `used = expected − counted`.
- [ ] `/audit` loads bulk items with `expected = on_hand`; submitting sets `on_hand = counted` and writes `stock_movements`.
- [ ] Hide peso values from staff unless the business allows it (the UI already hides them).
- [ ] Counted higher than expected → record it as a restock, not negative usage.

**Done when:** oil goes from 5 to 4.5, the day's bulk cost is ₱72.50, and the Today page stops saying "pending".

---

## Phase 5: Expenses & payables · M

- [ ] `expenses`: `date`, `category` (enum: Utilities, Rent, Wages, Supplies, StockPurchase, Payables, Misc), `description`, `amount`, `kind` (fixed/variable), `user_id`.
- [ ] Quick-add row → `StoreExpenseRequest`; month filter; totals by category.
- [ ] `assets`: `name`, `vendor`, `price`, `installment_amount`, `terms`, `paid_count`, `first_due_on`.
- [ ] "Mark installment paid" → increments `paid_count` and creates an expense in category *Payables*.
- [ ] Optional: a "Stock purchase" expense also adds to `on_hand` (restock). Consider it now; it's cheap to add here and painful later.

---

## Phase 6: Reports, dashboard, export · M

Build **one** query class (e.g. `App\Reports\DailyLedger`) and feed every report from it:

- Sales = sum of `orders.subtotal` (paid only)
- Ingredients (COGS) = sum of `order_lines.qty × unit_cost`
- Bulk used = sum of `audit_lines.used × unit_cost`
- Expenses = sum of `expenses.amount`
- Net = Sales − COGS − Bulk − Expenses

Screens and export:
- [ ] `admin/reports`: daily ledger + P&L waterfall for a date range.
- [ ] `admin/dashboard`: today so far, the last 7 days, best sellers, low stock, the setup checklist driven by real state.
- [ ] CSV export (streamed) for the ledger, expenses and menu pricing. Real `.xlsx` needs a new package (e.g. `maatwebsite/excel`), so decide whether it's worth it. CSV opens in Excel fine.
- [ ] Tests: one fixture day with known numbers, and assert every column.

**Done when:** the numbers on Today, Reports and the CSV all match, down to the centavo.

---

## Phase 7: Team & settings · S–M

- [ ] Invite staff: create a user (`role = staff`, same `business_id`) + password-reset email so they set their own password.
- [ ] Enforce the plan's staff limit (Tindahan = 3).
- [ ] Cashier permissions from `businesses.settings`: see costs, void orders, log expenses, run the audit.
- [ ] Business profile + register settings save.

---

## Phase 8: Super admin & billing · M

- [ ] Businesses list/detail from real data (orders in the last 7 days, last sale, audit count, status).
- [ ] Actions: extend trial, suspend/unsuspend. Skip "view as owner" (impersonation) for MVP.
- [ ] Plans: hardcode 2 plans in config for launch; a DB-managed plans table can come later.
- [ ] **Billing, fastest path:** collect payment manually (GCash/bank) at first, and the super admin marks a business *active* until a date. Add a payment gateway (e.g. PayMongo for GCash/Maya/card) after the first paying customers.
- [ ] Overview metrics: MRR, paying, trials, past due, and the trial → first-audit funnel.

---

## Phase 9: PWA · S (basic) / L (offline orders)

**Basic (do before launch):**
- [ ] `public/manifest.webmanifest` (name, icons 192/512, `display: standalone`, `theme_color #0c0a09`, `start_url /dashboard`).
- [ ] App icons (a placeholder "i." mark until the real logo exists).
- [ ] A service worker that caches the app shell (CSS/JS/fonts) and shows an offline page.

**Offline orders (only if you keep the landing page promise):**
- [ ] Queue orders in IndexedDB while offline, then sync to `/pos/orders` when back online.
- [ ] Make the endpoint idempotent (client-generated UUID per order) so a retry never double-deducts stock.

---

## Phase 10: Launch checklist · S–M

- [ ] Deploy (Laravel Cloud or a VPS), HTTPS on `iposa.me`, queue worker, daily DB backups.
- [ ] Error monitoring + logs.
- [ ] Mail provider for resets and invites.
- [ ] Privacy policy and terms (Philippine Data Privacy Act), a cancellation page.
- [ ] Rate-limit login and registration.
- [ ] Make the landing page copy match reality: offline, Excel export, Viber support, pricing.
- [ ] Replace the super admin demo stat "72% of shops that finish an audit go on to pay" with a real number, or remove it.
- [ ] Onboard 3–5 real shops for free and watch them do a full day, register to closing audit.

---

## Critical path (fastest route to the first paying shop)

```
Phase 0 → 1 → 2 → 3 → 4 → 6 (dashboard + ledger only) → 9 (basic) → 10
```

Phases 5, 7 and 8 can be done in their simplest form (manual billing, owner-only team) and finished after the first shops are live.

## Cut for MVP (add after launch)

Excel import · `.xlsx` export · payment gateway · impersonation · Bluetooth printers · offline order queue (if the landing copy is changed) · multiple branches · discounts & promos · customer loyalty.
