# Module 11 · UI foundation

The shell, theme, feedback and components every other module is built from. Blade + Alpine + Tailwind, **no other frontend dependencies**.

| Feature | Status |
|---|---|
| [App shell & navigation](#app-shell--navigation) | ✅ Built |
| [Dark / light theme](#dark--light-theme) | ✅ Built |
| [Loading feedback](#loading-feedback) | ✅ Built (real requests) |
| [Components](#components) | ✅ Built |
| [Money & number rules](#money--number-rules) | ✅ Built |
| [Public landing page](#public-landing-page) | ✅ Built |

---

## App shell & navigation

**Files:** `resources/views/layouts/app.blade.php` (used as `<x-app-layout title="…">`), `layouts/guest.blade.php` (auth pages), `layouts/partials/head.blade.php`.

- The sidebar is built from the user's role, the plan and the cashier's permissions ([02 › Navigation](02-access-control.md#role-based-navigation)).
- The workspace name is the real shop (`business_name`), or "Platform console" for the operator, or "No shop linked".
- The connection pill is live: **Online**, *Syncing offline sales…*, or **Offline · register still works**, with the number still waiting to sync ([12](12-pwa-offline.md)).
- `<x-app-layout focus>` collapses the sidebar to icons — the register uses it.
- On mobile: a top bar with a slide-in drawer.

## Dark / light theme

- **Dark by default** (`<html class="dark">`). The toggle saves `theme=light` in `localStorage`, and an inline script in the head applies it *before paint*, so there's no flash on reload.
- Tailwind 3 with `darkMode: 'class'` (`tailwind.config.js`).
- Palette: `ink` = warm stone neutrals (a food business, not a bank), `brand` = saffron, `gain` = emerald, `loss` = rose. **Money direction only ever uses gain/loss**, never the brand color.
- Fonts: Inter (UI), Instrument Serif (display), JetBrains Mono (numbers).
- Store: `Alpine.store('theme')` in `resources/js/app.js`.

> **Alpine can't remove a class that's also written statically in the markup.** A `:class="open ? 'translate-y-0' : 'translate-y-full'"` next to a static `translate-y-full` leaves both on the element, and the static one wins — which is exactly how the register's order sheet went missing on phones and tablets. Sliding panels now bind a data attribute instead (`x-bind:data-open`) and let Tailwind's `data-[open=true]:` variant do the work: its attribute selector outranks the plain class, and the closed state still renders correctly before Alpine boots.

## Loading feedback

Every click answers back. Nothing in here is simulated any more; the timings are the real requests.

| Situation | What the user sees | How |
|---|---|---|
| Navigating to another page | Top progress bar; "Please wait a moment…" after 500 ms | `Alpine.store('loader')` + a document click listener, `<x-page-loader />` in every layout |
| Submitting a form | The button shows a spinner and its `data-loading-text`, and is disabled | Document `submit` listener (skipped for `@submit.prevent` forms) |
| Action buttons | Spinner → ✓ done → back to normal, **at a fixed width** so nothing jumps | `<x-busy-button>` + `Alpine.data('busyAction')` |
| Register tap | Tile ring, highlighted cart line, "Added …" toast, a short vibration | `posTerminal.confirmTap()` |
| Complete sale / Close the day | Locked button, "Processing sale, please wait…" / "Saving counts…", errors shown inline with **Try again** | `posTerminal.complete()`, `closingAudit.submit()` — both `fetch()` with the CSRF token |

Opt out on any link or form with `data-no-loader`.

## Components

| Component | Use |
|---|---|
| `<x-icon name="…">` | Inline SVG icons (the list lives in `components/icon.blade.php`) |
| `<x-busy-button loading-text done-text type>` | Action button with busy/done states |
| `<x-spinner>` · `<x-page-loader>` | Spinner · navigation progress |
| `<x-page-header title eyebrow description>` + an `actions` slot | The page title row |
| `<x-flash>` | Session status and error messages |
| `<x-theme-toggle>` | Sun/moon button |
| `<x-logo-mark>` · `<x-brand-mark>` | The app mark · the "iposa.me" wordmark |
| `<x-closing-receipt>` | The receipt illustration on the landing and auth pages |
| `<x-modal>`, `<x-dropdown>`, `<x-text-input>`, `<x-input-label>`, `<x-input-error>`, buttons | Breeze components, restyled |

CSS helpers (`resources/css/app.css`): `.num`, `.surface`, `.eyebrow`, `.btn-primary`, `.btn-ghost`, `.btn-quiet`, `.field`, `.field-label`, `.pill`, `.tab`, `.tab-active`, `.kbd`, `.table-head`.

> Component attributes arrive HTML-escaped. A value printed inside `<title>` must be decoded first, or an apostrophe shows up as `&#039;`.

## Money & number rules

Money and counts always use `.num` (monospace, tabular), right-aligned in tables, formatted `₱1,234.00` — `number_format($x, 2)` in Blade, `formatPeso()` in JS. Losses are shown in (brackets) and in `loss` red; gains in `gain` green.

## Public landing page

`resources/views/welcome.blade.php` (`/`, route `home`): hero with the closing receipt · a day in three screens · the three kinds of stock · the Excel story · pricing · FAQ · closing CTA. Reachable signed-out, and covered by `UiScreensTest`.

---

## Tests

`UiScreensTest` renders every screen for every role with real data, checks each role's redirects, the friendly empty states of a brand-new shop, the shop name in the sidebar, and that cost totals stay hidden from cashiers.

## What's left

- Landing page copy: prices and the support channel still need a final pass before launch.
- A component/pattern page for reference (nice to have, not needed to ship).
