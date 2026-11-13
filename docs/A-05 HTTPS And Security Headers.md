# A-05 HTTPS And Security Headers

## Implementation

The cPanel deployment template redirects HTTP to the canonical HTTPS origin
before application routing. It preserves cPanel's PHP handler and INI blocks
and keeps an exact private `.htaccess` backup before replacement.

PHP responses, including redirects and runtime errors, receive HSTS (one year,
including subdomains), nosniff, strict-origin-when-cross-origin referrer policy,
and camera/microphone/geolocation denial. Apache also supplies these headers for
static files and server errors. Ordinary pages prohibit framing, objects and
external base URLs, and permit form destinations only on this origin or Stripe
Checkout. Multiple Apache/PHP CSP headers intersect; Apache never overwrites the
per-response nonce policy.

Sessions use `__Host-toinky`: always Secure, HttpOnly, SameSite=Lax, path `/`,
without Domain. Strict session IDs and cookie-only transport are required.
Existing browser sessions must sign in again after this cookie-name change.
Authenticated responses use `Cache-Control: no-store`.

## Staged CSP Rollout

Default: the full nonce policy is **report-only**, visible in the browser console.
The framing/base/object/form baseline is enforced immediately. After deployment,
inspect home, sign-in, lobby, play, writer, admin and Page Builder in production.
Only after addressing violations, configure `GAME2_CSP_MODE=enforce` in the
hosting environment and repeat those checks. Any other value returns to
report-only mode. No unauthenticated endpoint can change the mode.

Authored script and style elements carry a fresh random nonce. Inline script
event attributes were replaced by delegated handlers. JavaScript `unsafe-eval`
and script `unsafe-inline` are not granted.

Deliberate compatibility exceptions:

- Dynamic theme/editor style attributes still require `style-src-attr
  'unsafe-inline'`. Style elements require a nonce.
- `builder-preview.php` permits same-origin framing only after admin access,
  POST and CSRF validation. GET, ordinary-player and invalid-CSRF requests fail
  before framing permission. The parent permits same-origin frames. All ordinary
  pages remain unframeable. The builder's initial hero and row previews use
  this route; Open Page continues to open the public page.
- The existing optional narrator imports trusted modules from jsDelivr and model
  assets from Hugging Face. `strict-dynamic`, `wasm-unsafe-eval`, blob workers and
  narrowly named connection hosts support that existing feature. This does not
  grant JavaScript eval. Narrator model downloads and speech remain production
  verification items; this work does not claim all assets are first-party.
- PixiJS's official matching 8.14.0 compatibility module replaces its generated
  function path. Its upstream package name is `unsafe-eval`, but the module is
  the upstream solution for running without that CSP permission.

All 34 existing font families are hosted locally: 69 WOFF2 assets, Latin and
Latin Extended subsets, with source hashes and 34 licenses. `tools/vendor_fonts.php`
is an explicit maintenance command, not a deployment or HTTP download hook.

## Verification And Limits

`security_headers_guard_test.php` requests every root PHP entry point in both
modes, and checks authenticated pages, cookie attributes, fixation refusal,
nonce freshness/matching, preview permissions, and font/Pixi integrity. It uses
only an isolated `_test` database, suppresses real email and rejects AI traffic.
The guard runs on MariaDB 11.4 and 10.6 in CI. JavaScript action and deployment
configuration checks also run in CI.

Local PHP lint, account-link, signed-form, font, Page Builder and Inky contract
checks passed. Chrome with enforced CSP verified sign-in, dashboard, public-home
preview, writer, Inky panel, lobby and active play; no console CSP errors were
observed on these paths. The default Inky character was checked; a full Lantern
renderer and narrator playback exercise are not claimed. An isolated Apache
test validated syntax, a poisoned-Host HTTP request redirecting to toinky.com,
ordinary-page DENY and dedicated-preview SAMEORIGIN rules.

Local browser fixtures are not player-review playthroughs. Production CSP
sign-off remains pending deployment and enforcement verification. This document
does not change the Auditor's finding status.

## Rollback

Pre-A-05 main: `948a72e1bb8dd0cfe016e83c39ad9709bc244e36`.
Rollback ref: `codex/rollback-before-a05-2026-11-12`.
There is no database migration in A-05. Reverting enforcement to report-only
does not remove the baseline protections. A full code rollback should restore
the known-good deployment, including its `.htaccess`, using the private exact
backup if hosting directives need recovery. Never replace server-owned AI or
database credentials with repository examples.
