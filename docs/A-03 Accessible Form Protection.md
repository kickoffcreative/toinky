# A-03 Accessible Form Protection

Implemented November 12, 2026. Baseline/rollback: A-02 merge
`057643afdb6dd4d5a172afaaa19f96604b84dc71`, preserved on
`codex/rollback-before-a03-2026-11-12`. No database migration is needed.

## Behavior

Sign-in, registration and forgot-password POSTs require their normal CSRF token
plus a signed authentication-form token. The token is bound to the PHP session
and form action with HMAC-SHA-256. It expires after 30 minutes; submission before
two seconds is refused with an instruction to wait and submit again. A hidden
honeypot must remain empty. The secret signing key remains in the server session.

Forms need no JavaScript, sum, image transcription, Google challenge or outside
challenge network call. The honeypot is hidden from the accessibility tree and
keyboard focus. The token stays stable within the form lifetime so a premature
submission can be retried and generic forgot-password responses stay identical.
It is deliberately reusable during that lifetime: A-02 account/IP budgets remain
the main abuse control. A script that obtains a valid form and waits can still
submit within those budgets; this change does not claim to identify all bots.

The old arithmetic/image/Google helpers and Google verification request are
removed. The image endpoint returns 404. Admin Security explains the automatic
controls and offers no way to re-enable puzzle CAPTCHA or turn protection off.
Old CAPTCHA settings remain unused in the database for rollback; no saved secret
is displayed or read by this screen. A-12 handles remaining stored secrets.

## Verification

`tools/bot_protection_guard_test.php` checks 37 cases, including missing/tampered
tokens, action/session binding, timing, expiry, future timestamps, honeypots,
malformed input, and the real registration/forgot/image HTTP paths. It is wired
into both MariaDB CI jobs. Rejected scripted registrations create no account.
The test disables mail and never creates a real account or calls an AI service.

Local A-01 and A-02 guards also passed, including 59 secret-link/HTTP checks and
the throttle/concurrency suite. The forgot-password comparison now obtains the
actual signed form and allows its two-second minimum before submitting.

Chrome keyboard checks on the isolated local preview confirmed sign-in order
Email → Password → Sign In, and registration order Display Name → Email →
Password → Register. Neither the honeypot nor token appeared in the accessibility
tree or tab sequence. No real credentials were entered and no browser account
was created. This is not a full screen-reader or WCAG certification; A-18 retains
that broader verification. Live deployment remains separate from local checks.

## Rollback

Keep migrations 142 and 143. Restoring the baseline restores the former CAPTCHA
modes and removes the new form requirement, while preserving A-01/A-02 protections.
Prefer a forward fix if form handling needs adjustment. Tokens already open in a
browser before deployment are absent on old forms; refresh those pages once to
obtain the new hidden fields. Artifact status remains Auditor-owned.
