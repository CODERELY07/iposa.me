# Module 06 · Closing audit

At closing, staff look at the bottles and tubs and type what's left. The drop becomes the day's **bulk cost**, which completes the true daily profit. Target: under 60 seconds.

| Feature | Status |
|---|---|
| [Daily count](#feature-daily-count) | 🎨 UI only (client-side complete) |
| [Usage cost](#feature-usage-cost) | 🎨 UI only |
| [Restock detection](#feature-restock-detection) | 🎨 UI only (pill) |
| [Audit history](#feature-audit-history) | ⬜ Not started |

**Who:** `staff` and `admin` (`role:staff|admin`); later also the `audit.run` permission.

### Routes (existing)

| URI | Name | View | Demo variables |
|---|---|---|---|
| `/audit` | `audit` | `audit/index` | `$bulkItems` (`id, name, unit, expected, unitCost`) |

Client state: `Alpine.data('closingAudit')` in `resources/js/app.js`.

### Data model (to build)

| Table | Columns |
|---|---|
| `audits` | `id`, `business_id`, `date` (**unique per business**), `user_id`, `started_at`, `submitted_at`, `duration_seconds` |
| `audit_lines` | `id`, `audit_id`, `item_id`, `expected` decimal(12,3), `counted` decimal(12,3), `unit_cost` decimal(12,2) (copied), `used` decimal(12,3) (= max(0, expected − counted)), `restocked` decimal(12,3) (= max(0, counted − expected)) |

---

## Feature: Daily count

**Status:** 🎨 UI only

One card per bulk item: "system says 5". Large −/+ buttons (step 0.25), a number field (with the decimal keyboard on phones), a "Still 5, nothing used" shortcut, a progress bar, and **Close the day** (enabled once every item is checked; shows "Saving counts…").

### Backend to build

- [ ] A controller for `audit`: pass `$bulkItems` = `kind = Bulk`, not archived, with `expected = on_hand`.
- [ ] If today's audit already exists: show "Already closed by Jessa at 9:51 PM" with an option to **edit** (admin only).
- [ ] `POST /audit` (`audit.store`), `StoreAuditRequest`: one entry per bulk item; `counted` numeric ≥ 0; `item_id` exists in this business.
- [ ] One transaction: create the `audits` + `audit_lines` rows → set `items.on_hand = counted` → write `stock_movements` (`reason = Audit`, qty_change = counted − expected).
- [ ] `duration_seconds` from the page-open timestamp the form sends.
- [ ] Replace the `setTimeout` in `closingAudit.submit()` with the real POST.
- [ ] Scheduled reminder at `settings.audit_reminder_time` (notification to staff/owner if no audit yet).

### Done when

Oil 5 → 4.5 saves, `on_hand` becomes 4.5, and a second audit for the same day is blocked (or becomes an edit).

---

## Feature: Usage cost

**Status:** 🎨 UI only (the sticky footer shows "Bulk used today ₱…" for admins)

`bulk cost of the day = Σ(used × unit_cost)`. For example, 0.5 × ₱145 oil = ₱72.50.

### Backend to build

- [ ] Compute it from `audit_lines` (with the copied `unit_cost`) for [Reports › Daily ledger](08-reports.md#feature-daily-ledger).
- [ ] Only show peso values to users allowed `costs.view` ([RBAC](02-access-control.md#feature-cashier-permissions)). Today the view checks `role === 'admin'`.

---

## Feature: Restock detection

**Status:** 🎨 UI only (a "restocked?" pill when counted > expected)

### Backend to build

- [ ] Store the extra as `audit_lines.restocked`, **not** as negative usage.
- [ ] Suggest logging a stock-purchase expense for it ([Expenses](07-expenses.md#feature-expense-log)).

---

## Feature: Audit history

**Status:** ⬜ Not started

### Backend to build

- [ ] The Bulk & liquids tab header: "Last closing audit: Thu 9:51 PM by Jessa · took 52 seconds".
- [ ] A per-item "Avg use / day" (last 7 audits) for the inventory table.
- [ ] Platform metric: businesses with **no audit in N days** (the activation signal on [Platform › Overview](10-platform.md#feature-overview-metrics)).
