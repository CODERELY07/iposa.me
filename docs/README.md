# iPOSa documentation

iPOSa is a POS, inventory and daily-profit app for small food businesses in the Philippines: a register a cashier can learn in five minutes, stock that counts itself, a 60-second closing audit, and one honest number at the end of the day.

```
Net = Sales − Ingredients (COGS) − Bulk used − Expenses
```

**The backend is built.** Every module below runs on real data and is covered by tests. Each module file has the same shape: *status table → routes → data model → how it works → tests → what's left*.

## Status legend

| Mark | Meaning |
|---|---|
| ✅ Built | Real code, working and tested |
| 🟡 Partial | Built, with the gap named in the module's "What's left" |
| ⬜ Not built | Listed in [ROADMAP.md](ROADMAP.md) |

## Modules

| # | Module | State | Tests |
|---|---|---|---|
| 01 | [Authentication](modules/01-authentication.md) | ✅ sign-up creates a shop, verification, resets, email-failure fallback | `Auth/*`, `ProfileTest`, `ManualVerificationTest` |
| 02 | [Access control (RBAC)](modules/02-access-control.md) | ✅ roles, middleware, cashier permission gates | `UiScreensTest`, `ClosingAuditTest`, `VoidOrderTest` |
| 03 | [Business & tenancy](modules/03-business-tenancy.md) | ✅ scoping, trial, plans, suspension | `TenancyTest`, `SubscriptionAccessTest` |
| 04 | [Inventory](modules/04-inventory.md) | ✅ menu/pieces/bulk, recipes, CSV import, archive & delete | `InventoryTest` |
| 05 | [Register (POS)](modules/05-pos.md) | ✅ idempotent checkout, receipts, voids | `PosCheckoutTest`, `VoidOrderTest` |
| 06 | [Closing audit](modules/06-closing-audit.md) | 🟡 built; the reminder isn't sent yet | `ClosingAuditTest` |
| 07 | [Expenses & equipment](modules/07-expenses.md) | ✅ month view, installments | `ExpenseTest` |
| 08 | [Today & reports](modules/08-reports.md) | ✅ one ledger feeds every number | `ReportsTest` |
| 09 | [Team, settings & billing](modules/09-team-settings.md) | ✅ manual billing (no gateway) | `TeamTest`, `SettingsAndBillingTest` |
| 10 | [Platform console](modules/10-platform.md) | ✅ metrics, business editing, trash, tenant actions, plan CRUD, verifications | `SuperAdminBusinessesTest`, `BusinessEditTest`, `BusinessTrashTest`, `PlanManagementTest` |
| 11 | [UI foundation](modules/11-ui-foundation.md) | ✅ shell, theme, feedback, components | `UiScreensTest` |
| 12 | [Installable app & offline selling](modules/12-pwa-offline.md) | ✅ register sells offline and syncs | `PwaTest` |
| 13 | [Deployment & operations](modules/13-deployment.md) | ✅ Docker + Render + Postgres | — |

Step-by-step hosting guide: **[DEPLOY-RENDER.md](DEPLOY-RENDER.md)**. What's still to build: **[ROADMAP.md](ROADMAP.md)**.

## The decisions that shaped everything

| Decision | Why it matters |
|---|---|
| **One business = one branch = one subscription** | No `branch_id` anywhere; a second branch is a second shop |
| `users.business_id` + a global scope (`BelongsToBusiness`) | Cross-shop data leaks are impossible by default, not by discipline |
| **Order lines copy name, price and cost at sale time** | Editing a price today never rewrites last month's profit |
| Money `decimal(12,2)`, stock `decimal(12,3)` | Half a bottle of oil is a real quantity |
| **Checkout is idempotent by client `uuid`** | A retry — or a sale synced twice from offline — can't double-charge |
| Nothing changes `on_hand` outside `StockService` | Every number has a `stock_movements` row explaining it |
| One `DailyLedger` class behind every report | Today, the P&L and the CSV can't disagree |
| Email is assumed to fail | Sign-ups, invites and resets survive a dead SMTP server |
| **A shop's price is locked on the business, not read from the plan** | The operator can raise a price without changing anyone's bill mid-period |

## Where things live

```
routes/web.php                every app route, grouped by role (+ business, plan, can: middleware)
routes/console.php            daily businesses:mark-overdue
config/plans.php              trial length, billing period, manual payment details (plans live in the DB)
config/iposa.php              platform operator + support contact settings
app/Models/                   Business, User, Item, ItemVariant, RecipeLine, Category, StockMovement,
                              Order, OrderLine, Audit, AuditLine, Expense, Asset, SubscriptionPayment
app/Models/Concerns/          BelongsToBusiness (the tenancy scope)
app/Http/Middleware/          RoleMiddleware, EnsureBusinessAccess, EnsurePlanFeature
app/Services/                 Pos/, Inventory/, Audit/, Billing/, Team/, RegisterBusinessUserService
app/Reports/DailyLedger.php   every profit number
app/Exports/                  BusinessExports (ledger, expenses, menu, stock, orders, audits)
app/Support/SafeMail.php      send mail without letting a failure break the request
resources/pwa/sw.js           service worker source (served by ServiceWorkerController)
resources/js/offline-queue.js IndexedDB queue for offline sales
docker/                       start.sh, boot.sh, supervisord, nginx, php-fpm
database/seeders/             UserSeeder, DemoShopSeeder (14 days of activity), BusinessSeeder
tests/Feature/                one file per module
```

## Running it locally

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed
composer run dev
```

Demo logins after seeding (password `password`): `admin@gmail.com` (owner), `staff@gmail.com` (cashier). They are no longer printed on the login page — the seeder is the only place they're listed.

## Tests

```bash
php artisan test --compact
```

**181 of 181 pass** (679 assertions). Run one file or one test with a path or `--filter=`. PHP style: `vendor/bin/pint --dirty`.
