# Module 02 · Access control (RBAC)

Decides **which pages** each role can open. It does *not* limit **whose data** they see; that is [Business & tenancy](03-business-tenancy.md).

| Feature | Status |
|---|---|
| [Roles](#feature-roles) | ✅ Built |
| [Role middleware](#feature-role-middleware) | ✅ Built |
| [Role home redirect](#feature-role-home-redirect) | ✅ Built |
| [Role-based navigation](#feature-role-based-navigation) | ✅ Built |
| [Cashier permissions (per action)](#feature-cashier-permissions) | ⬜ Not started |

---

## Feature: Roles

**Status:** ✅ Built

Stored on `users.role`, a MySQL `enum('super_admin','admin','staff')` with default `admin` (`database/migrations/0001_01_01_000000_create_users_table.php`).

| Role | Who | How the account is created |
|---|---|---|
| `super_admin` | SaaS operator (us) | Seeder only |
| `admin` | Business owner / manager | Sign-up (column default) |
| `staff` | Cashier | Seeder only today → owner invite later ([Team › Invites](09-team-settings.md#feature-staff-invites)) |

`role` is in `User`'s `#[Fillable]`, so **never pass request input straight into `User::create()` or `update()`**. A user could promote themselves. `ProfileUpdateRequest` only validates `name` and `email`, which keeps it safe today.

### Backend to build

- [ ] `App\Enums\Role` (TitleCase keys: `SuperAdmin`, `Admin`, `Staff`) with string values; cast `role` to it in `User::casts()`.
- [ ] Remove `role` from `#[Fillable]` and set it explicitly (`$user->role = Role::Staff`) wherever accounts are created.
- [ ] Helpers on `User`: `isSuperAdmin()`, `isAdmin()`, `isStaff()`.

---

## Feature: Role middleware

**Status:** ✅ Built

### Files

- `app/Http/Middleware/RoleMiddleware.php`
- `bootstrap/app.php`: `$middleware->alias(['role' => RoleMiddleware::class]);`

### Usage

```php
Route::middleware(['auth', 'verified', 'role:admin'])->group(...);        // one role
Route::middleware(['auth', 'verified', 'role:staff|admin'])->group(...);  // several roles
```

### How it works

1. Not logged in → redirect `/login`.
2. `auth()->user()->role` is in the `|`-separated list → continue.
3. Otherwise → **redirect to the user's own home screen** (not a 403). For example, a cashier opening `/admin/reports` lands on `/pos`.

### Route map

| URI prefix | Middleware | Screens |
|---|---|---|
| `/dashboard` | auth, verified | Redirect only |
| `/pos`, `/audit` | auth, verified, `role:staff\|admin` | Register, closing audit |
| `/staff/*` | auth, verified, `role:staff` | My orders |
| `/admin/*` | auth, verified, `role:admin` | Today, inventory, expenses, reports, team, settings |
| `/super-admin/*` | auth, verified, `role:super_admin` | Overview, businesses, plans |
| `/profile` | auth, verified | Any role |

Live list: `php artisan route:list --except-vendor`.

### Backend to build

- [ ] Use `in_array($role, $allowed, true)` (strict).
- [ ] Return a 403 instead of redirecting for JSON requests (`$request->expectsJson()`), once the POS posts orders by AJAX.

### Tests

`tests/Feature/UiScreensTest.php`: ✅ each role can open all its screens; staff are redirected away from admin pages; admins are redirected away from the platform console.

---

## Feature: Role home redirect

**Status:** ✅ Built

`GET /dashboard` (`dashboard`, closure in `routes/web.php`) sends each role to its home screen:

| Role | Redirects to |
|---|---|
| `super_admin` | `super_admin.dashboard` |
| `admin` | `admin.dashboard` |
| `staff` | `pos` (cashiers start on the register) |
| anything else | `home` |

### Known issue

The same `match` also lives in `RoleMiddleware`, so the logic is duplicated.

### Backend to build

- [ ] A single `User::homeRoute(): string`, used by both the `/dashboard` route and `RoleMiddleware`.

### Tests

`UiScreensTest › sends each role to its home screen`: ✅ passing.

---

## Feature: Role-based navigation

**Status:** ✅ Built

`resources/views/layouts/app.blade.php` builds the sidebar from `auth()->user()->role`, so each role only sees links it can open:

| Role | Sidebar |
|---|---|
| `staff` | Register, Closing audit, My orders |
| `admin` | Today, Register, Inventory, Closing audit, Expenses, Profit & ledger, Team, Settings |
| `super_admin` | Overview, Businesses, Plans & billing |

Hiding a link is only cosmetic. The route middleware is what actually enforces access.

---

## Feature: Cashier permissions

**Status:** ⬜ Not started (the toggles exist on the Team screen as UI only)

Roles control pages. Some **actions** inside a page need a finer rule the owner can switch on or off per business:

| Permission | Default | Used in |
|---|---|---|
| `audit.run` | on | Closing audit submit |
| `costs.view` | off | Audit peso totals, margins |
| `orders.void` | off | Void a paid order (off = send a request instead) |
| `expenses.create` | on | Quick expense entry |

### Backend to build

- [ ] Store the permissions in `businesses.settings` (JSON) → `cashier_permissions`.
- [ ] Define Gates in `AppServiceProvider`, e.g.
  `Gate::define('void-order', fn (User $user) => $user->isAdmin() || $user->business->allows('orders.void'));`
- [ ] Use `@can` in views (replace the current `$canSeeCosts = role === 'admin'` in `audit/index.blade.php`) and `$this->authorize()` / `Gate::authorize()` in controllers.
- [ ] Policies per model once models exist (`OrderPolicy`, `ItemPolicy`, `ExpensePolicy`), each also checking that `business_id` matches.

### Done when

- An owner turns off "See cost prices" and a cashier's audit screen hides peso totals, **and** the cost fields are missing from the API response too.
