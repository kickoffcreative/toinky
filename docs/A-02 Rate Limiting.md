# A-02 Rate Limiting And Lockout

Implemented November 12, 2026. Baseline/rollback: A-01 merge
`d4bc6bbb847c67d8c2dce85e28920d0328f36a6b`, branch
`codex/rollback-before-a02-2026-11-12`. Requires migration 143 after 142.

## Limits

| Operation | Subject Budget | IP Budget | Window |
| --- | --- | --- | --- |
| Failed sign-in | 5 per normalized email | 20 | 15 minutes |
| Sign-in code | 5 attempts per account | 20 | 10 minutes |
| Initial code delivery/resend | 5 per account | 20 | 1 hour |
| Reset request | 3 per normalized email | 10 | 1 hour |
| Registration | Per-IP control | 5 | 1 hour |
| Site/game invitation, shared | 20 per sender; 5 per recipient across senders | 100 | 1 day |
| Invalid join code | 10 per account | 40 | 10 minutes |
| Friend request | 10 per account | 40 | 1 hour |
| Email-change request | 10 per account | 40 | 1 hour |
| Inky action/starter, shared | 30 per account | 120 | 10 minutes |
| Verification resend | 3 per account | 12 | 1 hour |
| Admin reset | 20 per actor | 80 | 1 hour |
| Reset submission | 5 per hashed token | 20 | 15 minutes |

The configured budget is allowed; reaching it starts the cooldown for later
attempts. Sign-in failure lockouts grow from 15 to 30, 60 and 120 minutes for
repeated lockouts within 24 hours. Rejected calls do not extend a running lock.
Other cooldowns equal their window. Sign-in budgets count password failures;
successful sign-ins do not add failures. Malformed sign-in codes count as guesses.

The fifth wrong code consumes the database code and clears pending sign-in state.
A correct sixth guess cannot authenticate, including from another pending browser.
A new sign-in and cooldown may be necessary. Code consumption uses a row lock;
failed counters commit before the validation error is returned.

## Storage And Integration

`RateLimitService::hit()` uses a unique SHA-256 action/subject key, atomic upsert,
and a short transaction to decide cooldown. No raw IP, email, bearer token or
user identifier is stored in the throttle table or lockout log. Both subject and
IP budgets are hit even when one is already exhausted. Lockout warnings include
the correlation ID through Logger. Old inactive throttle rows are included in
`tools/prune_expired_tokens.php --apply` after 30 days.

Services enforce account paths; the two Inky endpoints call the shared AI budget
after authorization and CSRF validation, before an AI call. Their throttle
responses are HTTP 429 with a fixed message. Forgot-password keeps the identical
generic response for unknown accounts and throttling. Unknown sign-in emails
still run password verification against a fixed dummy bcrypt hash.

Only `REMOTE_ADDR` supplies the IP. Forwarded headers are ignored. Production
proxy behavior is unverified; record trustworthy server configuration in A-19
before launch, rather than trusting caller-provided headers.

## Verification And Rollback

`tools/rate_limit_guard_test.php` is wired into both MariaDB CI jobs. It checks
each listed work-order limit and its exact configured window, repeated sign-in
cooldowns, five-wrong-code invalidation, correct-password lockout, invalid join
transactions, hashing/log privacy and 12 concurrent counter workers. The A-01
guard additionally compares real forgot-password responses across throttled and
unthrottled requests and repeats its secret-link response checks with captured
mail. No test calls a real mail or AI service.

Local limit/concurrency checks passed; full CI and live deployment verification
are separate. Artifact status remains Auditor-owned. Scheduling the pruning job
is part of A-17/A-19; adding its SQL here does not claim a production cron exists.

Rollback code to the baseline while retaining migrations 142/143 and their data.
Older code ignores the additive throttle table and code-attempt counter, which
removes throttling and code lockout: prefer a forward fix. A-01's session-version
and link protections remain in the baseline. Do not restore older token data.
