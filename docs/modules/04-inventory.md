# Module 04 · Inventory

Everything a shop buys, stores and sells. Three kinds of stock, each counted the way a real kitchen counts it:

| Kind (`items.kind`) | Examples | On the register | How the count goes down |
|---|---|---|---|
| **Menu** | Cheeseburger, Iced Tea 16oz/22oz | Yes | Linked pieces are deducted per sale; with no links, the item itself is |
| **Piece** | Buns, patties, cheese, cups | No | Automatically, through recipe links |
| **Bulk** | Oil, mayo, ketchup, LPG | No | By eye at the [closing audit](06-closing-audit.md), in decimals (4.5) |

**Who:** owners. Cashiers see menu items on the register and, when the owner allows it, a [Products page](#cashier-products--deliveries) to restock and ask for link changes.

| Feature | Status |
|---|---|
| [Menu items & sizes](#menu-items--sizes) | ✅ Built |
| [Pieces](#pieces) | ✅ Built |
| [Bulk & liquids](#bulk--liquids) | ✅ Built |
| [Containers & exact costs](#containers--exact-costs) | ✅ Built |
| [Restock](#restock) | ✅ Built |
| [Recipe links](#recipe-links) | ✅ Built (Negosyo plan) — pieces and liquids |
| [Link history & approvals](#link-history--approvals) | ✅ Built |
| [Cashier products & deliveries](#cashier-products--deliveries) | ✅ Built |
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
| POST | `/admin/inventory/items/{item}/restock` | `admin.inventory.restock` | `Admin\ItemRestockController` |
| POST | `/admin/inventory/import` | `admin.inventory.import` | `Admin\ItemImportController` |
| POST | `/admin/categories` | `admin.categories.store` | `Admin\CategoryController@store` (JSON) |
| POST | `/admin/recipe-changes/{recipeChange}/approve` · `/reject` | `admin.recipe-changes.approve` · `.reject` | `Admin\RecipeChangeController` |
| POST | `/admin/deliveries/{delivery}/check` | `admin.deliveries.check` | `Admin\DeliveryCheckController` |
| GET | `/staff/products` | `staff.products` | `Staff\ProductController@index` (needs `restock-stock` or `link-pieces`) |
| POST | `/staff/products/{item}/restock` | `staff.products.restock` | `Staff\ProductRestockController` (`can:restock-stock`) |
| GET · PUT | `/staff/products/{item}/links` | `staff.products.links` · `.links.update` | `Staff\ProductController` (`can:link-pieces`, `plan:recipes`) |

## Data model

| Table | Columns |
|---|---|
| `categories` | `business_id`, `name` (unique per shop), `color`, `sort` |
| `items` | `business_id`, `category_id`, `kind`, `name`, `unit`, `on_hand` (12,3), `low_threshold`, `unit_cost` (**14,6**), `include_recipe_cost`, `archived_at` |
| `item_containers` | `item_id`, `label` (bottle, jug, tin), `size` (in the item's unit), `price` (per container), `sort` |
| `item_variants` | `item_id`, `label`, `cost`, `price`, `sort` |
| `recipe_lines` | `item_id`, `item_variant_id` (null = all sizes), `piece_item_id`, `qty` |
| `stock_movements` | `business_id`, `item_id`, `qty_change`, `costed_qty` (the part a sale charged in its cost), `reason`, `order_id`, `audit_id`, `user_id`, `created_at` |
| `recipe_changes` | `business_id`, `item_id`, `user_id`, `requested_by`, `status` (saved, pending, approved, rejected, replaced), `before`/`after` (JSON snapshots with names and units), `decided_by`, `decided_by_name`, `decided_at` |
| `deliveries` | `business_id`, `item_id`, `user_id`, `received_by`, `quantity`, `item_container_id`, `container_label`, `container_size`, `added`, `status` (pending, checked), `receipt_added`, `paid`, `missing_cost`, `checked_by`, `checked_by_name`, `checked_at` |

Quantities are `decimal(12,3)` so half a bottle is exact. **Cost per unit keeps six decimals** (`decimal(14,6)`, on items and on audit lines), so ₱145 for 1,000 ml is stored as ₱0.145000 per ml instead of ₱0.15; peso totals are rounded to the centavo only where they're shown and reported. Other money stays `decimal(12,2)`.

---

## Menu items & sizes

The Excel pricing matrix: one row per size, each with its own **cost** and **selling price**. Margin is computed, never stored (`ItemVariant::costPerSale()`, `profit()`, `marginPercent()`).

**Include linked pieces & liquids in cost** (`items.include_recipe_cost`). When on, the cost the owner types is *their own* cost (labor, packaging) and each sale adds what the links cost at that moment: ₱29 typed + 1 bun at ₱1 = **₱30 per sale**. It's added at every sale and never copied into the cost box, so it can't be counted twice and follows price changes. New menu items start with it on. With it off, the typed cost must already include the links; the editor warns, and Inventory tags the item **not in cost**. The menu export keeps the typed cost (so a re-import doesn't add the links twice) while its profit and margin use the full cost.

The editor shows the margin live, colored green ≥50%, amber ≥25%, red below. `SaveItemRequest` requires at least one size with a price for menu items, and rejects duplicate size names.

## Pieces

Countable ingredients and packaging. `unit_cost` is required, because it prices what a sale consumed. The Pieces tab shows on-hand with a bar against its alert level, used today, unit cost and stock value.

## Bulk & liquids

Containers counted by eye. The tab shows on-hand, average use per day (mean of the last 7 audits), days left (red under 4), unit cost and daily cost, plus when the last audit ran and who did it.

## Containers & exact costs

A liquid is **stored** in ml or grams but **bought and counted** in containers. The owner fills in what's on the receipt:

| Field | Example |
|---|---|
| Measured in | ml (or L, g, kg) |
| How do you buy it? | 1 **bottle** holds **1000** ml · you pay **₱145** |
| On hand now | 3000 ml — with a *Fill from the shelf* helper: "3 full bottles + ½ open" |

The form works out **₱0.145 per ml** itself and shows the maths (`₱145 ÷ 1,000 ml`); nobody types a per-ml price. A cheap sauce keeps its precision too: a ₱45 3,785 ml jug is ₱0.011889 per ml, which two decimals used to store as ₱0.01.

**Several sizes, one item.** "Add another size" adds, say, an 18 L tin at ₱2,340 to the same oil. Stock is one number in ml, so bottles and tins add up. **The cost follows the latest price**: the first container that is new or whose price or size changed sets the cost per unit, so saving the form untouched never overwrites the price of the last restock. (A weighted average would be more exact, and harder to explain.)

**Items without containers work exactly as before** — counted in their own unit ("1L bottle", "tank") with a cost per that unit. Moving such an item to containers means its old count is in a different unit, so the form clears it and the server requires it again (`SaveItemRequest`), instead of silently turning 4.5 bottles into 4.5 ml.

The Bulk tab shows container items as "3 bottles · 3,000 ml" and "₱145 / bottle (₱0.145 / ml)".

## Restock

**Restock** on the item page records stock that was bought: *how many*, *of which container*, *what you paid*.

1. On hand goes up by `quantity × container size`, logged as a **Restock** stock movement (not an adjustment).
2. When a price is given, the cost per unit becomes what this purchase cost (`paid ÷ added`), and that container's price is updated for next time.
3. Optionally (Negosyo plan) the payment is logged in Expenses, e.g. "Cooking oil · 1 tin (18,000 ml)":
   - as a **Stock purchase** when using the item already lowers profit elsewhere (`Item::isCostedWhenUsed()`): menu items that count themselves, bulk, pieces in a recipe, and all pieces when the shop counts them at closing. Stock purchases are listed but **not subtracted from profit**, because the stock is costed when it's used.
   - as **Supplies** otherwise (a paper bag nobody links or counts), which does lower profit, since nothing else ever will.

Pieces and ready-made menu items (bottled water) can be restocked in their own unit too; made-to-order food can't. `App\Services\Inventory\RestockService`.

## Recipe links

"1 Cheeseburger uses 1 bun + 1 patty + 1 cheese + 15 ml ketchup." Optional per item, and a line can apply to **one size only** (16oz cup vs 22oz cup) or to every size.

**Liquids can be in recipes too.** Each sale then deducts the recipe amount (60 burgers × 15 ml = 900 ml), so Today shows how much ketchup is left before anyone counts. At closing the audit still counts the truth and corrects it — see [Closing audit › Liquids in recipes](06-closing-audit.md#liquids-in-recipes). Some liquids are better left audit-only: cooking oil is reused and topped up, so a per-sale amount would be fiction.

The editor shows, per size, *your cost + linked = cost per sale*, with the **Include in cost** checkbox ([above](#menu-items--sizes)). An ingredient must belong to the same shop and be a piece or a bulk item (`Rule::exists` scoped by `business_id` and `kind`).

On the Tindahan plan the section is hidden and submitted recipe lines are ignored, but **links saved earlier are kept and keep working**: saving the item never deletes them, and the editor says what each sale still uses. On a plan with links, clearing every row removes them.

## Link history & approvals

Every change to what one sale uses is a `recipe_changes` row (`App\Services\Inventory\RecipeChangeService`), with before/after snapshots that keep names and units as they were. The item editor shows the last 10 as **Link history**: "Remove 1 pc Beef patty · Maria · Sep 24, 8:25 AM · Saved".

- **Owner saves** are recorded as `saved` when the links really changed.
- **Cashier changes are requests** (`pending`). Nothing changes until the owner approves on Today or in the item's history. A newer request for the same item marks the older one `replaced`; a request identical to the current links isn't created.
- **Approving is refused** when the links changed after the request (it would undo that change), or when a requested piece or size no longer exists. The owner rejects and the cashier asks again.

## Cashier products & deliveries

With `restock_stock` or `link_pieces` on ([Team](09-team-settings.md#cashier-permissions)), cashiers get **Products** in their menu. It never shows names, prices or costs to change, and never shows pesos.

**Restock.** Pieces, liquids and items that count themselves, with what's on hand and a quantity + container box. The count goes up right away (a `Restock` movement by that cashier) and a `deliveries` row waits for the owner. No price and no expense are recorded by the cashier.

**Deliveries to check** (Today, `App\Services\Inventory\DeliveryService`). The owner types what the supplier's receipt says and what was paid:

| Receipt vs recorded | What happens |
|---|---|
| Same | Nothing else moves |
| Receipt says **more** (50 bought, 40 recorded) | 10 pcs never reached the shelf: logged as a **Missing stock** expense at the purchase price (₱75), which lowers profit |
| Receipt says **less** (30 bought, 40 recorded) | The count was too high: corrected by an `Adjustment` of −10 |

The price paid sets the cost per unit (and the container's price), and can be logged as a Stock purchase or Supplies, by the rule in [Restock](#restock). A delivery is checked once. The owner's own restocks don't need checking.

**Links.** Menu items with their current links and a *waiting for owner* tag; the editor sends a request ([above](#link-history--approvals)).

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

`ContainerStockTest`: cost per ml from the bottle price · six decimals for cheap liquids · the scenario audit to the centavo (₱72.50 + ₱11.25 = ₱83.75) · containers and a unit-sized step on the audit screen · a second size and "latest price wins" · restocking a tin with its expense · no expense when unticked or without the Expenses plan · restocking in an item's own unit · no restock for made-to-order food or another shop's item · re-entering the count when moving to ml · items without containers unchanged · a liquid in a recipe comes off the shelf at the sale · "recipes use less than set" · counts above expected stay restocks for liquids in no recipe.

`InventoryTest`: delete an item with no history · refuse to delete one that was sold, counted or linked · no cross-shop delete · the editor offers delete only when it's allowed · create with sizes and size-specific recipe links · update syncs sizes · menu items need a priced size · pieces and bulk need a unit cost · cross-shop piece refused · on-hand edit logs an adjustment · archive/restore · CSV import (including a broken row) · inline category · recipes hidden on Tindahan.

`RecipeChangesTest`, `DeliveryCheckTest`, `StaffProductsTest`: owner saves recorded only when links change · approve/reject from Today · replaced requests · no-op requests · stale and archived-piece approvals refused · cross-shop and cashier approval blocked · deliveries on Today · matching, short, over-recorded and container deliveries · supplies without missing stock · checked once · cashier pages hide pesos and only change links through requests.

## What's left

- Stock take for pieces outside closing — pieces can be counted at closing ([06](06-closing-audit.md#counting-pieces)), otherwise per item.
- Supplier records and purchase orders.
- `.xlsx` import; CSV only.
