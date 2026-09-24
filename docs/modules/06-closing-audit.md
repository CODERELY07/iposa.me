# Module 06 · Closing audit

At closing, staff look at the bottles and tubs (and, when the shop asks, the buns and cups) and type what's left. The drop becomes the day's **bulk cost**, which completes true daily profit. Target: under 60 seconds.

**Who:** owners, and cashiers with the `run_audit` permission (on by default).

| Feature | Status |
|---|---|
| [Daily count](#daily-count) | ✅ Built |
| [Counting containers](#counting-containers) | ✅ Built |
| [Liquids in recipes](#liquids-in-recipes) | ✅ Built |
| [Recipes that use too much](#recipes-that-use-too-much) | ✅ Built |
| [Counting pieces](#counting-pieces) | ✅ Built (optional) |
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
| `audits` | `business_id`, `date` (unique per shop), `user_id`, `counted_by`, `started_at`, `submitted_at`, `duration_seconds`, `last_movement_id` (where the count sits in the stock history) |
| `audit_lines` | `audit_id`, `item_id`, `expected`, `counted`, `used`, `restocked`, `recipe_surplus`, `recipe_deducted`, `recipe_deducted_costed`, `recipe_surplus_costed`, `recipe_fix`, `recipe_fix_at`, `unit_cost` (copied at count time, six decimals) |

---

## Daily count

One card per bulk item: "system says 5". For items counted in their own unit: large −/+ buttons, a number field with the decimal keyboard on phones, and a "Still 5, nothing used" shortcut. **The step follows the unit**: ±50 for ml and grams, ±0.25 for bottles, kg or tanks. A progress bar tracks how many are done, and **Close the day** only enables once every item is counted.

Submitting posts the counts as JSON with `started_at`, so the saved `duration_seconds` shows how long the audit really took.

`ClosingAuditService::submit()` runs one transaction: lock the counted items, create the audit and its lines, set `on_hand` to what was counted, and write `Audit` stock movements. Every item on the list must be included, or it's rejected.

## Counting containers

Items bought in containers ([Inventory › Containers](04-inventory.md#containers--exact-costs)) get a card nobody has to do maths on:

```
Cooking oil                          system says 3,000 ml
  Full bottles (1,000 ml each)   [ − ]  2  [ + ]
  Open bottle     Empty  ¼  [½]  ¾  Full
  Counted 2,500 ml                 or exactly [      ml]
```

`counted = Σ over sizes (full + open fraction) × size`, so a shop with bottles *and* a tin sees one row per size and the rows add up. The optional exact field is for anyone who measures. Only the total in ml is stored; the next audit starts again from 0 full / Empty, which is how a physical count works anyway.

Scenario, checked on a phone: oil 2 full + ½ → 2,500 ml, **used 500 ml = ₱72.50**; ketchup ¾ jug → 2,838.75 ml, **used 946.25 ml = ₱11.25**; the footer reads **Bulk used today ₱83.75**.

## Liquids in recipes

When a liquid is also in recipes, sales already took their share during the day. The audit compares against the stock **after** those sales (`expected` is `on_hand` at count time), so it only charges what the recipes don't explain — nothing is counted twice:

| Ketchup, Monday | ml | Where it's charged |
|---|---|---|
| 60 burgers × 15 ml (recipe) | 900 | Ingredients, through the burger's cost |
| Extra found by the count | 992.5 | Bulk & liquids (audit) |
| **Total really used** | **1,892.5** | |

**Counted more than expected?** For a liquid in no recipe it is a restock, as before. For one in recipes the card asks:

- ( ) We restocked today → `restocked`
- ( ) Recipes use less than set → `recipe_surplus`, and Today suggests lowering the recipe amount ([below](#recipes-that-use-too-much)) Today's **Liquids · recipes vs the count** card (`App\Reports\RecipeVariance`) shows, for the latest closing: recipe use, the extra the count found (with its pesos), the total, and a plain verdict — "close to what the recipes say", "more than the recipes explain: waste, bigger portions or free extras?", or "recipes deduct more than the kitchen uses".

> Ingredients (COGS) comes from each menu size's cost. With **Include in cost** on, each sale adds what its links cost ([Inventory › Menu items](04-inventory.md#menu-items--sizes)). With it off, the typed cost must include the ketchup, or the recipe share isn't charged anywhere; the editor warns about exactly this.

## Recipes that use too much

"Recipes use less than set" means sales took and costed more than the kitchen used. Three rules keep that exact:

1. **Capped at what recipes took since the previous count.** For each item the audit stores `recipe_deducted`: `Sale` minus `Void` movements after the previous count's `last_movement_id`. A surplus above that can't be the recipes, so the rest is still a `restocked`. An owner correction keeps the first count's window.
2. **Only what was charged is given back.** `recipe_surplus_costed = recipe_surplus × recipe_deducted_costed ÷ recipe_deducted`. Sales from items with Include in cost off never charged the liquid, so nothing comes back for them. The line's cost is `(used − recipe_surplus_costed) × unit_cost`, which can be below zero: it adds back to profit.
3. **Fix the cause.** Today lists **Recipes that use too much**: "Recipes took 150 ml since the count before, but only 120 ml was used (80%)". **Lower the recipes** sets every recipe that uses it to that share (15 ml → 12 ml) and records each change in the item's link history; **Keep as is** dismisses it. Only the newest count per item can be applied (`App\Services\Audit\RecipeFixService`).

Example: 10 burgers at 15 ml ketchup (₱0.05/ml, Include in cost on) → Ingredients ₱7.50 of ketchup. The count finds 30 ml more than expected → **+₱1.50** on the closing count.

## Counting pieces

Settings → **Count pieces at closing** (`settings.audit_pieces`, off by default). When on, pieces join the list after the liquids, stepping by 1. Missing pieces (`used`) are costed in the same line of the profit, so a stolen bun shows up the night it goes missing. The labels change to "Used or missing today" / "Used at closing".

## Usage cost

`used = max(0, expected − counted)`, priced with the unit cost copied into the line, so later price changes never rewrite history. Example: oil 5 → 4.5 at ₱145 = **₱72.50** for the day. The line's cost is `(used − recipe_surplus_costed) × unit_cost` ([above](#recipes-that-use-too-much)).

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

`ContainerStockTest` (audit parts): the scenario to the centavo · containers and step on the screen · "recipes use less than set" vs restock.

`ProfitAccuracyTest`: the over-charge given back exactly · nothing given back for uncosted recipes · surplus above the recipes is a restock · voids left out · the window starts at the previous count · corrections keep it · lower the recipes / keep as is / out-of-date / other shop.

`PiecesAuditTest`: bulk only by default · the setting saves · pieces required and costed when on.

`ClosingAuditTest`: saves counts and prices usage (₱72.50) · owner sees the usage cost · counting above expected is a restock · every bulk item required · cashiers can't redo today · owner corrections move stock by the difference only · the audit permission can be switched off.

## What's left

- The reminder at `settings.audit_reminder_time` (saved, but nothing sends it yet). Needs a scheduled command plus a notification channel (email or push).
- Offline audits: the register works offline, the audit still needs internet.
