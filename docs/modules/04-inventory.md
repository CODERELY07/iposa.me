# Module 04 · Inventory

Everything a shop buys, stores and sells. There are three kinds of stock, each counted differently:

| Kind | Examples | Shown on the register | How the count goes down |
|---|---|---|---|
| **Menu item** | Cheeseburger, Iced Tea 16oz/22oz | Yes | Its linked pieces are deducted when it sells; with no links, the item itself is deducted |
| **Piece** | Burger bun, patty, cheese slice, cups | No | Automatically, through recipe links |
| **Bulk & liquid** | Cooking oil, mayo tub, ketchup, LPG | No | By hand, in the [closing audit](06-closing-audit.md) (decimals such as 4.5) |

| Feature | Status |
|---|---|
| [Menu items & sizes (pricing matrix)](#feature-menu-items--sizes) | 🎨 UI only |
| [Pieces](#feature-pieces) | 🎨 UI only |
| [Bulk & liquids](#feature-bulk--liquids) | 🎨 UI only |
| [Recipe links](#feature-recipe-links) | 🎨 UI only |
| [Low-stock alerts](#feature-low-stock-alerts) | 🎨 UI only |
| [Import & export](#feature-import--export) | 🎨 UI only |
| [Archive](#feature-archive) | 🎨 UI only |

**Who:** `admin` (all features). Staff never open inventory; they only see menu items on the register.

### Routes (existing, `role:admin`)

| URI | Name | View | Demo variables |
|---|---|---|---|
| `/admin/inventory` | `admin.inventory` | `admin/inventory/index` | `$menuItems`, `$pieceItems`, `$bulkItems` |
| `/admin/inventory/items/new` | `admin.inventory.create` | `admin/inventory/item` | `$product` |
| `/admin/inventory/items/{item}/edit` | `admin.inventory.edit` | `admin/inventory/item` | `$product` |

### Shared data model (to build)

| Table | Columns |
|---|---|
| `categories` | `id`, `business_id`, `name`, `sort` |
| `items` | `id`, `business_id`, `kind` (enum: `Menu`, `Piece`, `Bulk`), `name`, `category_id` (nullable), `unit` (e.g. "1L bottle"), `on_hand` decimal(12,3), `low_threshold` decimal(12,3) nullable, `unit_cost` decimal(12,2) nullable, `archived_at` nullable, timestamps |
| `item_variants` | `id`, `item_id`, `label` (16oz, Large), `cost` decimal(12,2), `price` decimal(12,2), `sort` |
| `recipe_lines` | `id`, `item_id` (menu), `piece_item_id` (piece), `qty` decimal(12,3) |
| `stock_movements` | `id`, `business_id`, `item_id`, `qty_change` decimal(12,3), `reason` (enum: `Sale`, `Audit`, `Restock`, `Void`, `Adjustment`), `order_id` / `audit_id` nullable, `user_id`, `created_at` |

Models: `Category`, `Item`, `ItemVariant`, `RecipeLine`, `StockMovement`, each using `BelongsToBusiness` ([tenancy](03-business-tenancy.md#feature-data-scoping)).

---

## Feature: Menu items & sizes

**Status:** 🎨 UI only · **Screens:** the Menu items tab, and the item editor with "Sell this on the register? Yes"

The pricing matrix from the client's Excel file: one row per size, each with its own **cost (COGS)** and **selling price**. Profit and margin are computed.

### How it works (UI)

- The item editor adds or removes size rows; margin updates live, colored ≥50% green, ≥25% amber, below that rose.
- The register shows single-size items as one tile, and multi-size items as one button per size.

### Backend to build

- [ ] `ItemController` (`index`, `create`, `store`, `edit`, `update`) replacing the `Route::view` lines; keep the route names.
- [ ] `StoreItemRequest` / `UpdateItemRequest`:
  - `kind` required, in the enum
  - `variants` required when `kind = Menu`, `min:1`; `variants.*.label` required, distinct; `variants.*.price` required, numeric, ≥ 0; `variants.*.cost` numeric, ≥ 0
- [ ] Save the item, variants and recipe lines in one `DB::transaction`; sync the variants (update existing ones, delete removed ones).
- [ ] Computed attributes on `ItemVariant`: `profit` (`price - cost`) and `margin_percent`. **Don't store them.**
- [ ] Category management (inline "add category" in the select is enough for MVP).

### Done when

The owner creates "Iced Tea" with 16oz ₱45 / cost ₱9.50 and 22oz ₱60 / cost ₱13; the Menu tab shows margins of 78.9% and 78.3%, and the register shows two taps.

---

## Feature: Pieces

**Status:** 🎨 UI only · **Screen:** Pieces tab

Countable ingredients and packaging that only go down through recipe links.

### Backend to build

- [ ] The same `items` table with `kind = Piece`; `unit_cost` and `on_hand` are required.
- [ ] The "Used today" column = `-SUM(stock_movements.qty_change)` where `reason = Sale` and the date is today.
- [ ] Stock value = `on_hand × unit_cost`.
- [ ] Restocking: an "Add stock" action that writes a `Restock` movement (or comes from a stock-purchase expense, see [Expenses](07-expenses.md#feature-expense-log)).

---

## Feature: Bulk & liquids

**Status:** 🎨 UI only · **Screen:** Bulk & liquids tab

Containers counted by eye once a day. No weighing, no grams.

### Backend to build

- [ ] `items.kind = Bulk`; `on_hand` accepts decimals (step 0.25 in the UI).
- [ ] "Avg use / day" = average of the last 7 `audit_lines.used`.
- [ ] "Days left" = `on_hand / avg use`; red when under 4.
- [ ] "Last closing audit" header = the latest `audits` row (who, when, duration).

---

## Feature: Recipe links

**Status:** 🎨 UI only · **Screen:** item editor → "What one sale uses"

"1 Cheeseburger uses 1 bun + 1 patty + 1 cheese slice." Optional per menu item.

### Backend to build

- [ ] `recipe_lines` saved together with the item; `piece_item_id` must be a `Piece` of the **same business** (use a `Rule::exists` scoped to `business_id` and `kind`).
- [ ] "Pieces cost ₱X per sale" = `Σ(qty × piece.unit_cost)`; the **Use as cost** button copies it into the first size's cost (already works in the UI).
- [ ] Decide: are recipes per **item** (all sizes) or per **variant**? Drinks (16oz vs 22oz cup) need per-variant. Recommended: add a nullable `item_variant_id` to `recipe_lines`.
- [ ] Deduction happens at checkout ([POS › Stock deduction](05-pos.md#feature-stock-deduction)).

---

## Feature: Low-stock alerts

**Status:** 🎨 UI only · **Shown on:** the Pieces tab (red dot), Today → "Needs you tonight", and the "N left" pill on register tiles

### Backend to build

- [ ] `Item::scopeLowStock()`: `on_hand <= low_threshold`.
- [ ] Default threshold from `businesses.settings.default_low_threshold`.
- [ ] "Runs out tomorrow lunch" = `on_hand / average daily use` (pieces: last 7 days of sales).
- [ ] Optional later: a daily summary email to the owner.

---

## Feature: Import & export

**Status:** 🎨 UI only · **Buttons:** "Import Excel" (file picker) and "Export CSV"

### Backend to build

- [ ] **Export CSV** (MVP): a streamed download (`response()->streamDownload`) of the menu pricing matrix: item, category, size, cost, price, profit, margin.
- [ ] **Import** (after MVP): upload CSV → a preview table with errors per row → confirm → create items and variants in one transaction. Expected columns: `name, category, size, cost, price`.
- [ ] Real `.xlsx` needs a new package (e.g. `maatwebsite/excel` or `openspout`). **Ask before adding dependencies.**

---

## Feature: Archive

**Status:** 🎨 UI only · **Button:** item editor → "Archive"

### Backend to build

- [ ] Set `archived_at` instead of deleting: past orders still reference the item.
- [ ] Archived items are hidden from the register, the tabs and recipe pickers; a "Show archived" filter restores them.
