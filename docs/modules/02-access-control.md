# Module 02 · Access control (RBAC)

Decides **which pages** a user can open and **which actions** they may take. *Whose data* they see is [Business & tenancy](03-business-tenancy.md).

| Feature | Status |
|---|---|
| [Roles](#roles) | ✅ Built |
| [Role middleware](#role-middleware) | ✅ Built |
| [Role home redirect](#role-home-redirect) | ✅ Built |
| [Role-based navigation](#role-based-navigation) | ✅ Built |
| [Cashier permissions](#cashier-permissions) | ✅ Built |

---

## Roles

`users.role` is a MySQL/Postgres enum with default `admin`.

| Role | Who | Home screen |
|---|---|---|
| `super_admin` | Platform operator (you) | `/super-admin` |
| `admin` | Shop owner | `/admin` |
| `staff` | Cashier | `/pos` |

Constants and helpers live on `App\Models\User`: `ROLE_SUPER_ADMIN`, `ROLE_ADMIN`, `ROLE_STAFF`, `isSuperAdmin()`, `isAdmin()`, `isStaff()`, `homeRoute()`.

**`role` is not mass-assignable.** It's set explicitly when accounts are created (sign-up, invites, `app:ensure-super-admin`), so a crafted form can never promote a user.

Accounts are created by: sign-up (`admin`), owner invites ([Team](09-team-settings.md#staff-invites), `staff`), `php artisan app:ensure-super-admin` (`super_admin`), and seeders in local development.

## Role middleware

`App\Http\Middleware\RoleMiddleware`, aliased `role` in `bootstrap/app.php`.

```php
Route::middleware(['auth', 'verified', 'role:admin', 'business'])->group(...);
Route::middleware(['auth', 'verified', 'role:staff|admin', 'business'])->group(...);
```

1. Not logged in → `/login`.
2. Role is in the `|` list → continue.
3. Otherwise → **redirect to the user's own home screen** (JSON requests get 403). A cashier opening `/admin/reports` simply lands back on `/pos`.

### Route map

| URI prefix | Middleware | Screens |
|---|---|---|
| `/dashboard` | auth, verified | Redirect by role |
| `/pos`, `/audit`, `POST /expenses` | auth, verified, `role:staff\|admin`, `business` | Register, closing audit, quick expense |
| `/staff/*` | auth, verified, `role:staff`, `business` | My orders |
| `/admin/*` | auth, verified, `role:admin`, `business` | Today, inventory, expenses, reports, team, settings |
| `/super-admin/*` | auth, verified, `role:super_admin` | Platform console (no `business` middleware: the operator has no shop) |
| `/profile` | auth, verified | Any role |

Live list: `php artisan route:list --except-vendor`.

## Role home redirect

`GET /dashboard` sends each role to `User::homeRoute()`. `RoleMiddleware` uses the same method, so the rule lives in one place.

## Role-based navigation

`resources/views/layouts/app.blade.php` builds the sidebar from the role, and hides what the plan doesn't include (Expenses and Profit & ledger on Tindahan) or the cashier isn't allowed (Closing audit without `run_audit`). Hiding a link is cosmetic; the middleware is what enforces it.

## Cashier permissions

Owners can do everything in their shop. Cashiers are limited per business, switched on the Team screen and stored in `businesses.settings.cashier_permissions`.

| Gate | Permission key | Default | Used by |
|---|---|---|---|
| `run-audit` | `run_audit` | on | `/audit` routes, sidebar link |
| `view-costs` | `view_costs` | off | Peso totals on the closing audit |
| `void-orders` | `void_orders` | off | Void directly, or only request a void |
| `log-expenses` | `log_expenses` | on | Quick expense form on My orders |
| `correct-audit` | — (owners only) | — | Re-submitting today's audit |

Defined in `App\Providers\AppServiceProvider::defineCashierGates()`; used as `can:run-audit` middleware, `Gate::allows()` in controllers and `@can` in views.

---

## Tests

`UiScreensTest` (each role's screens, redirects, no-business 403), `ClosingAuditTest` (audit permission, owner-only correction), `VoidOrderTest` (void vs request), `ExpenseTest` (cashier logging), `TenancyTest`.

## What's left

- Policies per model (`OrderPolicy`, `ItemPolicy`) if the rules grow beyond the current gates.
- More roles (e.g. a manager between owner and cashier) if shops ask for it.
