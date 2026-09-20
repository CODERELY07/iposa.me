# Module 03 · Business & tenancy

One platform, many shops. **One business = one branch = one subscription.** Every row of shop data belongs to exactly one business.

| Feature | Status |
|---|---|
| [Business record](#business-record) | ✅ Built |
| [Staff membership](#staff-membership) | ✅ Built |
| [Data scoping](#data-scoping) | ✅ Built |
| [Access rules](#access-rules-suspended-unpaid-no-shop) | ✅ Built |
| [Trial & plans](#trial--plans) | ✅ Built |
| [Suspension](#suspension) | ✅ Built |

---

## Business record

`businesses` table:

| Column | Meaning |
|---|---|
| `user_id` | The owner who signed up |
| `business_name`, `business_type` | Shown on receipts, exports, the platform console |
| `plan` | `tindahan` or `negosyo` (see [Trial & plans](#trial--plans)) |
| `status` | `trial`, `active`, `past_due`, `suspended` (`App\Enums\BusinessStatus`) |
| `start_date`, `due_date` | Subscription period; `due_date` drives "days left" and overdue checks |
| `address`, `tin`, `receipt_footer` | Receipt details |
| `settings` (JSON) | Payment methods, audit reminder time, default low-stock level, cashier permissions |
| `last_order_number` | Per-shop order counter |
| `suspended_at`, `suspension_reason` | Set by the operator |

`App\Models\Business` provides `owner()`, `members()`, `items()`, `orders()`, `audits()`, `expenses()`, `subscriptionPayments()`, `search()`, `isOverdue()`, `requiresPayment()`, `daysUntilDue()`, `planDetails()`, `hasFeature()`, `staffSeatsLeft()`, `setting()`, `cashierCan()`, `enabledPaymentMethods()`, `lowStockThreshold()`.

Settings merge over `Business::DEFAULT_SETTINGS`: permissions merge key by key, lists (payment methods) replace the default outright.

## Staff membership

`users.business_id` links owner **and** cashiers to the shop; it's null only for the platform operator. `businesses.user_id` still records who owns it.

- Sign-up sets `business_id` on the owner.
- Invites set it on the cashier ([Team](09-team-settings.md#staff-invites)).
- `BusinessFactory` links the owner automatically, so tests match reality.

## Data scoping

`App\Models\Concerns\BelongsToBusiness` is used by `Item`, `Category`, `Order`, `Audit`, `Expense`, `Asset` and `StockMovement`. It:

- adds a global scope limiting every query to the logged-in user's `business_id`;
- fills `business_id` automatically on create;
- gives each model a `business()` relation.

The scope is skipped when nobody is logged in (console, queue, seeders) and for `super_admin`, who works across shops on purpose. Route model binding therefore **404s** for another shop's record.

This is the most important security rule in the app; `TenancyTest` guards it.

## Access rules (suspended, unpaid, no shop)

`App\Http\Middleware\EnsureBusinessAccess` (alias `business`) runs on every shop route:

| Situation | What happens |
|---|---|
| User has no business | 403 with "Your account is not linked to a business" |
| Business suspended | Logged out with a message on the login page |
| Payment required (past due, or trial/active with `due_date` passed) | Owner → redirected to Settings → billing. Cashier → 402 page "The register is paused". **Settings, billing and exports stay open** |

Exports are never blocked: "your data is yours" is a promise on the landing page.

## Trial & plans

- Sign-up: `status = trial`, `plan = negosyo`, `due_date = +14 days` (`RegisterBusinessUserService::TRIAL_DAYS`).
- Plans are rows in the `plans` table, managed by the operator ([Platform › Plans](10-platform.md#plans)): price, staff limit, and feature switches (`expenses`, `reports`, `recipes`). `businesses.plan` stores the plan's key and `businesses.plan_price` the price that shop agreed to.
- `App\Http\Middleware\EnsurePlanFeature` (alias `plan`) gates routes: `plan:expenses`, `plan:reports`. Owners are redirected to Settings with an upgrade note.
- `php artisan businesses:mark-overdue` (daily at 00:05) moves trials and subscriptions past their due date to `past_due`.
- Payment flow: [Team & settings › Plan & billing](09-team-settings.md#plan--billing) and [Platform › Payments](10-platform.md#payments).

## Suspension

The operator suspends a shop with a reason, confirming with their own password ([Platform › Tenant actions](10-platform.md#tenant-actions)). Everyone in that shop is logged out on their next click. Unsuspending returns the shop to `past_due` (if overdue), `active` (if they ever paid) or `trial`.

---

## Tests

`TenancyTest` (items, orders, expenses never cross shops; new rows get the right `business_id`; the operator sees everything), `SubscriptionAccessTest` (trial end, past due, suspended, overdue command, sign-up trial), `SettingsAndBillingTest`.

## What's left

- Multiple branches per business (deliberately out of scope for now).
- Transferring ownership to another user.
