# A-10 Canonical Site Address

November 13, 2026. Implementation record; deployment/live verification and
Auditor sign-off are separate.

## Configuration

`config/app.php` defines the public base URL `https://www.toinky.com` and accepted
request hosts `www.toinky.com` and `toinky.com`. Both remain valid entry points;
generated absolute links consistently use the canonical address. The default
matches the observed signed-in production site. No database setting is added.

`GAME2_BASE_URL` can explicitly override the base URL. `GAME2_APP_CONFIG` can
select a server-owned configuration file; otherwise the checked-in file is used.
An explicit override must be valid: an invalid value never falls back to Host
or to a different valid configuration. The URL's hostname is accepted along with
the configured aliases. Hostnames are compared case-insensitively; trailing-dot
aliases must not be used. An explicit port must match the configured port.

Production URLs require HTTPS. Credentials, query/fragment components, control
characters, backslashes, invalid ports and ambiguous path traversal/escaping
are refused. An explicit environment URL may use HTTP only for `localhost`,
`127.0.0.1` or `[::1]`, for local development/tests. That local override accepts
only its loopback hostname, with valid local test ports, and removes production
aliases. Never set a loopback URL in production.

The deployment copies and verifies `config/app.php`; an edited deployed copy is
not persistent configuration. Use a server environment override or an external
file selected through `GAME2_APP_CONFIG` for installation-specific values.
Preserve that external file through deployments and backups. The current
deployment needs no new secret, key or database migration.

## Behavior

- `AppUrlService` is the shared source for `AuthService::baseUrl`, email template
  site/logo URLs, checkout success/cancel URLs and the SMTP greeting hostname.
  Checkout validates the URL before creating a pending purchase record.
- Bootstrap validates Host before opening a session or connecting to the
  database. Unknown/malformed hosts return HTTP 400 with a fixed body. Missing
  or invalid address configuration returns HTTP 503. Both are no-store and
  logged with a request ID, without echoing/storing the hostile Host value.
- Forwarded-Host and Forwarded-Proto do not select the site origin. Request Host
  is read only inside the guard. Existing HTTPS redirect uses a fixed origin.
- This PHP boundary covers application requests. Virtual-host routing and
  infrastructure/static-file behavior still require A-19 hosting evidence.
  This change does not introduce redirects based on untrusted headers.

## Validation And Rollback

`tools/site_address_guard_test.php` runs real HTTP host/config failure checks,
canonical email composition and a real CommerceService request using a local
Stripe stub. It tests environment overrides, invalid/missing URLs, aliases,
ports, spoofed forwarding, early rejection logs and absence of Host reads in
other application services. No payment, real mail or external request occurs.
Both MariaDB CI suites include it. Existing HTTP guards use explicit loopback
origins and retain their authentication, privacy, nonce and cookie assertions.

Rollback anchor: `codex/rollback-before-a10-2026-11-13` at
`2a852fd19563106a87021bcae2b5db0403af68ce`. Preserve the external security key and
any server-owned configuration. A code rollback restores the earlier Host-based
URL behavior and removes this request-host protection. There is no migration
to reverse. Auditor status and the Google handoff are not changed by this PR.
