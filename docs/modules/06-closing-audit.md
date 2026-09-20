# Module 06 · Closing audit

At closing, staff look at the bottles and tubs and type what's left. The drop becomes the day's **bulk cost**, which completes true daily profit. Target: under 60 seconds.

**Who:** owners, and cashiers with the `run_audit` permission (on by default).

| Feature | Status |
|---|---|
| [Daily count](#daily-count) | ✅ Built |
| [Usage cost](#usage-cost) | ✅ Built |
| [Restock detection](#restock-detection) | ✅ Built |
| [Owner corrections](#owner-corrections) | ✅ Built |
| [Audit history](#audit-history) | ✅ Built |
| [Reminder notification](#whats-left) | ⬜ Not built |

## Routes

| Method | URI | Name | Middleware |
|---|---|---|---|
| GET | `/audit` | `audit` | `role:staff\|admin`, `business`, `can:run-audit` |
| POST | `/audit` | `audit.store` | same (JSON) |

## Data model

| Table | Columns |
|---|---|
| `audits` | `business_id`, `date` (unique per shop), `user_id`, `counted_by`, `started_at`, `submitted_at`, `duration_seconds` |
| `audit_lines` | `audit_id`, `item_id`, `expected`, `counted`, `used`, `restocked`, `unit_cost` (copied at count time) |

---

## Daily count

One card per bulk item: "system says 5". Large −/+ buttons (quarter steps), a number field with the decimal keyboard on phones, and a "Still 5, nothing used" shortcut. A progress bar tracks how many are done, and **Close the day** only enables once every item is counted.

Submitting posts the counts as JSON with `started_at`, so the saved `duration_seconds` shows how long the audit really took.

`ClosingAuditService::submit()` runs one transaction: lock the bulk items, create the audit and its lines, set `on_hand` to what was counted, and write `Audit` stock movements. Every bulk item must be included, or it's rejected.

## Usage cost

`used = max(0, expected − counted)`, priced with the unit cost copied into the line, so later price changes never rewrite history. Example: oil 5 → 4.5 at ₱145 = **₱72.50** for the day.

Peso totals are hidden from cashiers unless the owner allows `view_costs`; they see "N items left to check" instead.

## Restock detection

Counting more than expected is recorded as `restocked`, never as negative usage, and the card shows a "restocked?" pill.

## Owner corrections

A second submit on the same day is a **correction**, owners only (`can:correct-audit`). The original `expected` is kept, and stock moves only by the difference between the old and new count. Cashiers get 403, and the page tells them the day is already closed, with who counted and when.

## Audit history

- Inventory → Bulk tab: last audit (date, time, who, seconds) and average use per day from the last 7 audits.
- Today: "Closing audit not done" until it's finished; bulk shows as "pending" in the profit equation.
- Platform console: audits are the activation signal for a new shop.

---

## Tests

`ClosingAuditTest`: saves counts and prices usage (₱72.50) · owner sees the usage cost · counting above expected is a restock · every bulk item required · cashiers can't redo today · owner corrections move stock by the difference only · the audit permission can be switched off.

## What's left

- The reminder at `settings.audit_reminder_time` (saved, but nothing sends it yet). Needs a scheduled command plus a notification channel (email or push).
- Offline audits: the register works offline, the audit still needs internet.
