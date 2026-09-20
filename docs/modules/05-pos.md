# Module 05 · Register (POS)

The cashier's screen: fast to tap, readable at arm's length, and impossible to double-charge.

**Who:** cashiers and owners (`role:staff|admin`).

| Feature | Status |
|---|---|
| [Menu grid](#menu-grid) | ✅ Built |
| [Cart](#cart) | ✅ Built |
| [Checkout](#checkout) | ✅ Built |
| [Stock deduction](#stock-deduction) | ✅ Built |
| [Receipts](#receipts) | ✅ Built |
| [My orders](#my-orders) | ✅ Built |
| [Voids](#voids) | ✅ Built |
| [Offline selling](#offline-selling) | ✅ Built |

## Routes

| Method | URI | Name | Handler |
|---|---|---|---|
| GET | `/pos` | `pos` | `Pos\RegisterController@index` |
| POST | `/pos/orders` | `pos.orders.store` | `Pos\RegisterController@store` (JSON) |
| GET | `/pos/orders/{order}/receipt` | `pos.orders.receipt` | `Pos\OrderController@receipt` |
| POST | `/pos/orders/{order}/void` | `pos.orders.void` | `Pos\OrderController@void` |
| GET | `/staff/orders` | `staff.orders` | `Staff\MyOrdersController` |
| POST | `/admin/orders/{order}/void/approve` · `/reject` | `admin.orders.void.approve` · `.reject` | `Admin\VoidRequestController` |

## Data model

| Table | Columns |
|---|---|
| `orders` | `business_id`, `number` (per shop), `uuid` (unique per shop), `user_id`, `cashier_name`, `payment_method`, `subtotal`, `tendered`, `change`, `status`, `paid_at`, `void_requested_by`, `voided_by`, `voided_at` |
| `order_lines` | `order_id`, `item_id`, `item_variant_id`, `name`, `variant_label`, `price`, `unit_cost`, `qty` |

**Lines copy the name, size, price and cost at sale time**, so reports never change when the menu is edited later.

---

## Menu grid

Colored text tiles (no photos, for speed), grouped by category, searchable with the `/` key. Sizes are separate taps, never a pop-up. A "N left" pill appears when stock runs low, worked out from the linked pieces (`min(piece on hand ÷ recipe qty)`).

**The payload never contains cost prices**, so margins stay private even from cashiers.

## Cart

Client-side (Alpine). Every tap answers back: the tile rings and pops, the cart line highlights, an "Added …" toast appears, and the phone vibrates briefly. Quantity steppers, clear, and a payment method picker limited to what the owner enabled.

## Checkout

Cash asks for the amount received (Exact / ₱100 / ₱500 / ₱1000) and shows the change; GCash and Maya ask the cashier to confirm on the customer's phone.

`CheckoutService` runs one transaction:

1. Lock the business row, and return the existing order if this `uuid` was already used (safe retries).
2. Load the variants **from the database**, refusing anything archived or from another shop.
3. Recompute the total from database prices; cash must cover it.
4. Increment the shop's order number.
5. Create the order and its lines.
6. Deduct stock ([below](#stock-deduction)).

The button locks and shows "Processing sale, please wait…". Server errors appear in the modal with **Try again**.

## Stock deduction

| Menu item | Deducts |
|---|---|
| Has recipe lines for that size | Each linked piece × quantity sold |
| No recipe lines, but tracks its own stock | Itself |
| No recipe, no stock tracking | Nothing |

Items are locked (`lockForUpdate`) while updating, and every change writes a `Sale` stock movement tied to the order. Stock may go negative: a real kitchen sometimes sells before it counts, and the dashboard flags it rather than blocking the sale.

## Receipts

`/pos/orders/{order}/receipt` prints through the browser, styled for 58mm paper: shop name, address, TIN, order number, lines, total, payment, change and the receipt footer from Settings. Voided orders are stamped VOIDED. Reprint any order from My orders.

## My orders

Today's orders for the logged-in cashier, with drawer totals per payment method so they can count cash before handover. Reprint, and void or request a void per order. When allowed, a quick expense form for ice or LPG bought from the drawer.

## Voids

| Who | What happens |
|---|---|
| Cashier without `void_orders` | Order becomes **Void requested**; the owner sees it on Today |
| Cashier with `void_orders`, or the owner | Voided immediately |

Voiding reverses exactly the movements the sale made (`Void` reason) and excludes the order from every report. The owner can also reject a request, returning the order to Paid.

## Offline selling

The register works without internet: the page is cached, sales queue on the device and sync by themselves. Full details in [PWA & offline](12-pwa-offline.md).

Server side, a synced sale carries `offline_created_at` (accepted within the last 7 days, never in the future) so `paid_at` is when the sale actually happened.

---

## Tests

`PosCheckoutTest`: recipe deduction · size-specific recipes · items that count themselves · the same uuid never charges twice · prices come from the menu, not the browser · cash must cover the total · per-shop order numbering · cross-shop and archived items refused · disabled payment method refused · no costs in the payload · receipt output.

`VoidOrderTest`: request, approve (stock back), reject, direct void with permission, voided orders leave sales, no double void, cross-shop 404.

`PwaTest`: offline sale time, and no double charge on re-sync.

## What's left

- Discounts, promos and senior/PWD discounts.
- Split payments and partial refunds.
- Customer records or loyalty.
- Bluetooth/ESC-POS printing (browser printing works today).
