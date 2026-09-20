# Module 04 · Inventory

Everything a shop buys, stores and sells. Three kinds of stock, each counted the way a real kitchen counts it:

| Kind (`items.kind`) | Examples | On the register | How the count goes down |
|---|---|---|---|
| **Menu** | Cheeseburger, Iced Tea 16oz/22oz | Yes | Linked pieces are deducted per sale; with no links, the item itself is |
| **Piece** | Buns, patties, cheese, cups | No | Automatically, through recipe links |
| **Bulk** | Oil, mayo, ketchup, LPG | No | By eye at the [closing audit](06-closing-audit.md), in decimals (4.5) |

**Who:** owners only. Cashiers only ever see menu items, on the register.

| Feature | Status |
|---|---|
| [Menu items & sizes](#menu-items--sizes) | ✅ Built |
| [Pieces](#pieces) | ✅ Built |
| [Bulk & liquids](#bulk--liquids) | ✅ Built |
| [Recipe links](#recipe-links) | ✅ Built (Negosyo plan) |
| [Categories](#categories) | ✅ Built |
| [Low-stock alerts](#low-stock-alerts) | ✅ Built |
| [Stock movements](#stock-movements) | ✅ Built |
| [CSV import](#csv-import) | ✅ Built |
| [Archive & delete](#archive--delete) | ✅ Built |

## Routes

| Method | URI | Name | Handler |
|---|---|---|---|
| GET | `/admin/inventory` | `admin.inventory` | `Admin\ItemController@index` |
| GET | `/admin/inventory/items/new` | `admin.inventory.create` | `@create` |
| POST | `/admin/inventory/items` | `admin.inventory.store` | `@store` |
| GET | `/admin/inventory/items/{item}/edit` | `admin.inventory.edit` | `@edit` |
| PUT | `/admin/inventory/items/{item}` | `admin.inventory.update` | `@update` |
| PATCH | `/admin/inventory/items/{item}/archive` · `/restore` | `admin.inventory.archive` · `.restore` | `@archive` · `@restore` |
| DELETE | `/admin/inventory/items/{item}` | `admin.inventory.destroy` | `@destroy` |
| POST | `/admin/inventory/import` | `admin.inventory.import` | `Admin\ItemImportController` |
| POST | `/admin/categories` | `admin.categories.store` | `Admin\CategoryController@store` (JSON) |

## Data model

| Table | Columns |
|---|---|
| `categories` | `business_id`, `name` (unique per shop), `color`, `sort` |
| `items` | `business_id`, `category_id`, `kind`, `name`, `unit`, `on_hand` (12,3), `low_threshold`, `unit_cost` (12,2), `archived_at` |
| `item_variants` | `item_id`, `label`, `cost`, `price`, `sort` |
| `recipe_lines` | `item_id`, `item_variant_id` (null = all sizes), `piece_item_id`, `qty` |
| `stock_movements` | `business_id`, `item_id`, `qty_change`, `reason`, `order_id`, `audit_id`, `user_id`, `created_at` |

Quantities are `decimal(12,3)` so half a bottle is exact; money is `decimal(12,2)`.

---

## Menu items & sizes

The Excel pricing matrix: one row per size, each with its own **cost** and **selling price**. Margin is computed, never stored (`ItemVariant::profit()`, `marginPercent()`).

The editor shows the margin live, colored green ≥50%, amber ≥25%, red below. `SaveItemRequest` requires at least one size with a price for menu items, and rejects duplicate size names.

## Pieces

Countable ingredients and packaging. `unit_cost` is required, because it prices what a sale consumed. The Pieces tab shows on-hand with a bar against its alert level, used today, unit cost and stock value.

## Bulk & liquids

Containers counted by eye. The tab shows on-hand, average use per day (mean of the last 7 audits), days left (red under 4), unit cost and daily cost, plus when the last audit ran and who did it.

## Recipe links

"1 Cheeseburger uses 1 bun + 1 patty + 1 cheese." Optional per item, and a line can apply to **one size only** (16oz cup vs 22oz cup) or to every size.

The editor totals the piece cost per size and offers **Use as cost** to copy it into each size's cost. A piece must belong to the same shop and be a piece (`Rule::exists` scoped by `business_id` and `kind`).

On the Tindahan plan the section is hidden and any submitted recipe lines are dropped.

## Categories

Each category has a color that becomes the register tile's color. New ones can be added inline from the item editor (JSON, no page reload); names are unique per shop.

## Low-stock alerts

An item is low when `on_hand <= low_threshold`, falling back to `businesses.settings.default_low_threshold`. Low items show a red dot in Inventory, a "N left" pill on register tiles, and a "runs out in about N days" line on Today, based on the last 7 days of use.

## Stock movements

Every change writes a row with its reason: `Sale`, `Void`, `Audit`, `Restock`, `Adjustment`. Nothing changes `on_hand` outside `App\Services\Inventory\StockService`, so any number can be explained. Editing on-hand in the item editor logs an `Adjustment` with the difference.

## CSV import

`MenuImportService` reads a CSV saved from Excel: header row `name, category, size, cost, price`. Rows with the same name become one item with several sizes, existing items are updated, categories are created as needed, and `₱1,234.50` parses as `1234.50`. Bad rows are reported per line instead of failing the import.

## Archive & delete

**Archive** is the normal way to remove an item: it leaves the register and the tabs, past orders keep their reference, and "Show archived" lists it for restoring.

**Delete for good** is offered only when the item carries no history at all — never sold, no stock movements, never counted in an audit, and not linked into any recipe. Then it's a typo being cleaned up, and removing it changes no report. Otherwise the editor says so and only archive is available:

| Blocker | Message |
|---|---|
| Sold at least once | "it has been sold (N so far)." |
| Has stock movements | "it has stock movements on record." |
| Counted in an audit | "it has been counted in a closing audit." |
| Linked as a recipe piece | "it is linked to N recipes." |

Deleting removes the item with its sizes and its own recipe lines, in one transaction. Another shop's item 404s before any of this.

---

## Tests

`InventoryTest`: delete an item with no history · refuse to delete one that was sold, counted or linked · no cross-shop delete · the editor offers delete only when it's allowed · create with sizes and size-specific recipe links · update syncs sizes · menu items need a priced size · pieces and bulk need a unit cost · cross-shop piece refused · on-hand edit logs an adjustment · archive/restore · CSV import (including a broken row) · inline category · recipes hidden on Tindahan.

## What's left

- Stock take for pieces (count many at once) — today it's per item.
- Supplier records and purchase orders.
- `.xlsx` import; CSV only.
