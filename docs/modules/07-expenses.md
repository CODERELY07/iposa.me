# Module 07 · Expenses & equipment

Sales minus ingredients is not profit. Rent, wages, electricity and the freezer being paid off in six months all have to land in the same place, or the P&L lies.

**Who:** owners (Negosyo plan). Cashiers can log a small expense from My orders when the owner allows `log_expenses`.

| Feature | Status |
|---|---|
| [Month view](#month-view) | ✅ Built |
| [Quick add](#quick-add) | ✅ Built |
| [Categories & kind](#categories--kind) | ✅ Built |
| [Cashier expenses](#cashier-expenses) | ✅ Built |
| [Equipment & installments](#equipment--installments) | ✅ Built |
| [Plan gate](#plan-gate) | ✅ Built |

## Routes

| Method | URI | Name | Middleware |
|---|---|---|---|
| GET | `/admin/expenses` | `admin.expenses` | `role:admin`, `business`, `plan:expenses` |
| POST | `/expenses` | `expenses.store` | `role:staff\|admin`, `business` |
| DELETE | `/admin/expenses/{expense}` | `admin.expenses.destroy` | `role:admin`, `business` |
| POST | `/admin/assets` | `admin.assets.store` | owner |
| POST | `/admin/assets/{asset}/pay` | `admin.assets.pay` | owner |
| DELETE | `/admin/assets/{asset}` | `admin.assets.destroy` | owner |

## Data model

| Table | Columns |
|---|---|
| `expenses` | `business_id`, `date`, `category`, `description`, `kind`, `amount` (12,2), `asset_id`, `user_id`, `logged_by` |
| `assets` | `business_id`, `name`, `vendor`, `price`, `installment_amount`, `terms`, `paid_count`, `first_due_on` |

`logged_by` copies the name, so removing a cashier never blanks the history.

---

## Month view

A month picker (from the first expense up to now), the running total, entries newest first, and a breakdown by category sorted by size. Equipment and payables sit on their own tab, with what's due this month and the next due payment.

## Quick add

One spreadsheet-like row: date, category, description, amount. `StoreExpenseRequest` refuses future dates and refuses `payables` — those rows may only be created by paying an installment, so the P&L can't be inflated by hand.

## Categories & kind

| Category | Default kind | Lowers profit? |
|---|---|---|
| Utilities, Rent, Wages | Fixed | Yes |
| Supplies, Misc | Variable | Yes |
| **Stock purchase** | Variable | **No**: listed, but the stock is costed when used (sale cost, closing count) |
| Payables (equipment) | Fixed — system only | Yes |
| **Missing stock** | Variable — system only | Yes: stock bought that never reached the shelf ([checked deliveries](04-inventory.md#cashier-products--deliveries)) |

`ExpenseCategory::lowersProfit()` is the one place this is decided. Things you buy but never track in inventory (ice, napkins) belong in **Supplies**.

`ExpenseKind` (fixed/variable) is what later makes a break-even chart possible; it's recorded from day one even though nothing reads it yet.

## Cashier expenses

From My orders, a cashier with `log_expenses` can record ice or LPG bought out of the drawer. It posts to the same `POST /expenses`, dated today, and shows up in the owner's month with the cashier's name on it.

## Equipment & installments

Add a freezer at ₱24,000 over 12 terms; the card shows paid/remaining and the next due date. **Mark as paid** writes one expense of `installment_amount` in the `payables` category, tied to the asset, and bumps `paid_count` — so an installment lands in the P&L exactly once, on the day it was paid.

A cash purchase (`terms = 1`) is logged as paid immediately. Deleting that expense decrements `paid_count` again, so the two never drift. Deleting the asset keeps its past payments in the expense history.

## Plan gate

`plan:expenses` — Negosyo only. Tindahan owners see an upgrade note on Settings and the sidebar link is hidden.

---

## Tests

`ExpenseTest`: spreadsheet-row logging · future dates and hand-written payables refused · cashier logging when allowed · the month view with category totals · an installment recorded exactly once · a cash purchase logged right away · deleting an installment expense rolls the count back · Tindahan is blocked.

## What's left

- Recurring expenses (rent on the 1st, entered for you).
- Attaching a photo of the receipt.
- A break-even view using `kind` (fixed vs variable).
