# Module 01 · Authentication

Laravel Breeze 2 (Blade), extended with business sign-up and required email verification.

| Feature | Status |
|---|---|
| [Sign-up](#feature-sign-up) | 🟡 Partial |
| [Login](#feature-login) | ✅ Built |
| [Email verification](#feature-email-verification) | ✅ Built (page not restyled) |
| [Password reset](#feature-password-reset) | ✅ Built (pages not restyled) |
| [Password confirmation](#feature-password-confirmation) | ✅ Built |
| [Profile & account deletion](#feature-profile--account-deletion) | 🟡 Partial |
| [Logout](#feature-logout) | ✅ Built |

---

## Feature: Sign-up

**Status:** 🟡 Partial · **Who:** guests (new business owners)

A business owner creates their account and their business in one step, then must verify their email.

### Routes

| Method | URI | Name | Middleware | Handler |
|---|---|---|---|---|
| GET | `/register` | `register` | guest | `RegisteredUserController@create` |
| POST | `/register` | | guest | `RegisteredUserController@store` |

### Files

- `app/Http/Controllers/Auth/RegisteredUserController.php`
- `app/Services/RegisterBusinessUserService.php`
- `app/Models/User.php`, `app/Models/Business.php`
- `resources/views/auth/register.blade.php`

### How it works

1. Validate:

   | Field | Rules |
   |---|---|
   | `name` | required, string, max:255 |
   | `email` | required, string, lowercase, email, max:255, unique users |
   | `password` | required, confirmed, `Password::defaults()` |
   | `business_name` | required, string, max:255 |
   | `business_type` | required, string, max:255 |

2. `RegisterBusinessUserService::register()` runs in a **DB transaction**:
   create the `User` (role = DB default `admin`), then `$user->business()->create([...])`.
3. `event(new Registered($user))` → the verification email is sent.
4. `Auth::login($user)` → redirect to `/dashboard` → the `verified` middleware sends them to `/verify-email`.

### Backend to build

- [ ] Restrict `business_type` to the known list: `Rule::in([...])` or an enum (`Café / coffee shop`, `Burger & fast food`, `Milk tea & drinks`, `Carinderia / eatery`, `Bakery`, `Other food business`).
- [ ] Move validation to a `RegisterBusinessRequest` Form Request.
- [ ] Set `role => 'admin'` explicitly in the service. Don't rely on the DB default, because the in-memory model returns `null` until it's refreshed.
- [ ] Start the trial here: `trial_ends_at = now()->addDays(14)` (see [Business › Trial](03-business-tenancy.md#feature-trial--plan-status)).
- [ ] Add return and parameter types to `register(array $data): User`, with an array-shape PHPDoc.
- [ ] Rate-limit `POST /register` (e.g. `throttle:5,1`).

### Done when

- Sign-up creates exactly one user (role admin) and one business, or neither.
- The user lands on the verify-email page and receives an email.

### Tests

- `tests/Feature/Auth/RegistrationTest.php`: ❌ **fails**. Its POST data is missing `business_name` and `business_type`. Add them, and assert that a `businesses` row exists for the user.
- Add: an invalid `business_type` is rejected; the transaction rolls back if creating the business fails.

---

## Feature: Login

**Status:** ✅ Built · **Who:** all roles

### Routes

| Method | URI | Name | Middleware | Handler |
|---|---|---|---|---|
| GET | `/login` | `login` | guest | `AuthenticatedSessionController@create` |
| POST | `/login` | | guest | `AuthenticatedSessionController@store` |

### Files

- `app/Http/Controllers/Auth/AuthenticatedSessionController.php`
- `app/Http/Requests/Auth/LoginRequest.php`
- `resources/views/auth/login.blade.php`

### How it works

1. `LoginRequest` validates the email and password, and is **rate-limited to 5 attempts per email + IP**. Then it throws a lockout with the wait time.
2. The session is regenerated to prevent session fixation.
3. Redirect to the intended URL, or to `/dashboard` → role home screen ([RBAC › Home redirect](02-access-control.md#feature-role-home-redirect)).

The login page lists the demo accounts only when `APP_ENV=local` (`@env('local')`).

### Backend to build

- [ ] Block login for suspended businesses (see [Business › Suspension](03-business-tenancy.md#feature-suspension)).

### Tests

`tests/Feature/Auth/AuthenticationTest.php`: ✅ passing.

---

## Feature: Email verification

**Status:** ✅ Built · **Who:** every logged-in user

### Routes

| Method | URI | Name | Middleware | Handler |
|---|---|---|---|---|
| GET | `/verify-email` | `verification.notice` | auth | `EmailVerificationPromptController` |
| GET | `/verify-email/{id}/{hash}` | `verification.verify` | auth, signed, throttle:6,1 | `VerifyEmailController` |
| POST | `/email/verification-notification` | `verification.send` | auth, throttle:6,1 | `EmailVerificationNotificationController@store` |

### Files

- `app/Models/User.php`: `implements MustVerifyEmail`
- `app/Http/Controllers/Auth/{EmailVerificationPromptController, VerifyEmailController, EmailVerificationNotificationController}.php`
- `resources/views/auth/verify-email.blade.php`
- `routes/web.php`: every app route group has the `verified` middleware

### How it works

```
Registered event ──► verification email (signed URL, expires in 60 min)
User clicks link ──► VerifyEmailController
                     ├─ already verified → /dashboard?verified=1
                     └─ markEmailAsVerified() + Verified event → /dashboard?verified=1
Unverified user opens any app page ──► `verified` middleware ──► /verify-email
```

- `{hash}` is `sha1(email)`, and the URL is signed, so it can't be forged or reused for another account.
- Resending is limited to 6 per minute.

### Configuration

| Env | Value |
|---|---|
| Local, no inbox | `MAIL_MAILER=log`: the link appears in `storage/logs/laravel.log` |
| Local inbox | `MAIL_MAILER=smtp` + Mailpit/Mailtrap host, port and credentials |
| Production | A real provider + `MAIL_FROM_ADDRESS`, `APP_NAME=iPOSa` |

Verify a user by hand (local only):

```bash
php artisan tinker --execute 'App\Models\User::where("email", "admin@gmail.com")->first()->markEmailAsVerified();'
```

### Backend to build

- [ ] Queue the verification email (`ShouldQueue` notification) so sign-up doesn't wait on SMTP.
- [ ] Customize the email (iPOSa branding, Filipino-friendly copy) with `VerifyEmail::toMailUsing()` in `AppServiceProvider`.
- [ ] Restyle `verify-email.blade.php` to match `login.blade.php`; it still uses Breeze's default gray and indigo.
- [ ] Staff invited by an owner should arrive already verified, because the invite link proves the email (see [Team › Invites](09-team-settings.md#feature-staff-invites)).

### Tests

`tests/Feature/Auth/EmailVerificationTest.php`: ✅ passing.

---

## Feature: Password reset

**Status:** ✅ Built · **Who:** guests

### Routes

| Method | URI | Name | Handler |
|---|---|---|---|
| GET | `/forgot-password` | `password.request` | `PasswordResetLinkController@create` |
| POST | `/forgot-password` | `password.email` | `PasswordResetLinkController@store` |
| GET | `/reset-password/{token}` | `password.reset` | `NewPasswordController@create` |
| POST | `/reset-password` | `password.store` | `NewPasswordController@store` |

Tokens are stored in `password_reset_tokens` and expire after 60 minutes (`config/auth.php`).

### Backend to build

- [ ] Restyle `forgot-password` and `reset-password` views to match login.
- [ ] Reuse this flow for staff invites: the owner creates a cashier, and the cashier gets a "set your password" link.

### Tests

`tests/Feature/Auth/PasswordResetTest.php`: ✅ passing.

---

## Feature: Password confirmation

**Status:** ✅ Built · **Who:** logged-in users

`GET/POST /confirm-password` (`password.confirm`) asks for the password again before a sensitive action. Protect a route with the `password.confirm` middleware.

### Backend to build

- [ ] Put `password.confirm` on: deleting the account, changing the plan, super admin suspend/impersonate, and exporting everything.

### Tests

`tests/Feature/Auth/PasswordConfirmationTest.php`: ✅ passing.

---

## Feature: Profile & account deletion

**Status:** 🟡 Partial · **Who:** logged-in, verified users

### Routes

| Method | URI | Name | Handler |
|---|---|---|---|
| GET | `/profile` | `profile.edit` | `ProfileController@edit` |
| PATCH | `/profile` | `profile.update` | `ProfileController@update` (`ProfileUpdateRequest`) |
| DELETE | `/profile` | `profile.destroy` | `ProfileController@destroy` |
| PUT | `/password` | `password.update` | `PasswordController@update` |

Changing the email clears `email_verified_at`, so the user must verify again.

### Known issue

`businesses.user_id` has no `cascadeOnDelete()`, so **deleting an owner who has a business fails** with a foreign-key error.

### Backend to build

- [ ] Decide what deleting an owner account means: block it and point to "close business", or soft-delete the business + staff and keep sales records for tax purposes.
- [ ] Staff shouldn't be able to delete their own account; only the owner removes staff.

### Tests

`tests/Feature/ProfileTest.php` and `Auth/PasswordUpdateTest.php`: ✅ passing (they use factory users without a business).

---

## Feature: Logout

**Status:** ✅ Built · `POST /logout` (`logout`): invalidates the session and regenerates the CSRF token. The sidebar logout button shows a spinner while it submits.
