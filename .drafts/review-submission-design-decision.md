# Review submission gating — design decision

Input for `/magic.spec`, resolving the open design question F-11 left in
`qa-deep-findings.md` ("who may leave a review — anonymous with CAPTCHA, or
only after a contact click"). Decision made by simulating both variants
against how comparable directory and marketplace products actually gate
review creation, plus a third that was considered and rejected.

## The three variants simulated

**1. Open anonymous submission (name + rating + text), CAPTCHA-gated, always
entering the moderation queue.**
Precedent: Google Business Profile reviews, most WordPress/CMS review
plugins, TripAdvisor pre-verification. No purchase or stay record is checked
— there is none to check, since this portal explicitly keeps no booking or
occupancy record (§1, §76). Maximizes review volume, which is the actual
problem a near-empty catalog has at launch. Spam risk is real but is exactly
what CAPTCHA plus a moderation queue (already built for §44) exists to
absorb.

**2. Gated behind a prior contact-channel click for the same object** — a
visitor may only submit once they have clicked at least one `tel:`/WhatsApp/
Telegram/etc. link for that object in the current session.
Precedent search turned up no major platform that uses a *click* as its
review gate. The platforms that do gate on engagement (Booking.com, Airbnb)
gate on a **verified completed stay** — a transactional record this portal
deliberately does not keep. A click is a much weaker signal: it proves
interest, not a visit, and costs an abuser nothing to fake (click a phone
number, leave). It is real friction, though, and a real deterrent against
drive-by review-bombing a competitor, which anonymous-open does not resist at
all.

**3. Authenticated visitor accounts required** — rejected outright. Visitor
accounts are explicit *future* scope (§64 "личные кабинеты туристов"), not a
launch-time feature; building an entire auth surface to gate one form is a
scope explosion out of proportion to the problem.

## Decision

Build **both** variant 1 and variant 2 as selectable modes, switched by the
chief administrator from the back office — not because the trade-off is
unresolvable, but because it is a genuine judgment call about this specific
portal's spam risk at this specific point in its growth, one the client is
better positioned to make and revisit than a one-time developer default
baked into code. This also directly satisfies §63 ("Гибкость системы... все
параметры... должны изменяться через административную панель без участия
программиста"), which already names this exact class of decision.

This is not a novel mechanism for this codebase — it is the same shape of
decision `moderation.default_mode` and `presentation.within_tier_order`
already are: an enumerated string setting in `SettingsRegistry`, read at the
point of use, changeable without a deploy. Precedent:

```php
new SettingDefinition('moderation.default_mode', 'moderation', 'string', 'review'),
new SettingDefinition('presentation.within_tier_order', 'presentation', 'string', 'recent_bump'),
```

## Proposed shape (for `/magic.spec` to formalize)

- New setting `reviews.submission_mode` ∈ `{open, contact_gated}`, default
  `open` — the safer default for a catalog that needs review volume more than
  it needs to filter clicks, and the one that works with zero additional
  client configuration on day one.
- **`open` mode:** name, rating (1–5), body; CAPTCHA validated server-side
  using the `integrations.captcha_provider`/`_site_key`/`_secret` settings
  that already exist in the registry and are currently read by nothing (this
  closes that gap — see F-16). Rate-limited per IP alongside CAPTCHA, the
  same defence-in-depth the feedback form already gets.
- **`contact_gated` mode:** the form is only reachable/submittable once the
  current session has clicked at least one contact channel for that specific
  object. Mechanism: `ContactClickController` sets a session flag scoped to
  the object id at the point it already records the `contact_click` event
  (no new tracking table, no IP/device fingerprinting — matching §89's
  "не хранить лишнюю информацию о посетителях"); the review form checks that
  flag. No CAPTCHA requirement in this mode — the click gate is the friction.
- Both modes: a submitted review always enters `status = 'pending'`. This is
  a new writer, not a new moderation model — `Review::status`,
  `ReviewPolicy`, and the owner's existing `reply()`/`report()` surface are
  unchanged. The **admin side of F-11** — a `ReviewResource` in the back
  office (list, filter, publish/reject/hide with a reason, view reported
  reviews, block an author) — is a separate, required half of closing F-11
  and is unaffected by which submission mode is active.
- Setting lives on the existing Portal Settings page, in a `reviews` group
  positioned next to the `integrations` CAPTCHA settings it depends on.

## What `/magic.spec` should decide that this document does not

- Exact CAPTCHA provider integration point (server-side verification call
  shape) if not already specified elsewhere.
- Whether `contact_gated` session-flag TTL should expire (e.g. end of
  session vs. a fixed window) — leaning toward "end of session," simplest and
  consistent with not persisting extra visitor data.
- Whether the mode setting should be portal-wide only (as proposed) or
  eventually scoped like `moderation_settings` (per-country/-category) — TZ
  §39/§87/§120 describe no such scoping for reviews specifically, so
  portal-wide is the minimal correct scope unless the spec finds a reason
  otherwise.
