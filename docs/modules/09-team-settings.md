# Module 09 · Team & settings

The owner's controls: who can use the register, what cashiers may do, the shop's details, and the subscription.

| Feature | Status |
|---|---|
| [Staff invites](#feature-staff-invites) | 🎨 UI only |
| [Team list & removal](#feature-team-list--removal) | 🎨 UI only |
| [Cashier permission toggles](#feature-cashier-permission-toggles) | 🎨 UI only |
| [Business profile](#feature-business-profile) | 🎨 UI only |
| [Register & closing settings](#feature-register--closing-settings) | 🎨 UI only |
| [Plan & billing (tenant side)](#feature-plan--billing) | 🎨 UI only |
| [Download all data](#feature-download-all-data) | 🎨 UI only |

**Who:** `admin` only.

### Routes (existing)

| URI | Name | View | Demo variables |
|---|---|---|---|
| `/admin/team` | `admin.team` | `admin/team` | `$members` |
| `/admin/settings` | `admin.settings` | `admin/settings` | `$plans` |

---

## Feature: Staff invites

**Status:** 🎨 UI only (Name + Email + **Send invite**)

### Backend to build

- [ ] `POST /admin/team` (`admin.team.store`), `InviteStaffRequest`: name required; email required, unique users.
- [ ] Create the user with `role = staff`, `business_id` = the owner's, a random password, and `email_verified_at = now()` (the invite link proves the email).
- [ ] Send a "Set your password" email using the password-reset broker (`Password::sendResetLink`), with custom invite wording.
- [ ] Enforce the plan's staff limit (Tindahan = 3) from `config/plans.php`; show "Upgrade to add more".
- [ ] Depends on: [Business › Staff membership](03-business-tenancy.md#feature-staff-membership).

### Done when

An invited cashier gets an email, sets a password, logs in and lands on `/pos` for the right shop.

---

## Feature: Team list & removal

**Status:** 🎨 UI only

### Backend to build

- [ ] List the business's users (owner first), with their last activity (`sessions.last_activity` or a `last_seen_at` column).
- [ ] Remove a cashier: soft-delete or deactivate (keep their name on past orders); force a logout by deleting their sessions.
- [ ] The owner can't remove themselves here.

---

## Feature: Cashier permission toggles

**Status:** 🎨 UI only (4 switches)

Run the closing audit · See cost prices and margins · Void a paid order · Log expenses.

### Backend to build

- [ ] Save to `businesses.settings.cashier_permissions`, and enforce them through Gates. Full spec: [RBAC › Cashier permissions](02-access-control.md#feature-cashier-permissions).

---

## Feature: Business profile

**Status:** 🎨 UI only (name, type, address, TIN, receipt footer)

### Backend to build

- [ ] `PATCH /admin/settings/business` → `UpdateBusinessRequest` → update the `businesses` columns ([Business record](03-business-tenancy.md#feature-business-record)).
- [ ] These values are used by receipts ([POS › Receipts](05-pos.md#feature-receipts)) and exports.

---

## Feature: Register & closing settings

**Status:** 🎨 UI only (payment methods, audit reminder time, default low-stock alert)

### Backend to build

- [ ] Save to `businesses.settings`: `payment_methods` (array), `audit_reminder_time` (HH:MM), `default_low_threshold`.
- [ ] The register only shows enabled payment methods.
- [ ] The audit reminder runs from the scheduler ([Closing audit](06-closing-audit.md#feature-daily-count)).

---

## Feature: Plan & billing

**Status:** 🎨 UI only (two plan cards, "Trial ends Sep 27")

### Backend to build

- [ ] Show the real `plan`, `status` and `trial_ends_at` ([Business › Trial](03-business-tenancy.md#feature-trial--plan-status)).
- [ ] **MVP billing is manual:** a "Pay with GCash / bank transfer" panel with instructions + reference upload; the super admin confirms it ([Platform › Payments](10-platform.md#feature-payments)).
- [ ] Switching plan: allowed if the staff count fits the new limit.
- [ ] A payment gateway (e.g. PayMongo: GCash, Maya, card) comes after the first paying customers.

---

## Feature: Download all data

**Status:** 🎨 UI only ("Download .xlsx")

### Backend to build

- [ ] Menu & prices, stock, audits, expenses and the daily ledger in one download. MVP: a zip of CSVs. Details in [Reports › Export](08-reports.md#feature-export).
- [ ] Protect it with `password.confirm`, and allow it for every business status, including expired trials.
