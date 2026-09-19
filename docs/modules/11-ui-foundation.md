# Module 11 · UI foundation

Shared layout, theme, feedback and components used by every module.

| Feature | Status |
|---|---|
| [App shell & navigation](#feature-app-shell--navigation) | ✅ Built |
| [Dark / light theme](#feature-dark--light-theme) | ✅ Built |
| [Loading feedback](#feature-loading-feedback) | ✅ Built (fake timings until the backend exists) |
| [Components](#feature-components) | ✅ Built |
| [Demo-data pattern](#feature-demo-data-pattern) | ✅ Built |
| [Public landing page](#feature-public-landing-page) | ✅ Built |

---

## Feature: App shell & navigation

**Files:** `resources/views/layouts/app.blade.php` (used via `<x-app-layout title="…">`), `layouts/guest.blade.php` (auth pages), `layouts/partials/head.blade.php`.

- The sidebar comes from the user's role ([RBAC › Navigation](02-access-control.md#feature-role-based-navigation)).
- `<x-app-layout focus>` collapses the sidebar to icons (used by the register).
- On mobile: a top bar + a slide-in drawer.
- **Backend to build:** the workspace name ("Kape't Burger · Owner · Marikina branch") and the "Online · synced" status are hardcoded; read them from the user's business and the real sync state.

## Feature: Dark / light theme

- Dark by default (`<html class="dark">`). The toggle saves `theme=light` in `localStorage`, and an inline script in the head applies it before paint (no flash).
- Tailwind 3 `darkMode: 'class'`; colors in `tailwind.config.js`: `ink` (warm neutrals), `brand` (saffron), `gain` (emerald), `loss` (rose).
- Store: `Alpine.store('theme')` in `resources/js/app.js`.

## Feature: Loading feedback

Every click must visibly respond.

| Situation | What the user sees | How |
|---|---|---|
| Clicking a link to another page | Top progress bar; "Please wait a moment…" after 500 ms | `Alpine.store('loader')` + a document click listener; `<x-page-loader />` in every layout |
| Submitting a real form (login, sign-up, profile, logout) | The button shows a spinner + `data-loading-text` and is disabled | Document `submit` listener; skipped for `@submit.prevent` forms |
| Prototype action buttons (Save, Export, Invite…) | Spinner + loading text → ✓ done text → back to normal; the width never changes | `<x-busy-button>` + `Alpine.data('busyAction')` |
| Register tap | Tile ring, highlighted cart line, "Added …" toast, vibration | `posTerminal.confirmTap()` |
| Complete sale / Close the day | Locked button, "Processing sale…" / "Saving counts…" | `posTerminal.processing`, `closingAudit.saving` |

Opt out on a link or form: `data-no-loader`.

**Backend to build:** replace the `setTimeout` in `busyAction.run()`, `posTerminal.complete()` and `closingAudit.submit()` with real `fetch()` calls; keep the same states and add an error state (e.g. "Couldn't save. Try again.").

## Feature: Components

| Component | Use |
|---|---|
| `<x-icon name="…">` | Inline SVG icons (list in `components/icon.blade.php`) |
| `<x-busy-button loading-text done-text duration type>` | Action button with busy/done states |
| `<x-spinner>` | Loading spinner |
| `<x-page-loader>` | Navigation progress + "Please wait a moment…" |
| `<x-page-header title eyebrow description>` + `actions` slot | Page title row |
| `<x-theme-toggle>` | Sun/moon button |
| `<x-brand-mark>` | "iposa.me" wordmark (until a logo exists) |
| `<x-closing-receipt>` | The receipt illustration (landing, auth pages) |

CSS helpers (`resources/css/app.css`): `.num` (all money and counts), `.surface`, `.eyebrow`, `.btn-primary`, `.btn-ghost`, `.btn-quiet`, `.field`, `.field-label`, `.pill`, `.tab`, `.tab-active`, `.kbd`, `.table-head`.

**Rule:** money is always `.num`, right-aligned in tables, formatted `₱1,234.00` (`number_format($x, 2)` in Blade, `formatPeso()` in JS). Losses show in (brackets).

## Feature: Demo-data pattern

Every screen declares its demo data at the top:

```php
@php
    $menuItems = $menuItems ?? [ /* static demo rows */ ];
@endphp
```

To connect real data, a controller passes a variable **with the same name and shape**; the demo data is skipped, and the view doesn't change. Then replace the `Route::view(...)` line with the controller route (keep the route name).

> ⚠️ Blade parses an inline `@php(...)` wrong when a `@php … @endphp` block follows it later in the same file. Keep variable setup in the top block.

## Feature: Public landing page

**File:** `resources/views/welcome.blade.php` (`/`, `home`).
Sections: hero + closing receipt · a day in three screens · three kinds of stock · Excel · pricing · FAQ · final CTA.

**Before launch:** make the copy match what's built: offline selling, Excel export, Viber support, prices.
