# Module 01 · Authentication

Laravel Breeze (Blade), extended with business sign-up, required email verification, and a fallback for when email doesn't work.

| Feature | Status |
|---|---|
| [Sign-up](#sign-up) | ✅ Built |
| [Login](#login) | ✅ Built |
| [Email verification](#email-verification) | ✅ Built |
| [When email fails](#when-email-fails) | ✅ Built |
| [Password reset](#password-reset) | ✅ Built |
| [Password confirmation](#password-confirmation) | ✅ Built |
| [Profile & account deletion](#profile--account-deletion) | ✅ Built |
| [Logout](#logout) | ✅ Built |

## Routes

| Method | URI | Name | Middleware | Handler |
|---|---|---|---|---|
| GET/POST | `/register` | `register` | guest | `Auth\RegisteredUserController` |
| GET/POST | `/login` | `login` | guest | `Auth\AuthenticatedSessionController` |
| GET/POST | `/forgot-password` | `password.request`, `password.email` | guest | `Auth\PasswordResetLinkController` |
| GET/POST | `/reset-password` | `password.reset`, `password.store` | guest | `Auth\NewPasswordController` |
| GET | `/verify-email` | `verification.notice` | auth | `Auth\EmailVerificationPromptController` |
| GET | `/verify-email/{id}/{hash}` | `verification.verify` | auth, signed, throttle:6,1 | `Auth\VerifyEmailController` |
| POST | `/email/verification-notification` | `verification.send` | auth, throttle:6,1 | `Auth\EmailVerificationNotificationController` |
| POST | `/verify-email/request-agent` | `verification.request-agent` | auth, throttle:3,10 | `Auth\ManualVerificationRequestController` |
| GET/POST | `/confirm-password` | `password.confirm` | auth | `Auth\ConfirmablePasswordController` |
| PUT | `/password` | `password.update` | auth | `Auth\PasswordController` |
| GET/PATCH/DELETE | `/profile` | `profile.*` | auth, verified | `ProfileController` |
| POST | `/logout` | `logout` | auth | `Auth\AuthenticatedSessionController@destroy` |

---

## Sign-up

One form creates the owner account **and** their shop.

| Field | Rules |
|---|---|
| `name` | required, max 255 |
| `email` | required, lowercase, email, unique |
| `password` | required, confirmed, `Password::defaults()` |
| `business_name` | required, max 255 |
| `business_type` | required, one of `UpdateBusinessProfileRequest::BUSINESS_TYPES` (6 types) |

`RegisterBusinessUserService` runs in one transaction: create the user (role `admin`), create the business (plan `negosyo`, status `trial`, `start_date` now, `due_date` +14 days), then link `users.business_id`. If anything fails, nothing is saved.

Then the verification email is sent through `SafeMail` (see [When email fails](#when-email-fails)), the owner is logged in, and lands on `/dashboard` → verify-email.

**Files:** `Auth\RegisteredUserController`, `App\Services\RegisterBusinessUserService`, `resources/views/auth/register.blade.php`

## Login

`LoginRequest` rate-limits to **5 attempts per email + IP**, then locks out with a wait time. The session is regenerated on success. Users land on their own home screen ([RBAC](02-access-control.md#role-home-redirect)).

The login page lists the demo accounts only when `APP_ENV=local`.

## Email verification

`User` implements `MustVerifyEmail`, and every app route group uses the `verified` middleware.

```
sign-up ──► verification email (signed link, expires in 60 min)
click link ──► markEmailAsVerified() + Verified event ──► /dashboard?verified=1
unverified user opens any app page ──► /verify-email
```

The link is signed and contains `sha1(email)`, so it can't be forged or reused for another account. Resending is throttled to 6 per minute.

**Verify a user by hand (local):**

```bash
php artisan tinker --execute 'App\Models\User::where("email", "admin@gmail.com")->first()->markEmailAsVerified();'
```

## When email fails

Some hosts block SMTP, so **no email must ever break the app**.

- `App\Support\SafeMail::attempt()` wraps every send: the error is logged (`report()`) and the caller gets `false`.
- `MAIL_TIMEOUT` (default 10 s) stops a blocked SMTP port from hanging a page for a minute.

| Where | What happens when the send fails |
|---|---|
| Sign-up | Account and shop are saved anyway; owner goes to verify-email with "We couldn't send the email right now" |
| Resend verification | Same message instead of a crash |
| Password reset | "We couldn't send the reset email right now. Please contact an iPOSa agent at …" |
| Staff invite | The cashier is still added; the owner is told to use **Set password** ([Team](09-team-settings.md#staff-invites)) |

**The fallback:** the verify-email page offers **Ask an agent to verify me**, which stamps `users.verification_requested_at`, plus your contact details from `config/iposa.php` (`SUPPORT_EMAIL`, `SUPPORT_PHONE`, `SUPPORT_MESSENGER_URL`). The platform operator then verifies them with one button ([Platform › Verifications](10-platform.md#email-verifications)). After that, the owner taps **I've been verified, continue**.

**Files:** `App\Support\SafeMail`, `Auth\ManualVerificationRequestController`, `resources/views/auth/verify-email.blade.php`, `config/iposa.php`

## Password reset

Standard Breeze flow; tokens live in `password_reset_tokens` and expire after 60 minutes. Staff invites reuse this broker: the invite email carries a reset token so the cashier sets their own password ([Team](09-team-settings.md#staff-invites)).

## Password confirmation

`GET/POST /confirm-password` re-asks for the password. Protect any route with the `password.confirm` middleware.

Not used on a route today. Suspending a business asks for the operator's password inside the form instead, because `password.confirm` can't redirect back to a POST.

## Profile & account deletion

`/profile` edits name and email (changing the email clears `email_verified_at`), changes the password, or deletes the account.

**Owners cannot delete their account while they own a shop**: they're told to export their data in Settings and contact support. Otherwise the whole shop's data would go with them.

## Logout

Invalidates the session and regenerates the CSRF token. The button warns about unsynced offline sales and clears the cached register page ([PWA](12-pwa-offline.md#logout-on-a-shared-device)).

---

## Tests

`tests/Feature/Auth/*` (Breeze), `ProfileTest`, `ManualVerificationTest`, `SubscriptionAccessTest`:

- sign-up creates a linked 14-day trial shop
- sign-up still works when the verification email fails, and offers the agent fallback
- resend failure is reported, not fatal
- an unverified user can ask an agent once
- password-reset failure shows the contact message
- owners can't delete an account that owns a shop

## What's left

- Throttles cover login (5 attempts per email + IP), verification links and resends (6/min) and agent requests (3 per 10 min). **Sign-up and "forgot password" have none yet** — worth adding before launch.
- Two-factor authentication for the platform operator.
- Social or phone-number sign-in (owners ask for "login with Google" more than for email).
