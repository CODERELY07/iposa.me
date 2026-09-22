# iPOSa

**POS, inventory and true daily profit for food businesses in the Philippines.**
Cafés, burger stands, milk tea shops and carinderias ring up orders, count bulk supplies in 60 seconds at closing, and see tonight's real profit in pesos.

```
Net = Sales − Ingredients (COGS) − Bulk used − Expenses
```

> **Status:** the app is built and deployed — sign-up through closing audit runs on real data, works offline, and is covered by 180 tests. What's not built is listed in [docs/ROADMAP.md](docs/ROADMAP.md).

---

## What it does

| Area | Summary |
|---|---|
| **Register (POS)** | Color tiles by category, sizes as separate taps, Cash/GCash/Maya, 58mm receipts, **keeps selling offline** |
| **Inventory** | Three kinds of stock: *menu items* (sold), *pieces* (buns, patties — deducted by recipe), *bulk & liquids* (oil, mayo — counted by eye) |
| **Closing audit** | Staff type what's left (oil 5 → 4.5); the drop becomes the day's bulk cost |
| **Expenses** | Entries by date and category, plus equipment installments that hit the P&L exactly once |
| **Today & reports** | Profit so far vs yesterday at this hour, the daily ledger, best sellers, CSV/zip export |
| **Team & billing** | Cashier invites, per-shop permissions, manual GCash/bank subscriptions |
| **Platform console** | For the operator: MRR, trial funnel, shops that went quiet, plans and prices, suspensions, manual email verification |

## Roles

| Role | Who | Lands on |
|---|---|---|
| `super_admin` | Platform operator (us) | `/super-admin` |
| `admin` | Shop owner — one business, one branch | `/admin` |
| `staff` | Cashier | `/pos` |

Details: [Access control](docs/modules/02-access-control.md).

## Tech stack

Laravel 13 · PHP 8.4 · Breeze (Blade) · Postgres in production / MySQL or SQLite locally · Tailwind CSS 3 · Alpine.js 3 · Vite · Pest 5. **No other frontend dependencies.**

---

## Getting started

Requirements: PHP 8.4, Composer, Node 20+, and MySQL 8 (or change `DB_CONNECTION` to `sqlite`).

```bash
git clone <repo-url> iposa.me
cd iposa.me
composer run setup
php artisan migrate:fresh --seed
composer run dev
```

`composer run setup` installs dependencies, copies `.env`, generates the key, migrates and builds assets. `composer run dev` starts the server (http://localhost:8000), the queue listener and Vite together.

In `.env`, `MAIL_MAILER=log` sends verification emails to `storage/logs/laravel.log`.

### Demo accounts

Seeded verified, password `password` (local development only — the login page no longer lists them):

| Email | Role |
|---|---|
| `admin@gmail.com` | Owner of the demo shop, with 14 days of activity |
| `staff@gmail.com` | Cashier at the same shop |
| (the operator email in `UserSeeder`) | Platform operator — local only, never seeded in production |

## Testing

```bash
php artisan test --compact
```

180 tests, 677 assertions. Format PHP before committing:

```bash
vendor/bin/pint --dirty
```

## Deploy

Production runs as a Docker web service (nginx + PHP 8.4-FPM + scheduler) with managed Postgres, described by the Render Blueprint `render.yaml`.

1. Push to GitHub.
2. Render → **New → Blueprint** → pick the repo.
3. Fill in `SUPER_ADMIN_EMAIL`, `SUPER_ADMIN_PASSWORD`, the `MAIL_*` values and the billing details, then **Apply**.

Migrations run in the background on every deploy, after the port opens. Full guide, costs and troubleshooting: [docs/DEPLOY-RENDER.md](docs/DEPLOY-RENDER.md) · why it's built this way: [Deployment & operations](docs/modules/13-deployment.md).

## Project structure

```
app/
  Http/Middleware/      RoleMiddleware (role:a|b), EnsureBusinessAccess, EnsurePlanFeature
  Models/Concerns/      BelongsToBusiness — the tenant scope every shop model uses
  Services/             Pos/, Inventory/, Audit/, Billing/, Team/, RegisterBusinessUserService
  Reports/DailyLedger   every profit number on every screen
  Exports/              streamed CSV + zip
  Support/SafeMail      send mail without letting a failure break the request
routes/web.php          app routes grouped by role, with business/plan/can middleware
config/plans.php        trial length, billing period, manual payment details
resources/
  views/                layouts, pos, audit, staff, admin, super_admin, components
  js/app.js             Alpine stores: theme, loader, register, audit, offline queue
  js/offline-queue.js   IndexedDB queue for sales made offline
  pwa/sw.js             service worker source (versioned per build)
docker/                 start.sh, boot.sh, supervisord, nginx, php-fpm
docs/                   README.md (index), modules/, ROADMAP.md, DEPLOY-RENDER.md
tests/Feature/          one file per module
```

## Conventions

- **Dark mode by default**, saved per browser, applied before paint.
- **Money** always uses `.num` (tabular monospace), right-aligned in tables, `₱1,234.00`; losses in (brackets).
- **Every action gives feedback:** `<x-busy-button loading-text="Saving…" done-text="Saved">`, plus the navigation loader.
- Shop data is reached through the tenant scope — never a raw `Business::find()` on a request path.
- Run `vendor/bin/pint --dirty` after touching PHP.

## Documentation

Start at **[docs/README.md](docs/README.md)**. One file per module: status, routes, data model, how it works, tests, and what's left.

| Module | |
|---|---|
| 01 | [Authentication](docs/modules/01-authentication.md): sign-up, login, verification, resets, email-failure fallback |
| 02 | [Access control (RBAC)](docs/modules/02-access-control.md): roles, middleware, cashier permissions |
| 03 | [Business & tenancy](docs/modules/03-business-tenancy.md): the business record, scoping, trial, plans, suspension |
| 04 | [Inventory](docs/modules/04-inventory.md): menu & sizes, pieces, bulk, recipes, low stock, CSV import |
| 05 | [Register (POS)](docs/modules/05-pos.md): menu grid, checkout, stock deduction, receipts, voids |
| 06 | [Closing audit](docs/modules/06-closing-audit.md): the 60-second count and the day's bulk cost |
| 07 | [Expenses & equipment](docs/modules/07-expenses.md): month view, categories, installments |
| 08 | [Today & reports](docs/modules/08-reports.md): the daily ledger, dashboard, P&L, exports |
| 09 | [Team, settings & billing](docs/modules/09-team-settings.md): invites, permissions, plans, payments |
| 10 | [Platform console](docs/modules/10-platform.md): metrics, tenant actions, email verifications |
| 11 | [UI foundation](docs/modules/11-ui-foundation.md): shell, theme, feedback, components |
| 12 | [Installable app & offline selling](docs/modules/12-pwa-offline.md): manifest, service worker, sync |
| 13 | [Deployment & operations](docs/modules/13-deployment.md): image, boot, environment, scheduler |

What's next: [docs/ROADMAP.md](docs/ROADMAP.md).
