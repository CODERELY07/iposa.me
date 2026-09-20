# Module 09 · Team, settings & billing

The owner's three admin screens: who works here, how the shop behaves, and what they pay for.

**Who:** owners only (`role:admin`).

| Feature | Status |
|---|---|
| [Staff invites](#staff-invites) | ✅ Built |
| [Staff limit by plan](#staff-limit-by-plan) | ✅ Built |
| [Removing a cashier](#removing-a-cashier) | ✅ Built |
| [Cashier permissions](#cashier-permissions) | ✅ Built |
| [Business profile](#business-profile) | ✅ Built |
| [Register settings](#register-settings) | ✅ Built |
| [Plan & billing](#plan--billing) | ✅ Built |

## Routes

| Method | URI | Name |
|---|---|---|
| GET | `/admin/team` | `admin.team` |
| POST | `/admin/team` | `admin.team.store` |
| POST | `/admin/team/{user}/resend` | `admin.team.resend` |
| PATCH | `/admin/team/{user}/password` | `admin.team.password` |
| PATCH | `/admin/team/permissions` | `admin.team.permissions` |
| DELETE | `/admin/team/{user}` | `admin.team.destroy` |
| GET | `/admin/settings` | `admin.settings` |
| PATCH | `/admin/settings/business` · `/register` | `admin.settings.business` · `.register` |
| PATCH | `/admin/billing/plan` | `admin.billing.plan` |
| POST | `/admin/billing/payments` | `admin.billing.payments.store` |

---

## Staff invites

`App\Services\Team\TeamService::invite()` creates the cashier with `role = staff` and the owner's `business_id`, then emails a **set your password** link (`StaffInvitation`).

Because only the real mailbox can open that link, **an invited cashier starts verified** — no second verification step.

Two things make this work when email doesn't:

- The owner may type a password themselves; then no email is sent at all and the cashier can sign in immediately.
- If the invite email fails, the cashier is still created and the owner is told to hand over a password instead ([Authentication › When email fails](01-authentication.md#when-email-fails)). **Resend invite** tries again later.

Setting a password from the Team screen only ever works on a cashier of the owner's own shop.

## Staff limit by plan

Each plan row carries a `staff_limit` ([Platform › Plans](10-platform.md#plans)). `Business::staffSeatsLeft()` drives the counter on the Team screen, and the limit is enforced in the service, not just in the view.

## Removing a cashier

Deletes the user and ends their sessions immediately, but **past orders keep their name** (`orders.cashier_name`) and logged expenses keep `logged_by`. A cashier from another shop can never be removed — the tenant scope 404s first.

## Cashier permissions

Four switches, stored in `businesses.settings.cashier_permissions` and enforced by Gates:

| Switch | Default | Hint shown to the owner |
|---|---|---|
| Run the closing audit | on | Whoever closes, counts |
| See cost prices and margins | off | Off keeps your margins private |
| Void a paid order | off | Off sends a void request to you instead |
| Log a small expense | on | For ice, LPG, anything bought from the drawer |

Details in [Access control › Cashier permissions](02-access-control.md#cashier-permissions).

## Business profile

Shop name, business type, address, TIN (digits and dashes only) and a receipt footer of up to 120 characters. These are exactly the fields printed on a [receipt](05-pos.md#receipts).

## Register settings

| Setting | Default | Effect |
|---|---|---|
| `payment_methods` | cash, gcash, maya | Which buttons the cashier sees; at least one is required |
| `audit_reminder_time` | 21:30 | Saved; the reminder itself isn't sent yet ([06](06-closing-audit.md#whats-left)) |
| `default_low_threshold` | 10 | Used by items with no threshold of their own |

Settings merge over `Business::DEFAULT_SETTINGS`, so a new switch added later has a sensible value for existing shops without a migration.

## Plan & billing

Manual, Philippine-style billing — no card processor.

1. The owner picks a plan (only plans the operator has published). Downgrading is refused while more cashiers are employed than the smaller plan allows. Switching locks in that plan's price as it stands that day.
2. The owner pays by GCash or bank transfer and submits the **reference number**; a `subscription_payments` row is created as `pending`.
3. The operator confirms it in the console, which extends the subscription by one period — **from the current due date if they paid early, from today if they paid late** — and sets the business to `active`.
4. Or the operator rejects it with a note, which the owner sees on the billing card.

The card shows the price **this shop** agreed to, and, when the operator has since changed the plan's price, a line reading "From your next renewal: ₱X" ([Platform › Plans](10-platform.md#plans)).

The billing card always shows the plan, the status, the due date and days left; it stays reachable even when the shop is past due, so an owner can always pay their way back in ([03 › Access rules](03-business-tenancy.md#access-rules-suspended-unpaid-no-shop)).

---

## Tests

`TeamTest`: invite with a set-password email · the invited cashier sets a password and lands on the register · the staff limit on Tindahan · removing a cashier keeps their name on past orders · no cross-shop removal · saving permissions.

`SettingsAndBillingTest`: receipt details saved · payment methods on and off · at least one method required · plan switches only when the staff fit · a payment reference confirmed by the operator · a rejected payment with a note · owners can't delete their account while they own a shop.

`ManualVerificationTest`: a cashier is added even when the invite email fails · added with a password and no email · another shop's cashier can't be given a password.

## What's left

- Online payment (GCash API / card) instead of reference numbers.
- Invoices or official receipts for the subscription itself.
- A per-cashier PIN for faster shift switching on one device.
