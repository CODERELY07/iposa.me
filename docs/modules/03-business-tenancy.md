# Module 03 · Business & tenancy

One platform, many businesses. **One business = one branch = one subscription.** Every piece of shop data belongs to exactly one business.

| Feature | Status |
|---|---|
| [Business record](#feature-business-record) | 🟡 Partial |
| [Staff membership](#feature-staff-membership) | ⬜ Not started |
| [Data scoping](#feature-data-scoping) | ⬜ Not started |
| [Trial & plan status](#feature-trial--plan-status) | 🟡 Partial (status + dates stored; no plan, no expiry job) |
| [Suspension](#feature-suspension) | ⬜ Not started |

---

## Feature: Business record

**Status:** 🟡 Partial

### Files

- `app/Models/Business.php`: fillable name, type, owner, status, dates; `owner()` relation; `search()` scope; `isOverdue()`
- `app/Enums/BusinessStatus.php`
- `database/factories/BusinessFactory.php`, `database/seeders/BusinessSeeder.php`
- `database/migrations/2026_09_19_055057_create_businesses_table.php`
- `app/Models/User.php`: `business(): HasOne`
- `app/Http/Controllers/BusinessController.php`: super admin list and detail ([Platform](10-platform.md#feature-businesses-list))
- Created during sign-up by `RegisterBusinessUserService` ([Authentication › Sign-up](01-authentication.md#feature-sign-up))

### Table today

| Column | Type |
|---|---|
| `id` | bigint |
| `user_id` | FK → users (the owner), **no cascade** |
| `business_name` | string |
| `business_type` | string |
| `status` | enum `trial`, `active`, `past_due`, `suspended` (default `trial`), cast to `App\Enums\BusinessStatus` |
| `start_date` | timestamp, nullable: trial or subscription start |
| `due_date` | timestamp, nullable: trial end or next payment due |
| timestamps | |

### Backend to build

- [ ] Add columns: `address`, `tin` (nullable), `receipt_footer`, `settings` (JSON: payment methods, audit reminder time, cashier permissions, default low-stock threshold).
- [x] `Business::owner(): BelongsTo` (`user_id`).
- [ ] `Business::members(): HasMany` (users), after [Staff membership](#feature-staff-membership).
- [ ] `settings` cast to `array` (or `AsArrayObject`), with defaults filled in on create.
- [x] `BusinessFactory` for tests.
- [ ] Decide what happens to `user_id` when the owner is deleted: restrict, or transfer ownership.
- [ ] Owner-side editing (Settings screen) needs its own controller: [Team & settings › Business profile](09-team-settings.md#feature-business-profile).

---

## Feature: Staff membership

**Status:** ⬜ Not started

Right now only the owner is linked, through `businesses.user_id`. **Cashiers have no link to a shop.**

### Backend to build

- [ ] Migration: `users.business_id` → nullable FK to `businesses` (null only for `super_admin`).
- [ ] Sign-up sets `business_id` on the owner right after the business is created, in the same transaction.
- [ ] `User::business(): BelongsTo` (replaces the current `HasOne`), and `Business::members(): HasMany`.
- [ ] Keep `businesses.user_id`, renamed to `owner_id` if you like, to know who owns the business.
- [ ] Seeder: create "Kape't Burger" and attach `admin@gmail.com` and `staff@gmail.com` to it.

### Done when

`$staff->business` and `$owner->business` return the same business.

---

## Feature: Data scoping

**Status:** ⬜ Not started · **The most important security rule in the app**

Every tenant table (items, orders, audits, expenses, …) has `business_id`, and a user can only ever read or write their own.

### Backend to build

- [ ] Trait `App\Models\Concerns\BelongsToBusiness`:
  - global scope `where business_id = auth()->user()->business_id` (skipped for `super_admin` and in console commands)
  - `creating` hook that fills `business_id` automatically
  - `business(): BelongsTo`
- [ ] Use it on every tenant model.
- [ ] Route model binding then 404s automatically for another business's records.
- [ ] Middleware `EnsureUserHasBusiness` on the admin/staff groups: a user without a business gets an error page instead of broken queries.

### Tests (must have)

- Two businesses side by side: business A's admin gets a 404 for B's item, and never sees B's orders in lists or reports.
- Creating a model without setting `business_id` stores the current user's business.

---

## Feature: Trial & plan status

**Status:** 🟡 Partial

### Built

- `businesses.status` (enum `BusinessStatus`), `start_date`, `due_date`.
- Sign-up (`RegisterBusinessUserService`) sets `status = Trial`, `start_date = now()`, `due_date = now() + 14 days` (`TRIAL_DAYS`).
- The platform console lists and filters by status ([Platform › Businesses list](10-platform.md#feature-businesses-list)).
- `BusinessFactory` states: default (trial), `active()`, `pastDue()`, `suspended()`. `BusinessSeeder` gives `admin@gmail.com` "Kape't Burger" and adds 17 demo businesses.

### Backend to build

- [ ] A `plan` column (`tindahan` | `negosyo`); sign-up sets `plan = negosyo`.
- [ ] Plan limits in `config/plans.php` (price, staff limit, features), read in one place.
- [ ] A scheduled daily command moves expired trials to `past_due`.
- [ ] Middleware for past-due businesses: allow Settings (billing) and the data export; show a "trial ended" screen everywhere else. **Never block the export** (it's a promise on the landing page).

### Done when

The Today screen shows the real "Trial · N days left" pill, and an expired trial can still export.

---

## Feature: Suspension

**Status:** ⬜ Not started (the "Suspend" button on the platform screen is UI only)

### Backend to build

- [ ] `status = suspended` set by `super_admin` only (see [Platform › Tenant actions](10-platform.md#feature-tenant-actions)).
- [ ] Middleware: users of a suspended business are logged out, with a clear message and a contact link.
- [ ] Record who suspended the business, when and why (`suspended_at`, `suspension_reason`).
