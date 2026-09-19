# iPOSa Documentation

Organized **by module**. Each module file lists its features, and each feature has the same sections:
**Status · Who · Routes · Files · How it works · Backend to build · Done when · Tests**.

> **Update, 19 Sep 2026: the backend is built.** Modules 02–10 now run on real data. The per-module files still carry their original "Backend to build" checklists; use the table below and the **What's built** section for the current state. The remaining gaps are listed at the end.

## Status legend

| Mark | Meaning |
|---|---|
| ✅ Built | Real backend code, working and tested |
| 🟡 Partial | Built, with the gaps listed below |
| ⬜ Not started | Not built |

## Modules

| # | Module | Status | Tests |
|---|---|---|---|
| 01 | [Authentication](modules/01-authentication.md) | ✅ | `Auth/*`, `ProfileTest`, `SubscriptionAccessTest` (sign-up) |
| 02 | [Access control (RBAC)](modules/02-access-control.md) | ✅ roles, middleware, cashier permission gates | `UiScreensTest`, `ClosingAuditTest`, `VoidOrderTest`, `ExpenseTest` |
| 03 | [Business & tenancy](modules/03-business-tenancy.md) | ✅ staff membership, data scoping, trial, plans, suspension | `TenancyTest`, `SubscriptionAccessTest` |
| 04 | [Inventory](modules/04-inventory.md) | ✅ | `InventoryTest` |
| 05 | [Register (POS)](modules/05-pos.md) | ✅ including offline selling | `PosCheckoutTest`, `VoidOrderTest`, `PwaTest` |
| 06 | [Closing audit](modules/06-closing-audit.md) | ✅ (🟡 no reminder notification) | `ClosingAuditTest` |
| 07 | [Expenses](modules/07-expenses.md) | ✅ | `ExpenseTest` |
| 08 | [Reports & analytics](modules/08-reports.md) | ✅ | `ReportsTest` |
| 09 | [Team & settings](modules/09-team-settings.md) | ✅ (manual billing) | `TeamTest`, `SettingsAndBillingTest` |
| 10 | [Platform console](modules/10-platform.md) | ✅ (🟡 no impersonation) | `SuperAdminBusinessesTest`, `SettingsAndBillingTest`, `SubscriptionAccessTest` |
| 11 | [UI foundation](modules/11-ui-foundation.md) | ✅ | `UiScreensTest` |

## What's built

| Area | Key files |
|---|---|
| Tenancy | `app/Models/Concerns/BelongsToBusiness.php` (global scope + auto `business_id`), `users.business_id`, `EnsureBusinessAccess` middleware (no business → 403, suspended → logout, unpaid → only settings/billing/exports) |
| Plans | `config/plans.php` (Tindahan ₱499: 3 staff, no expenses/P&L/recipes · Negosyo ₱999), `EnsurePlanFeature` middleware (`plan:expenses`, `plan:reports`) |
| Cashier permissions | Gates in `AppServiceProvider`: `run-audit`, `view-costs`, `void-orders`, `log-expenses`, `correct-audit`; stored in `businesses.settings` |
| Inventory | `Item` (kind menu/piece/bulk), `ItemVariant`, `RecipeLine` (per item or per size), `Category`, `StockMovement`; `ItemService`, `StockService`, `MenuImportService` (CSV) |
| Register | `CheckoutService`: prices from the DB, idempotent by uuid, per-shop order numbers, recipe deduction in one transaction; `VoidOrderService`; 58mm receipt view |
| Closing audit | `ClosingAuditService`: one audit per day, owner corrections move stock by the difference, restocks detected |
| Expenses | `Expense`, `Asset` (installments → Payables expense once), month view, CSV |
| Reports | `App\Reports\DailyLedger`: one query class for Today, P&L, ledger, best sellers, exports |
| PWA & offline | `public/manifest.webmanifest`, icons in `public/icons`, service worker `resources/pwa/sw.js` (served by `ServiceWorkerController`, versioned per build), `resources/js/offline-queue.js`: the register opens offline, sales queue in IndexedDB and sync with their uuid and original time (`offline_created_at`). Other pages show `public/offline.html` |
| Exports | `App\Exports\BusinessExports` + `ExportController`: ledger, expenses, menu, stock, orders, audits, or everything as a zip. Always allowed, even unpaid |
| Team | `TeamService`: invite (email with set-password link), resend, remove (orders keep the name), permissions |
| Billing | `SubscriptionService`: change plan (checks staff limit), submit GCash/bank reference, operator confirms (+30 days) or rejects, extend trial, suspend (password required), unsuspend; daily `businesses:mark-overdue` |
| Platform | `PlatformDashboardController` (MRR, collected per month, funnel, gone quiet, trials ending), `BusinessController` (list, detail, actions), `PlanController`, `SubscriptionPaymentController` |

## Remaining gaps (not built)

- **Payment gateway** (PayMongo). Billing is manual: the owner sends a reference, the operator confirms it.
- **Closing-audit reminder** at the configured time (the setting is saved; nothing sends a notification yet).
- **`.xlsx` exports.** CSV/zip only; `.xlsx` needs a new package.
- **Impersonation** ("view as owner") on the platform console.
- **Bluetooth / ESC-POS printers.** Receipts print through the browser.
- **Cron:** production must run `php artisan schedule:run` every minute for the daily overdue check.

## Where things live

```
routes/web.php                app routes, grouped by role (+ business, plan, can: middleware)
routes/console.php            daily businesses:mark-overdue
config/plans.php              plans, prices, limits, manual payment details
app/Models/                   Business, User, Item, ItemVariant, RecipeLine, Category, StockMovement,
                              Order, OrderLine, Audit, AuditLine, Expense, Asset, SubscriptionPayment
app/Services/                 Pos/, Inventory/, Audit/, Billing/, Team/, RegisterBusinessUserService
app/Reports/DailyLedger.php   every profit number
app/Http/Controllers/         Pos/, Staff/, Admin/, SuperAdmin/, Audit, Expense, Business
database/seeders/             UserSeeder, DemoShopSeeder (14 days of real activity), BusinessSeeder
tests/Feature/                one file per module (123 tests)
```

## Test status (last run)

**123 of 123 pass** (405 assertions): `php artisan test --compact`.
