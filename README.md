# iPOSa

**POS, inventory and true daily profit for food businesses in the Philippines.**
Cafés, burger stands, milk tea shops and carinderias ring up orders, count bulk supplies in 60 seconds at closing, and see tonight's real profit in pesos.

> Status: early MVP. Authentication, email verification, roles and business sign-up are real. The app screens are a UI prototype with static data. See [docs/ROADMAP.md](docs/ROADMAP.md).

---

## What it does

| Area | Summary |
|---|---|
| **Register (POS)** | Color tiles by category, sizes as separate taps, cart, Cash/GCash/Maya checkout |
| **Inventory** | Three kinds of stock: *menu items* (sold), *pieces* (buns, patties: deducted by recipe), *bulk & liquids* (oil, mayo: counted by eye) |
| **Closing audit** | Staff type what's left (e.g. oil 5 → 4.5); the drop becomes the day's bulk cost |
| **Expenses** | Excel-style entries by date and category, plus equipment installments |
| **Profit & ledger** | Sales − ingredients − bulk used − expenses = true profit, day by day; CSV export |
| **Platform console** | For the SaaS operator: businesses, trials, plans, MRR |

## Roles

| Role | Who | Lands on |
|---|---|---|
| `super_admin` | SaaS operator (us) | `/super-admin` |
| `admin` | Business owner, one business with one branch | `/admin` |
| `staff` | Cashier | `/pos` |

Details: [Access control module](docs/modules/02-access-control.md).

## Tech stack

Laravel 13 · PHP 8.4 · Breeze (Blade) · MySQL · Tailwind CSS 3 · Alpine.js 3 · Vite · Pest 5

---

## Getting started

### Requirements

PHP 8.4, Composer, Node 20+, MySQL 8.

### Install

```bash
git clone <repo-url> iposa.me
cd iposa.me
composer run setup
```

`composer run setup` installs PHP and JS dependencies, copies `.env`, generates the app key, runs migrations and builds the assets.

Then in `.env`:

```dotenv
APP_NAME=iPOSa
DB_DATABASE=iposa.me
DB_USERNAME=root
DB_PASSWORD=
MAIL_MAILER=log        # verification emails go to storage/logs/laravel.log
```

Seed the demo accounts:

```bash
php artisan migrate:fresh --seed
```

### Run

```bash
composer run dev
```

This starts the Laravel server (http://localhost:8000), the queue worker and Vite together.

### Demo accounts

| Email | Password | Role |
|---|---|---|
| `super_admin@gmail.com` | `password` | super_admin |
| `admin@gmail.com` | `password` | admin |
| `staff@gmail.com` | `password` | staff |

> Every app route requires a verified email, and the seeder doesn't verify these accounts yet. Until that's fixed, verify them by hand:
> ```bash
> php artisan tinker --execute 'App\Models\User::whereNull("email_verified_at")->update(["email_verified_at" => now()]);'
> ```

---

## Testing

```bash
php artisan test --compact
```

Format PHP before committing:

```bash
vendor/bin/pint --dirty
```

## Project structure

```
app/
  Http/Middleware/RoleMiddleware.php    role:<a|b> route guard
  Services/RegisterBusinessUserService  sign-up: user + business in one transaction
  Models/User.php, Business.php
routes/
  web.php                               app routes grouped by role
  auth.php                              Breeze auth + email verification
resources/
  views/layouts/app.blade.php           role-aware sidebar shell
  views/components/                     icon, busy-button, page-loader, closing-receipt, …
  views/pos, audit, staff, admin, super_admin
  js/app.js                             Alpine stores: theme, loader, POS, audit
  css/app.css                           design tokens (.num, .btn-*, .field, .surface)
docs/
  README.md                             documentation index + status per module
  modules/NN-<module>.md                one file per module, organized by feature
  ROADMAP.md                            step-by-step plan to the MVP
```

## UI conventions

- **Dark mode by default**; the choice is saved per browser.
- **Money** always uses the `.num` class (tabular monospace), is right-aligned in tables and formatted as `₱1,234.00`.
- **Every action gives feedback:** `<x-busy-button loading-text="Saving…" done-text="Saved">`, and the page loader shows on navigation.
- Views hold their demo data as `$name = $name ?? [...]`. A controller that passes `$name` replaces it with no view changes.

## Documentation

Start at **[docs/README.md](docs/README.md)**. Each module has its own file, organized by feature (status, routes, files, how it works, backend to build, done when, tests):

| Module | |
|---|---|
| 01 | [Authentication](docs/modules/01-authentication.md): sign-up, login, email verification, password reset, profile |
| 02 | [Access control (RBAC)](docs/modules/02-access-control.md): roles, role middleware, cashier permissions |
| 03 | [Business & tenancy](docs/modules/03-business-tenancy.md): business record, staff membership, data scoping, trial |
| 04 | [Inventory](docs/modules/04-inventory.md): menu items & sizes, pieces, bulk, recipe links, low stock |
| 05 | [Register (POS)](docs/modules/05-pos.md): menu grid, cart, checkout, stock deduction, receipts |
| 06 | [Closing audit](docs/modules/06-closing-audit.md): daily count, usage cost |
| 07 | [Expenses](docs/modules/07-expenses.md): expense log, equipment & payables |
| 08 | [Reports & analytics](docs/modules/08-reports.md): daily ledger, Today dashboard, P&L, export |
| 09 | [Team & settings](docs/modules/09-team-settings.md): staff invites, business profile, billing |
| 10 | [Platform console](docs/modules/10-platform.md): super admin metrics, businesses, plans, payments |
| 11 | [UI foundation](docs/modules/11-ui-foundation.md): shell, theme, loading feedback, components |

Build order: [docs/ROADMAP.md](docs/ROADMAP.md).
