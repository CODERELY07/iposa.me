# Module 07 · Expenses

Excel-style expense tracking: date, category, amount. Plus equipment bought in installments. Everything here goes into the P&L.

| Feature | Status |
|---|---|
| [Expense log](#feature-expense-log) | 🎨 UI only |
| [Categories & fixed/variable](#feature-categories--fixedvariable) | 🎨 UI only |
| [Equipment & payables](#feature-equipment--payables) | 🎨 UI only |
| [Export](#feature-export) | 🎨 UI only (button) |

**Who:** `admin`; later `staff` with the `expenses.create` permission (quick entries like ice or LPG).

### Routes (existing)

| URI | Name | View | Demo variables |
|---|---|---|---|
| `/admin/expenses` | `admin.expenses` | `admin/expenses` | `$expenses`, `$assets` |

### Data model (to build)

| Table | Columns |
|---|---|
| `expenses` | `id`, `business_id`, `date`, `category` (enum: `Utilities`, `Rent`, `Wages`, `Supplies`, `StockPurchase`, `Payables`, `Misc`), `description`, `kind` (enum: `Fixed`, `Variable`), `amount` decimal(12,2), `asset_id` nullable, `user_id`, timestamps |
| `assets` | `id`, `business_id`, `name`, `vendor`, `price` decimal(12,2), `installment_amount` decimal(12,2) nullable, `terms` (months), `paid_count`, `first_due_on`, timestamps |

> Ingredient costs are **not** logged here. They come from sales (recipe costs) and the closing audit. The screen says so, to prevent double counting.

---

## Feature: Expense log

**Status:** 🎨 UI only

A quick-add row at the top (Date · Category · What for · Amount · **Add**), the table below, and a month total in the footer.

### Backend to build

- [ ] `ExpenseController@index` (with the month filter from the select, default this month) and `@store`.
- [ ] `StoreExpenseRequest`: `date` required date ≤ today; `category` in the enum; `description` required, max 255; `amount` required numeric > 0; `kind` in the enum (default: `Fixed` for Rent/Utilities/Wages, otherwise `Variable`).
- [ ] Edit / delete an entry (inline or modal), admin only.
- [ ] `StockPurchase` entries can optionally add stock to an item (`stock_movements.reason = Restock`), so buying 200 buns updates inventory.

### Done when

Adding "Ice, 3 sacks · ₱300" today shows up in the table, the category breakdown and today's P&L.

---

## Feature: Categories & fixed/variable

**Status:** 🎨 UI only (the "Where it went" side panel: stacked bar + list)

### Backend to build

- [ ] A month total per category: `SUM(amount) GROUP BY category`, sorted descending.
- [ ] Category colors stay in the view (presentation only).

---

## Feature: Equipment & payables

**Status:** 🎨 UI only (Equipment & payables tab: price, monthly amount, paid 6/12 bars, next due)

Mirrors the client's asset sheet: espresso machine, freezer, blenders bought in installments.

### Backend to build

- [ ] `AssetController` (index / store / update).
- [ ] Computed: `next_due_on` = `first_due_on + paid_count months` (null when fully paid); "Payables this month"; "Next due".
- [ ] **Mark installment paid** → `paid_count++` **and** create an `expenses` row (`category = Payables`, `asset_id`) so it lands in the P&L exactly once.
- [ ] A cash purchase (`terms = 1`) creates one expense on the purchase date.
- [ ] Due-soon items show on Today → "Needs you tonight".

---

## Feature: Export

**Status:** 🎨 UI only ("Export CSV" busy button)

### Backend to build

- [ ] A streamed CSV for the selected month: date, category, description, type, amount, logged by.
