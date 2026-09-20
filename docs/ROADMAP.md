# iPOSa roadmap

**Where things stand:** the product is built and deployed. Sign-up → menu → sell → closing audit → true daily profit works end to end, on real data, offline-capable, with 170 passing tests. What follows is what's *not* built, in the order that gets to the first paying shop fastest.

Sizing assumes one developer: **S** = under a day, **M** = 1–3 days, **L** = 3–5 days.

---

## Phase A · Before the first real shop · S–M

Small, and each one is something a real owner will hit in week one.

- [ ] **Throttle sign-up and password reset** (`throttle:` on `POST /register` and `POST /forgot-password`). Everything else is already throttled — [01](modules/01-authentication.md#whats-left). · S
- [ ] **Landing page copy vs reality**: prices, the support channel, and the claims about offline and Excel. · S
- [ ] **Error tracking** (Sentry or similar). Today a 500 only reaches the host's logs. · S
- [ ] **Uptime check on `/up`.** · S
- [ ] **Paid host tier.** On the free tier the service sleeps after ~15 minutes and the first request takes about a minute — unusable mid-rush — and the free Postgres is deleted after 30 days. [13](modules/13-deployment.md) · S
- [ ] **Privacy policy and terms** (Philippine Data Privacy Act), plus a cancellation page. · M
- [ ] **Onboard 3–5 shops for free** and watch one full day each, register to closing audit. Everything below should be re-ordered by what they actually complain about.

## Phase B · The closing-audit reminder · M

The one feature that's half-built: `settings.audit_reminder_time` is saved and editable, but nothing sends anything ([06](modules/06-closing-audit.md#whats-left)).

- [ ] A scheduled command that finds shops past their reminder time with no audit for today.
- [ ] A channel that actually reaches a cashier — email is weak here; web push is the right answer and the service worker already exists.
- [ ] Queue worker (`QUEUE_CONNECTION=database`) so sending never blocks a request.

## Phase C · Getting paid without chasing people · M–L

Billing works, but by hand: the owner sends a GCash reference and the operator confirms it ([09](modules/09-team-settings.md#plan--billing)).

- [ ] **Payment gateway** (PayMongo for GCash/Maya/card), keeping the manual path as a fallback.
- [ ] Automatic renewal and receipts for the subscription itself.
- [ ] Announcements from the platform console ("your trial ends tomorrow") — today that's a manual message. [10](modules/10-platform.md#whats-left)
- [ ] Moving shops between plans in bulk (today the operator changes one shop at a time, or the owner switches).

## Phase D · What shops will ask for · M each

Ordered by how often a Philippine food business actually needs it.

- [ ] **Discounts** — senior/PWD is a legal requirement for many shops, not a nice-to-have. [05](modules/05-pos.md#whats-left)
- [ ] **Stock take for pieces**: count many items at once instead of one at a time. [04](modules/04-inventory.md#whats-left)
- [ ] **Recurring expenses** (rent on the 1st, entered for you). [07](modules/07-expenses.md#whats-left)
- [ ] **This month vs last month** on one screen, and day-of-week patterns. [08](modules/08-reports.md#whats-left)
- [ ] **Per-cashier PIN** for fast shift switching on a shared tablet. [09](modules/09-team-settings.md#whats-left)
- [ ] **Offline closing audits** — the register works offline, the audit doesn't yet. [12](modules/12-pwa-offline.md#whats-left)
- [ ] **Impersonation** ("view as owner") with an audit trail, for support. [10](modules/10-platform.md#whats-left)

## Phase E · Later, or only if asked

- Bluetooth / ESC-POS printing (browser printing works today).
- `.xlsx` import and export — CSV opens in Excel and needs no new package.
- Split payments and partial refunds.
- Customer records and loyalty.
- Supplier records and purchase orders.
- Break-even view using fixed vs variable expenses.
- Background Sync, so the offline queue flushes with the app closed.
- Multiple branches per business, and transferring ownership.
- More roles (a manager between owner and cashier).

---

## Decisions already locked

These shaped the schema and would need migrations on live data to change. They are settled — see [the decisions table](README.md#the-decisions-that-shaped-everything).

Money `decimal(12,2)` · stock `decimal(12,3)` · one business = one branch, no `branch_id` · order lines copy name, price and cost at sale time · checkout idempotent by client `uuid` · all stock changes through `StockService` · every report from `DailyLedger` · email assumed unreliable.
