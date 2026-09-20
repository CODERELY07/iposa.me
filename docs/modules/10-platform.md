# Module 10 · Platform console (super admin)

Your own screens as the operator of iPOSa: how the business is doing, which shops need a call, and the few actions only you can take. No `business` middleware here — the operator belongs to no shop and sees across all of them ([03 › Data scoping](03-business-tenancy.md#data-scoping)).

**Who:** `role:super_admin` only.

| Feature | Status |
|---|---|
| [Overview](#overview) | ✅ Built |
| [Businesses list](#businesses-list) | ✅ Built |
| [One business](#one-business) | ✅ Built |
| [Editing a business](#editing-a-business) | ✅ Built |
| [Tenant actions](#tenant-actions) | ✅ Built |
| [Plans](#plans) | ✅ Built (full CRUD) |
| [Payments](#payments) | ✅ Built |
| [Email verifications](#email-verifications) | ✅ Built |

## Routes

| Method | URI | Name |
|---|---|---|
| GET | `/super-admin` | `super_admin.dashboard` |
| GET | `/super-admin/businesses` | `super_admin.businesses.index` |
| GET | `/super-admin/businesses/{business}` | `super_admin.businesses.show` |
| GET/PUT | `/super-admin/businesses/{business}/edit` · `/{business}` | `super_admin.businesses.edit` · `.update` |
| POST | `/super-admin/businesses/{business}/extend-trial` | `…extend-trial` |
| POST | `/super-admin/businesses/{business}/suspend` · `/unsuspend` | `…suspend` · `…unsuspend` |
| GET | `/super-admin/plans` | `super_admin.plans` |
| GET/POST | `/super-admin/plans/new` · `/plans` | `super_admin.plans.create` · `.store` |
| GET/PUT | `/super-admin/plans/{plan}/edit` · `/plans/{plan}` | `super_admin.plans.edit` · `.update` |
| PATCH | `/super-admin/plans/{plan}/archive` · `/restore` | `super_admin.plans.archive` · `.restore` |
| DELETE | `/super-admin/plans/{plan}` | `super_admin.plans.destroy` |
| POST | `/super-admin/payments/{payment}/confirm` · `/reject` | `super_admin.payments.confirm` · `.reject` |
| GET | `/super-admin/verifications` | `super_admin.verifications` |
| POST | `/super-admin/users/{user}/verify` | `super_admin.users.verify` |

The operator account is created by `php artisan app:ensure-super-admin`, which runs on every deploy ([13 › Boot](13-deployment.md#what-happens-on-boot)).

---

## Overview

**Metrics:** MRR (the sum of what active shops actually pay, `monthlyPrice()`), paying, trials, past due, suspended, total, payments waiting for review, and verification requests waiting.

**Collected by month** — confirmed payments over the last 12 months.

**Trial funnel** — sign-ups in the last 30 days and how far each got: signed up → added menu → first sale → **first closing audit (activation)** → paid. The audit is the activation step because a shop that closes its day is a shop that has adopted the product.

**Activation → paid rate** counts only shops that actually activated, so the percentage can't exceed 100.

**Who needs a call:**
- *Gone quiet* — paying shops with no sale for 3+ days.
- *Trials ending* within 7 days, flagged by whether they activated. An activated trial is worth a call; an idle one is worth an email.

Plus a breakdown by business type (carinderia, milk tea, bakery, …).

## Businesses list

Every shop with its owner, plan, status and due date. Status tabs carry counts, an unknown status filter is ignored rather than erroring, and search matches the business name **or the owner's email** (`whereLike`, so it is case-insensitive on Postgres too). Empty states are written for a real empty console, not a blank table.

## One business

Health for the last 7 days (orders, sales, closing audits, cashiers used of the limit), the owner's details, the last five payments, and a **timeline built from real records**: signed up, first menu item, first sale, first closing audit (activated), cashiers added, payments submitted, latest sale, latest audit, suspension. Nothing here is a separate event log to keep in sync — it is derived, so it cannot drift.

## Editing a business

**Edit** on the shop's page opens one form with three sections.

| Section | Fields |
|---|---|
| The shop | Business name, type, address, TIN, receipt footer |
| Subscription | Plan, **the price this shop pays**, status, start date, due date |
| Owner | The owner's name and email |

Rules that keep the history honest:

- **Orders · 7d, Last sale and Signed up are never editable** — they're derived from real records, so a box for them would be a box for faking history.
- **Status can be set to trial, active or past due only.** Suspending keeps its own button, because it needs a reason and the operator's password, and a suspended shop can't be edited until it's unsuspended.
- **Changing the owner's email clears their verification** — the new address hasn't been proven. The operator can verify it in one click under [Verifications](#email-verifications). Renaming alone leaves verification intact.
- The price field is the shop's own `plan_price`, so lowering it is how a discount is given; moving a shop to another plan takes its feature gates with it immediately.

## Tenant actions

| Action | Rule |
|---|---|
| **Extend trial** | 1–60 days; reopens an expired trial |
| **Suspend** | Requires a reason **and the operator's own password**; everyone in that shop is logged out on their next click |
| **Unsuspend** | Back to `past_due` if the bill is still open, `active` if they ever paid, otherwise `trial` |

## Plans

Plans are rows in the `plans` table, created and edited here — price, staff limit, the modules they unlock and the bullet list owners see. `config/plans.php` only seeds the first two on install; nothing reads it at runtime.

| Column | Meaning |
|---|---|
| `key` | What `businesses.plan` stores. Made from the name, lowercase and dashed, and **never changes** |
| `name`, `price`, `pitch` | What owners and the landing page show |
| `staff_limit` | Cashier accounts allowed (null = unlimited) |
| `features` | `expenses`, `reports`, `recipes` — the switches `plan:` middleware and `hasFeature()` read |
| `feature_list` | The bullets on the pricing card, one per line |
| `sort`, `archived_at` | Order on the cards · hidden from new sign-ups |

**The price rule.** Each business stores the price it agreed to in `businesses.plan_price`, set at sign-up, when they switch plans, and again each time a payment is confirmed. So raising a price **never changes what a shop owes mid-period**: the operator is told how many shops keep the old price, and the owner's billing card says "From your next renewal: ₱X". `monthlyPrice()` is what they pay today; `priceAtRenewal()` is what changes.

| Action | Rule |
|---|---|
| Create | Key must be unique; it's generated from the name when left blank |
| Edit | Everything except the key |
| Archive | Hidden from sign-ups and plan switching; **shops already on it keep working**. Refused when it's the last available plan |
| Restore | Available again |
| Delete | Only when no shop is on it **and** no payment ever referenced it — otherwise archive |

MRR on the overview sums what shops actually pay, not the list price.

## Payments

Listed with **pending first**, filterable by status.

- **Confirm** extends the subscription by one period, sets the shop active, and re-locks their price to the plan's current one ([09 › Plan & billing](09-team-settings.md#plan--billing)).
- **Reject** records a note the owner sees on their billing card.

## Email verifications

The fallback for when SMTP is unavailable — which, on a free host, is often ([01 › When email fails](01-authentication.md#when-email-fails)).

Unverified accounts are listed with **people who asked an agent first**, newest request first, searchable by name or email, 25 per page. **Verify** marks the email as verified exactly as if the person had clicked the link (it fires Laravel's `Verified` event), and clears `verification_requested_at` so the queue empties.

The count of waiting requests also appears on the overview, so it isn't missed.

---

## Tests

`PlanManagementTest`: create a plan owners can switch to · unlimited staff · the key is generated and unique · the key never changes · a price rise leaves paying shops alone until renewal · the renewal applies the new price · archiving hides a plan but keeps its shops working · the last plan can't be archived · delete only when unused · the public pricing section follows the plans · validation · owners kept out.

`BusinessEditTest`: the edit screen · saving shop details and the subscription by hand · moving a shop between plans moves its gates · fixing an email un-verifies it and can be re-verified · a rename keeps verification · a taken email and a backwards due date are refused · suspended can't be set here and a suspended shop can't be edited · owners kept out · an archived plan stays selectable for the shop on it.

`SuperAdminBusinessesTest`: the list with owner and status · status filters · an unknown filter ignored · search by name and owner email · the empty state · one business with its dates · 404 for a missing business · non-operators kept out.

`ManualVerificationTest`: requests listed first and verified with one button · the verified user can open the app · shop owners kept out of the verification tools.

`UiScreensTest`, `SettingsAndBillingTest`, `SubscriptionAccessTest` cover the console screens, payment confirmation and suspension.

`EnsureSuperAdminCommandTest`: creates a verified operator · keeps the existing password on later deploys · refuses a short password · skips quietly when nothing is configured.

## What's left

- Messaging shops from the console (announcements, "your trial ends tomorrow").
- Impersonating an owner for support, with an audit trail.
