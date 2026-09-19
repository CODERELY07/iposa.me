# Module 10 · Platform console (super admin)

For the SaaS operator: see every business, spot the ones about to churn, and manage plans and payments.

| Feature | Status |
|---|---|
| [Overview metrics](#feature-overview-metrics) | 🎨 UI only |
| [Businesses list](#feature-businesses-list) | ✅ Built (real data) |
| [Business detail](#feature-business-detail) | 🟡 Partial (real business + owner; no activity yet) |
| [Tenant actions](#feature-tenant-actions) | ⬜ Not started |
| [Plans](#feature-plans) | 🎨 UI only |
| [Payments](#feature-payments) | 🎨 UI only |

**Who:** `super_admin` only (`role:super_admin`). Queries here **skip the tenant scope** on purpose.

### Routes (existing)

| URI | Name | View | Demo variables |
|---|---|---|---|
| `/super-admin` | `super_admin.dashboard` | `super_admin/dashboard` | `$metrics`, `$mrrHistory`, `$funnel`, `$goneQuiet`, `$trialsEnding`, `$byType` |
| `/super-admin/businesses` | `super_admin.businesses.index` | `super_admin/businesses/index` | real: `BusinessController@index` |
| `/super-admin/businesses/{business}` | `super_admin.businesses.show` | `super_admin/businesses/show` | real: `BusinessController@show` |
| `/super-admin/plans` | `super_admin.plans` | `super_admin/plans` | `$plans`, `$payments` |

> Businesses use `Route::resource('businesses', BusinessController::class)->only(['index', 'show'])` with route model binding (`{business}`).

---

## Feature: Overview metrics

**Status:** 🎨 UI only

MRR (+ vs last month, 12-month line), paying / in trial / past due counts, the trial funnel, "Gone quiet", "Trials ending soon", businesses by type.

### Backend to build

- [ ] **MRR** = Σ plan price of businesses with `status = active`. **History:** a monthly snapshot table (`platform_snapshots`: month, mrr, paying, trials) filled by a scheduled command.
- [ ] **Trial funnel** (last 30 days' sign-ups): signed up → has a menu item → has an order → has an audit → paid. **"First closing audit" is the activation step.**
- [ ] **Gone quiet**: paying businesses with no order in 3+ days, with a reason (audit streak broken, card declined, few staff logins).
- [ ] **Trials ending**: `status = Trial` and `due_date` within 7 days, flagged as activated (has an audit) or not.
- [ ] **By type**: `COUNT GROUP BY business_type`.
- [ ] Remove or replace the demo line "72% of shops that finish a closing audit go on to pay" until it's a real number.

---

## Feature: Businesses list

**Status:** ✅ Built

### Files

- `app/Http/Controllers/BusinessController.php` → `index()`
- `app/Models/Business.php`: `owner()` relation, `search()` scope, `isOverdue()`, casts (`status` → `BusinessStatus`, dates)
- `app/Enums/BusinessStatus.php`: `Trial`, `Active`, `PastDue`, `Suspended` + `label()`
- `resources/views/super_admin/businesses/index.blade.php`

### How it works

- Columns: business (name · type), owner (name · email, "unverified" flag), status pill, started (`start_date`), due (`due_date`, red when past), signed up (`created_at`).
- **Status tabs** are links (`?status=trial|active|past_due|suspended`) with counts from one grouped query; an unknown status is ignored.
- **Search** `?q=` matches the business name, type, or the owner's name or email. Tab counts respect the search.
- Newest first, `paginate(25)->withQueryString()`, owner eager-loaded (no N+1 queries).
- Empty states: "No businesses yet" / "No businesses match these filters" + clear link.

### Backend to build

- [ ] Plan, orders in the last 7 days, last sale and MRR columns once [plans](#feature-plans) and [POS orders](05-pos.md#data-model-to-build) exist (`withCount` / `withMax`).
- [ ] `city` on businesses.

### Tests

`tests/Feature/SuperAdminBusinessesTest.php`: list, status filter, unknown status, search by owner email, empty state, access blocked for admin/staff.

---

## Feature: Business detail

**Status:** 🟡 Partial

`BusinessController@show(Business $business)` → `super_admin/businesses/show.blade.php`: status pill, a warning banner (due date passed, or owner email not verified), stat strip (status, started, due, days left/overdue), owner card (name, email, verification), record info (signed up, updated, ID). A missing ID returns a 404.

### Backend to build

- [ ] Health stats (orders, sales volume, closing audits, staff seats) once those modules save data.
- [ ] Timeline: an `activity_log` table (business_id, user_id, event, meta, created_at), written on key events: signed up, imported menu, first sale, first audit, invited staff, plan change, payment.

### Tests

`SuperAdminBusinessesTest`: detail page shows owner, status and overdue banner; 404 for unknown ID.

---

## Feature: Tenant actions

**Status:** ⬜ Not started (the fake buttons were removed from the real detail page)

### Backend to build

- [ ] **Extend trial**: `due_date += 7 days` while `status = Trial`; log it.
- [ ] **Suspend / unsuspend**: [Business › Suspension](03-business-tenancy.md#feature-suspension); requires `password.confirm` and a reason.
- [ ] **View as owner** (impersonation): after MVP. It must be logged, read-only if possible, and clearly marked in the UI.

---

## Feature: Plans

**Status:** 🎨 UI only (Tindahan ₱499 · Negosyo ₱999)

### Backend to build

- [ ] MVP: plans in `config/plans.php` (key, name, price, staff limit, features). Editing prices means a deploy, which is fine for now.
- [ ] Later: a `plans` table + the "New plan / Edit plan" UI.
- [ ] Subscriber count and MRR per plan from `businesses`.

---

## Feature: Payments

**Status:** 🎨 UI only (recent payments table)

### Backend to build

- [ ] MVP manual billing: a `payments` table (business_id, plan, amount, method GCash/Maya/Bank/Card, reference, proof image, status `Pending`/`Paid`/`Failed`, paid_at, confirmed_by).
- [ ] The super admin confirms a pending payment → business `status = active`, `paid_until = +1 month`.
- [ ] A scheduled command: `paid_until` passed → `past_due` → email the owner.
- [ ] Gateway integration (PayMongo webhooks) replaces the manual confirmation later.
