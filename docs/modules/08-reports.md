# Module 08 · Reports & analytics

Turns sales, recipe costs, audits and expenses into the one number owners care about: **true profit**.

```
Net profit = Sales − Ingredients (COGS) − Bulk used − Expenses
```

| Feature | Status |
|---|---|
| [Daily ledger (the core query)](#feature-daily-ledger) | 🎨 UI only |
| [Today dashboard](#feature-today-dashboard) | 🎨 UI only |
| [P&L summary](#feature-pl-summary) | 🎨 UI only |
| [Best sellers](#feature-best-sellers) | 🎨 UI only |
| [Period filter](#feature-period-filter) | 🎨 UI only (tabs switch, data doesn't) |
| [Export](#feature-export) | 🎨 UI only (buttons) |

**Who:** `admin` only. Cashiers never see profit.

### Routes (existing)

| URI | Name | View | Demo variables |
|---|---|---|---|
| `/admin` | `admin.dashboard` | `admin/dashboard` | `$today`, `$week`, `$bestSellers`, `$lowStock` |
| `/admin/reports` | `admin.reports` | `admin/reports` | `$ledger` (collection of rows with `date` as Carbon) |

---

## Feature: Daily ledger

**Status:** 🎨 UI only · **Build this first; every other report reads from it.**

One row per day, the same columns as the client's spreadsheet:

| Column | Source |
|---|---|
| Orders | `COUNT(orders)` status `Paid` |
| Sales | `SUM(orders.subtotal)` status `Paid` |
| Ingredients (COGS) | `SUM(order_lines.qty × order_lines.unit_cost)` of paid orders |
| Bulk | `SUM(audit_lines.used × audit_lines.unit_cost)` for that day's audit |
| Expenses | `SUM(expenses.amount)` by `date` |
| Net | Sales − COGS − Bulk − Expenses |
| Margin | Net / Sales |

### Backend to build

- [ ] `App\Reports\DailyLedger` with `forRange(Business, CarbonImmutable $from, CarbonImmutable $to): Collection`. It returns rows keyed by date, **including days with zero sales**.
- [ ] 4 grouped queries (orders, order lines, audit lines, expenses), merged in PHP. No per-day loops of queries.
- [ ] Dates use the business timezone (`Asia/Manila`); set `app.timezone` or convert explicitly.
- [ ] A controller passes `$ledger` in the shape the view uses (`date`, `orders`, `sales`, `cogs`, `bulk`, `expenses`, `net`).

### Tests (must have)

A fixture day with known orders, an audit and expenses → assert every column to the centavo, and that voided orders are excluded.

---

## Feature: Today dashboard

**Status:** 🎨 UI only

The owner's home: **true profit so far** (big number) with the equation strip (Sales / Ingredients / Bulk used / Expenses, each linking to its source), "vs yesterday at this hour", *Needs you tonight* (audit not done, low stock, void requests), the last 7 days' sales vs profit, best sellers, and a setup checklist.

### Backend to build

- [ ] `$today` from `DailyLedger` for today (+ order count, time now).
- [ ] "Bulk used" shows **pending** until today's audit exists.
- [ ] "vs yesterday at this hour" = yesterday's ledger limited to `paid_at <=` the same time.
- [ ] `$week` = the last 7 ledger rows; today marked `isToday`.
- [ ] `$lowStock` from `Item::lowStock()` ([Inventory](04-inventory.md#feature-low-stock-alerts)).
- [ ] Setup checklist from real state: has menu items / has bulk items / has recipe links / has staff / has an audit. Hide it once complete.
- [ ] Cache for ~60 s per business if needed; stale data is fine here.

---

## Feature: P&L summary

**Status:** 🎨 UI only (waterfall bars on Profit & ledger)

Gross revenue → minus COGS → minus bulk → minus operating expenses → minus equipment payables → **net profit**, plus avg per day, best day and food cost %.

### Backend to build

- [ ] Totals from `DailyLedger` for the period.
- [ ] Equipment payables: `Payables` expenses in the period (after [Expenses › Equipment](07-expenses.md#feature-equipment--payables) is built they're already inside Expenses, so **don't subtract them twice**; show them as a separate bar but exclude them from "Operating expenses").
- [ ] Food cost % = (COGS + Bulk) / Sales.

---

## Feature: Best sellers

**Status:** 🎨 UI only

### Backend to build

- [ ] `order_lines` for the period grouped by `name + variant_label`: qty sold, revenue, margin % = (revenue − cost) / revenue. Top 5 by qty.

---

## Feature: Period filter

**Status:** 🎨 UI only (Week / Month / Custom tabs show "Updating numbers…" but the data doesn't change)

### Backend to build

- [ ] Query string `?period=week|month|custom&from=&to=`, validated; default = this month.
- [ ] The tabs become links (or fetch), so the page loader shows while the report reloads.

---

## Feature: Export

**Status:** 🎨 UI only ("Export to Excel", "CSV")

### Backend to build

- [ ] A streamed CSV of the ledger for the selected period (same columns as the table).
- [ ] "Download everything" (Settings) = one CSV per module zipped, or `.xlsx` with one sheet each (needs a package; **ask first**).
- [ ] **Export always works, even for expired trials** ([Business › Trial](03-business-tenancy.md#feature-trial--plan-status)).
