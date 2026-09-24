# Module 08 · Today & reports

The answer the owner actually wants: **did I make money today, and where did it go?**

Every screen below reads the same class, `App\Reports\DailyLedger`, so Today, the P&L, best sellers and the CSV exports can never disagree.

```
Net = Sales − Ingredients (COGS) − Bulk used − Expenses
```

| Feature | Status |
|---|---|
| [Today (dashboard)](#today-dashboard) | ✅ Built |
| [Day details](#day-details) | ✅ Built |
| [Daily ledger](#daily-ledger) | ✅ Built |
| [Profit & ledger page](#profit--ledger-page) | ✅ Built |
| [Best sellers](#best-sellers) | ✅ Built |
| [CSV & zip exports](#csv--zip-exports) | ✅ Built |

## Routes

| Method | URI | Name | Middleware |
|---|---|---|---|
| GET | `/admin` | `admin.dashboard` | `role:admin`, `business` |
| GET | `/admin/day/{section}?date=` | `admin.day` | `role:admin`, `business` — `section`: sales, ingredients, bulk, expenses |
| GET | `/admin/reports` | `admin.reports` | + `plan:reports` |
| GET | `/admin/exports/{dataset}` | `admin.exports.download` | `role:admin`, `business` (never plan- or billing-gated) |

---

## Today (dashboard)

- **Profit so far**, with the change against *yesterday at this same hour* — a fair comparison at 10am, not against a whole day.
- The equation spelled out: sales − ingredients − bulk − expenses. Bulk shows "pending" until the [closing audit](06-closing-audit.md) is done.
- Orders today, the last 7 days as a small bar chart, today's best sellers.
- **Running low**, with "runs out in about N days" from the last 7 days of use.
- **Void requests** waiting for approval ([Register › Voids](05-pos.md#voids)).
- **Waiting for you**: deliveries to check, link change requests and recipes that use too much ([Inventory](04-inventory.md#cashier-products--deliveries), [Closing audit](06-closing-audit.md#recipes-that-use-too-much)).
- **Liquids · recipes vs the count** — for liquids that are in recipes, what the recipes used against what the last closing count found ([06 › Liquids in recipes](06-closing-audit.md#liquids-in-recipes)).
- A **setup checklist** for a new shop: add your menu, add bulk & liquids, link ingredients, invite a cashier, ring up a sale, finish a closing audit. It disappears once everything is done.

## Day details

Each of the four numbers under *True profit so far* opens its own page (`Admin\DayController`, `App\Reports\DayBreakdown`), with the four numbers as tabs, the day's profit, and ← / → / a date picker for any past day (never the future):

| Page | Shows | Adds up to |
|---|---|---|
| **Sales** | Totals per payment method; every order: number (opens the receipt), time, items, cashier, payment, total, cost, profit after ingredients. Voided orders are struck through and not counted | Sales |
| **Ingredients** | Each size sold: qty, cost each (the day's average), sales, cost. Then what sales took off the shelf, per piece or liquid, and whether its cost was added by the app (Include in cost), must be in the typed cost, or is the item itself | Ingredients |
| **Bulk used** | The closing count per item: expected, counted, used or missing, recipe over-charge given back, unrecorded restocks, cost. "No closing count yet" with a link otherwise | Bulk used |
| **Expenses** | Every entry with category and who logged it; stock purchases greyed as *not in profit* | Expenses |

Each total reads the same rows as the ledger and is rounded once, so it matches Today to the centavo (tested).

## Daily ledger

`DailyLedger::forRange()` returns one row per day — `orders, sales, cogs, bulk, audited, expenses, payables, missing, stock_purchases, net` — by running four grouped queries (sales, COGS, bulk usage, expenses) and merging them over a full list of dates, **so days with no activity appear as zeros** instead of vanishing.

Details that matter:

- Voided orders are excluded everywhere.
- COGS uses `order_lines.unit_cost`, the cost copied at sale time.
- Bulk uses `(audit_lines.used − recipe_surplus_costed) × unit_cost`, the cost copied at count time, minus what recipes over-charged.
- `expenses` leaves out **stock purchases**, which are reported separately as `stock_purchases` (stock is costed when used). `missing` is the Missing stock slice, shown as its own line on the P&L.
- `payables` is the equipment slice of expenses, shown separately so the owner can see why a profitable day still felt tight.
- Grouping is `date(paid_at)`, which behaves the same on SQLite, MySQL and Postgres.

`totals()` sums a set of rows; `salesAndCogsBetween()` powers the hour-for-hour comparison on Today.

## Profit & ledger page

Week, this month, or a custom range (capped at one year, no future dates — `before_or_equal:today`). Shows the totals band (with Missing stock as its own line and Stock bought listed but not subtracted), a note when deliveries are still unchecked, the day-by-day table newest first, and best sellers for the range. Negosyo only (`plan:reports`). The ledger CSV has an extra *Stock bought (not in net)* column.

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

`DayBreakdownTest`: every order listed, voided ones not counted · ingredients per size and stock taken · the closing count · expenses without stock purchases · each total equals the ledger · past days, no future, other shop, cashier blocked · Today links to all four.

`ReportsTest`: the ledger adds up to the centavo · days without activity are zeros · best sellers with margin · a custom range · a future range is rejected · Today shows the equation · the ledger CSV · the menu CSV matches the importer's columns · the zip of everything · Tindahan can't open the P&L.

## What's left

- Hour-of-day and day-of-week patterns ("Fridays are your best day").
- Comparing this month against last month on one screen.
- Scheduled email of the weekly summary.
