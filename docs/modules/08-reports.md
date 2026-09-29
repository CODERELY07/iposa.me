# Module 08 · Today & reports

The answer the owner actually wants: **did I make money today, and where did it go?**

Every screen below reads the same class, `App\Reports\DailyLedger`, so Today, the P&L, best sellers and the CSV exports can never disagree.

```
Net = Sales − Restock costs − Operating expenses
```

Cash basis, on purpose: a restock counts against profit the day it's paid for, not
the day it sells. Ingredient cost (COGS) and bulk usage are still computed — they
drive the Ingredients and Bulk drill-down pages and the recipe-surplus math — but
no longer feed Net. A restock only counts if it was logged as an expense (the "log
this as an expense" box on the restock form).

| Feature | Status |
|---|---|
| [Today (dashboard)](#today-dashboard) | ✅ Built |
| [Day details](#day-details) | ✅ Built |
| [Daily ledger](#daily-ledger) | ✅ Built |
| [Profit & ledger page](#profit--ledger-page) | ✅ Built |
| [Best sellers](#best-sellers) | ✅ Built |
| [PDF business report](#pdf-business-report) | ✅ Built |
| [CSV & zip exports](#csv--zip-exports) | ✅ Built |

## Routes

| Method | URI | Name | Middleware |
|---|---|---|---|
| GET | `/admin` | `admin.dashboard` | `role:admin`, `business` |
| GET | `/admin/day/{section}?date=` | `admin.day` | `role:admin`, `business` — `section`: sales, ingredients, bulk, expenses |
| GET | `/admin/reports` | `admin.reports` | + `plan:reports` |
| GET | `/admin/reports/pdf?period=&from=&to=` | `admin.reports.pdf` | + `plan:reports` |
| GET | `/admin/exports/{dataset}` | `admin.exports.download` | `role:admin`, `business` (never plan- or billing-gated) |

---

## Today (dashboard)

- **Profit so far**, with the change against *yesterday at this same hour* — a fair comparison at 10am, not against a whole day.
- The equation spelled out: sales − restock costs − expenses.
- Orders today, the last 7 days as a small bar chart, today's best sellers.
- **Running low**, with "runs out in about N days" from the last 7 days of use.
- **Void requests** waiting for approval ([Register › Voids](05-pos.md#voids)).
- **Waiting for you**: deliveries to check, link change requests and recipes that use too much ([Inventory](04-inventory.md#cashier-products--deliveries), [Closing audit](06-closing-audit.md#recipes-that-use-too-much)).
- **Liquids · recipes vs the count** — for liquids that are in recipes, what the recipes used against what the last closing count found ([06 › Liquids in recipes](06-closing-audit.md#liquids-in-recipes)).
- A **setup checklist** for a new shop: add your menu, add bulk & liquids, link ingredients, invite a cashier, ring up a sale, finish a closing audit. It disappears once everything is done.

## Day details

Four drill-down pages (`Admin\DayController`, `App\Reports\DayBreakdown`), reachable from Today's Sales/Expenses tiles or directly, with tabs for all four, the day's profit, and ← / → / a date picker for any past day (never the future). Only **Sales** and **Expenses** feed Net now; **Ingredients** and **Bulk used** are informational drill-downs for margin analysis, kept for that even though they're no longer part of the P&L:

| Page | Shows | Feeds Net? |
|---|---|---|
| **Sales** | Totals per payment method; every order: number (opens the receipt), time, items, cashier, payment, total, cost, profit after ingredients. Voided orders are struck through and not counted | Yes — Sales |
| **Ingredients** | Each size sold: qty, cost each (the day's average), sales, cost. Then what sales took off the shelf, per piece or liquid, and whether its cost was added by the app (Include in cost), must be in the typed cost, or is the item itself | No — informational only |
| **Bulk used** | The closing count per item: expected, counted, used or missing, recipe over-charge given back, unrecorded restocks, cost. "No closing count yet" with a link otherwise | No — informational only |
| **Expenses** | Every entry with category and who logged it; restock costs shown as their own line | Yes — Restock costs + Expenses |

Each total reads the same rows as the ledger and is rounded once, so it matches Today to the centavo (tested).

## Daily ledger

`DailyLedger::forRange()` returns one row per day — `orders, sales, cogs, bulk, audited, expenses, payables, missing, stock_purchases, net` — by running four grouped queries (sales, COGS, bulk usage, expenses) and merging them over a full list of dates, **so days with no activity appear as zeros** instead of vanishing.

Details that matter:

- Voided orders are excluded everywhere.
- COGS uses `order_lines.unit_cost`, the cost copied at sale time.
- Bulk uses `(audit_lines.used − recipe_surplus_costed) × unit_cost`, the cost copied at count time, minus what recipes over-charged.
- `expenses` leaves out **stock purchases**, which are reported separately as `stock_purchases` and *do* feed Net (cash basis). `missing` (Missing stock) and `payables` (equipment installments) are both slices *within* `expenses`, broken out for their own line on the P&L, not on top of it.
- `net = sales − expenses − stock_purchases`. `cogs` and `bulk` are still computed (they drive the Ingredients/Bulk pages and the recipe-surplus math) but don't feed `net`.
- Grouping is `date(paid_at)`, which behaves the same on SQLite, MySQL and Postgres.

`totals()` sums a set of rows; `salesBetween()` powers the hour-for-hour comparison on Today.

## Profit & ledger page

Week, this month, or a custom range (capped at one year, no future dates — `before_or_equal:today`). Shows the P&L waterfall (Gross revenue, Restock costs, Operating expenses, Net profit), a note when deliveries are still unchecked, the day-by-day table newest first, best sellers for the range, and a **Startup capital** section (`App\Models\CapitalContribution`) — equity the owner put in, shown as an all-time total regardless of the date range, never counted against Net. Negosyo only (`plan:reports`).

## Best sellers

Grouped by the *sold* name and size from `order_lines`, ranked by quantity, with revenue and margin %. Because it reads the snapshot, renaming an item later doesn't rewrite last month's chart.

## PDF business report

**Download PDF report** on the Profit & ledger page makes an A4 PDF of the range on screen (`Admin\ReportPdfController`, `App\Reports\PeriodReport`, view `reports/pdf.blade.php`, rendered by `barryvdh/laravel-dompdf`). The range rules are shared with the page (`ReportRangeRequest`: last 7 days, this month, or any range up to a year, no future dates).

| Section | Charts | Tables |
|---|---|---|
| **Summary** | Profit and loss bars (share of sales); sales vs profit per day (per week past 45 days), losses below zero | Sales, net profit and margin, average order, restock cost %; the P&L with % of sales; a note for unchecked deliveries |
| **Daily ledger** | — | Every day: orders, sales, restock costs, expenses, net, margin; totals |
| **Sales** | Payment methods, average sales per weekday, sales per hour | Same, with order counts and shares |
| **Menu performance** | Profit share per item | Best and lowest margins; every item and size with sold, sales, cost, profit, margin |
| **Costs and stock** | — | Stock taken by sales (and how much of it the item's cost includes), closing counts per item, cashier deliveries, stock on hand with value and low-stock flags |
| **Expenses and team** | Expenses per category | Categories (in profit or not), every entry (first 200; the CSV has all), sales per cashier with average order and voids |

Every total comes from `DailyLedger`, so the PDF matches Today, the P&L page and the CSV to the centavo (`ReportPdfTest`). Charts are bars sized in percent or, for the trend, an SVG drawn by `App\Reports\TrendChart` (Sales `#3f76c4`, Profit `#d97706`, Loss `#b42318`, validated for colour-blind separation), because dompdf renders tables and SVG reliably. The footer carries "Page N of M".

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

`ReportPdfTest`: downloads a real PDF from the page's range · totals equal the ledger and every breakdown adds up · closing counts, deliveries and stock · long ranges by week · a shop with no sales · loss bars below zero · other shops, cashiers and Tindahan blocked · no future ranges.

`DayBreakdownTest`: every order listed, voided ones not counted · ingredients per size and stock taken · the closing count · expenses without stock purchases · each total equals the ledger · past days, no future, other shop, cashier blocked · Today links to all four.

`ReportsTest`: the ledger adds up to the centavo · days without activity are zeros · best sellers with margin · a custom range · a future range is rejected · Today shows the equation · the ledger CSV · the menu CSV matches the importer's columns · the zip of everything · Tindahan can't open the P&L.

## What's left

- Hour-of-day and day-of-week patterns ("Fridays are your best day").
- Comparing this month against last month on one screen.
- Scheduled email of the weekly summary.
