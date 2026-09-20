# Module 08 · Today & reports

The answer the owner actually wants: **did I make money today, and where did it go?**

Every screen below reads the same class, `App\Reports\DailyLedger`, so Today, the P&L, best sellers and the CSV exports can never disagree.

```
Net = Sales − Ingredients (COGS) − Bulk used − Expenses
```

| Feature | Status |
|---|---|
| [Today (dashboard)](#today-dashboard) | ✅ Built |
| [Daily ledger](#daily-ledger) | ✅ Built |
| [Profit & ledger page](#profit--ledger-page) | ✅ Built |
| [Best sellers](#best-sellers) | ✅ Built |
| [CSV & zip exports](#csv--zip-exports) | ✅ Built |

## Routes

| Method | URI | Name | Middleware |
|---|---|---|---|
| GET | `/admin` | `admin.dashboard` | `role:admin`, `business` |
| GET | `/admin/reports` | `admin.reports` | + `plan:reports` |
| GET | `/admin/exports/{dataset}` | `admin.exports.download` | `role:admin`, `business` (never plan- or billing-gated) |

---

## Today (dashboard)

- **Profit so far**, with the change against *yesterday at this same hour* — a fair comparison at 10am, not against a whole day.
- The equation spelled out: sales − ingredients − bulk − expenses. Bulk shows "pending" until the [closing audit](06-closing-audit.md) is done.
- Orders today, the last 7 days as a small bar chart, today's best sellers.
- **Running low**, with "runs out in about N days" from the last 7 days of use.
- **Void requests** waiting for approval ([Register › Voids](05-pos.md#voids)).
- A **setup checklist** for a new shop: add your menu, add bulk & liquids, link ingredients, invite a cashier, ring up a sale, finish a closing audit. It disappears once everything is done.

## Daily ledger

`DailyLedger::forRange()` returns one row per day — `orders, sales, cogs, bulk, audited, expenses, payables, net` — by running four grouped queries (sales, COGS, bulk usage, expenses) and merging them over a full list of dates, **so days with no activity appear as zeros** instead of vanishing.

Details that matter:

- Voided orders are excluded everywhere.
- COGS uses `order_lines.unit_cost`, the cost copied at sale time.
- Bulk uses `audit_lines.used × unit_cost`, the cost copied at count time.
- `payables` is the equipment slice of expenses, shown separately so the owner can see why a profitable day still felt tight.
- Grouping is `date(paid_at)`, which behaves the same on SQLite, MySQL and Postgres.

`totals()` sums a set of rows; `salesAndCogsBetween()` powers the hour-for-hour comparison on Today.

## Profit & ledger page

Week, this month, or a custom range (capped at one year, no future dates — `before_or_equal:today`). Shows the totals band, the day-by-day table newest first, and best sellers for the range. Negosyo only (`plan:reports`).

## Best sellers

Grouped by the *sold* name and size from `order_lines`, ranked by quantity, with revenue and margin %. Because it reads the snapshot, renaming an item later doesn't rewrite last month's chart.

## CSV & zip exports

`GET /admin/exports/{dataset}` with optional `from`/`to` (default: this month, limited to the last 2 years).

| Dataset | Contents |
|---|---|
| `ledger` | The daily table above |
| `expenses` | Every expense with category, kind and who logged it |
| `menu` | **The same columns the importer reads** (`name, category, size, cost, price`) — export, edit in Excel, re-import |
| `stock` | Items, on-hand, alert level, unit cost, stock value |
| `orders` | Order lines with prices and costs |
| `audits` | Audit lines with expected, counted, used and cost |
| `all` | One zip containing all six |

Files are streamed (no memory spike), start with a UTF-8 BOM so Excel shows ₱ correctly, and are named after the shop and range. **Exports stay open when the trial has ended or the bill is unpaid** — "your data is yours" is a promise on the landing page, so it is enforced in the middleware, not just written there.

---

## Tests

`ReportsTest`: the ledger adds up to the centavo · days without activity are zeros · best sellers with margin · a custom range · a future range is rejected · Today shows the equation · the ledger CSV · the menu CSV matches the importer's columns · the zip of everything · Tindahan can't open the P&L.

## What's left

- Hour-of-day and day-of-week patterns ("Fridays are your best day").
- Comparing this month against last month on one screen.
- Scheduled email of the weekly summary.
