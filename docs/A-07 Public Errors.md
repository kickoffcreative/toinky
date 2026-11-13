# A-07 Public Errors

November 13, 2026. Implementation record; no production deployment or Auditor
status change is implied. A-06 base is main `f56c5bb`, with additive migration
144. This item introduces no migration.

## Error Boundary

`UserFacingException` identifies reviewed, deliberate validation text. Rate
limits and privacy validation inherit that type. Ordinary account, game,
authoring, media, Page Builder and email-template validation uses it where the
message is intended for the caller. The HTML views still escape messages;
JSON endpoints encode them rather than constructing raw JSON.

`public_error(Throwable)` returns that deliberate message. Unexpected failures
return only `Something went wrong. Reference: <id>`. The per-request reference
also appears in private structured diagnostics, with exception class/code,
source basename/line and a bounded call path without arguments. PDO diagnostics
include SQLSTATE and numeric driver code. Raw exception messages, SQL values,
request data, passwords, tokens, codes, mail bodies, provider response text and
trace arguments are deliberately excluded from this diagnostic record. Repeated
reporting of the same exception uses one cached reference.

Only `PublicError.php` accesses exception messages. Its separate internal
`exception_category` helper returns bounded retry/idempotency categories, so
AI JSON repair, connection retry decisions and lobby idempotency retain their
behavior without copying raw provider/database strings into responses or logs.
Deliberate repair validation can still explain the issue to the model through
the reviewed user-message path; unknown failures supply a reference instead.

All previous direct `getMessage()` uses in browser endpoints now use the helper,
including JSON responses and the root installer. Bootstrap turns display errors
off, enables private error logging, excludes exception arguments and installs a
last-resort exception handler for uncaught request failures. The installer uses
the same boundary without requiring an existing database configuration. Existing
`.htaccess` protection for `error_log` remains in place; actual host log routing,
permissions, retention and web-server/proxy logs still require A-19 evidence.
Application `error_log()` calls are consolidated in `Logger`.

This does not sanitize arbitrary strings that another subsystem deliberately
chooses to log. Future callers must use fixed event names and safe structured
metadata, not raw request bodies or concatenated secrets. Do not mark an
unexpected backend/provider exception as `UserFacingException` to expose it.

## Registration And Duplicate Addresses

Both valid new-address and existing-address submissions return the same HTTP
303 destination and generic completion notice. Neither automatically signs the
visitor in. New users follow email verification/sign-in; emailed game invitations
still create the permitted membership, which is available after sign-in. Pending
join-by-code behavior remains handled at sign-in.

An existing mailbox gets a private, fixed registration notice through the
account-security mail transport. It contains no submitted name, password,
reset/verification token or new authorization. Existing account/password data
is unchanged. Notices are limited to three per address per hour using a hashed
throttle key; suppression leaves the outward completion unchanged. Erasure
cleans that notice throttle when the address is not owned by another account.
Both cases perform password hashing; invitation validation precedes the
existence branch. A duplicate-key race is resolved through the same completion.

This is response uniformity, not a claim of constant-time networking or mailbox
delivery. Email delivery and backend timing can vary; the implementation does
not prove resistance to every timing side channel. Broader enumeration/email
change work remains A-09.

## Verification And Rollback

`tools/public_error_guard_test.php` requires an explicit `_test` database and
uses disposable application/session files and captured mail with no real
delivery or outbound AI. Tests force actual PDO failures in the profile HTML
and preference JSON paths, inspect private diagnostics for secret-bearing
canaries, force an uncaught database failure, retain deliberate validation,
check all application/public message-access sites, and compare new/duplicate
registration responses, notice throttling, password preservation and sign-in
state. CI runs it on both MariaDB 11.4 and 10.6.

Additional local checks cover privacy, account links, signed forms, AI endpoint
policy/diagnostics, security headers, authoring correction/stable-code behavior,
Inky preferences and Page Builder. Record final passing counts and CI head in
the handoff report. Production smoke checks must follow an owner deployment;
no production faults are injected by this work.

Local results: **106** public-error checks, **51** privacy checks, **59** account
link checks, **695** rate-limit checks, **37** signed-form checks, **155** AI
endpoint checks, **62** diagnostic checks and **733** security-header checks
passed. Correction v1/v2, stable-code, Inky guide/action/character/preference,
Page Builder custom-page/hero, Inky endpoint and deploy-manifest guards passed.
PHP lint passed across app/public. Errors occurring before bootstrap executes
also require production PHP `display_errors=Off`; A-19 must verify that host
setting and the private destination/retention of native PHP/server logs.

Rollback branch `codex/rollback-before-a07-2026-11-13` preserves `f56c5bb`.
Deploy the earlier application version through the normal owner-controlled
process if required. No schema reversal is needed. A-06's approval flag and
retention/erasure limits still apply to that rollback version. Existing privacy
erasures cannot be undone by reverting code.
