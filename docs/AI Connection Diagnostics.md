# AI Connection Diagnostics

The Super Admin AI Settings page now distinguishes endpoint authentication,
HTTP, DNS, connection, timeout, TLS, response-format and model-availability
failures. It reports whether a complete server Basic Authentication credential
pair is configured, never its values. HTTP 401 with configured credentials is
distinct from HTTP 401 without them. These results identify the failed check;
they do not prove which gateway or upstream configuration caused it.

Only fixed guidance and bounded numeric HTTP codes are rendered. Raw backend
bodies, cURL error strings, private credentials, usernames, tokens and untrusted
response text are not included in diagnostic messages. Private server logging
retains the existing troubleshooting detail. Player availability responses
remain generic and omit all diagnostic metadata.

The diagnostic service checks the actor's active Super Admin role as well as
the page-level access guard. Test Connection requires a valid CSRF token and
bypasses the ten-second availability cache. Ordinary availability checks retain
the cache. The destination allowlist, public-address validation, DNS pinning,
TLS verification and redirect refusal remain in force.

The model catalogue must contain a JSON `models` array; malformed catalogues
cannot be reported as ready. A ready status verifies that the configured model
is listed. It does not test inference or send story text.

## Verification

`tools/ai_diagnostics_guard_test.php` covers fixed classifications, unknown and
malicious backend fields, cached versus fresh results, service authorization,
real HTTP access for players/admins/Super Admins, CSRF and the generic player
API. It runs against isolated `_test` databases on MariaDB 11.4 and 10.6 in CI,
with an unapproved dummy endpoint so no outbound AI request is made.

Local checks: 62 diagnostic checks, the A-04 destination guard, the 733-check
A-05 guard and the Inky contract guard passed. Production AI still needs a new
Test Connection after deployment to identify the hosted failure category.
Unauthenticated external HTTP 401 alone does not demonstrate whether hosted
Toinky's credentials are present or accepted. Do not send passwords in chat.

## Rollback

No migration or private configuration change. Pre-follow-up main:
`aeb1ae84865c62e95fe2cffdb77ee04f4cb43269`.
Rollback branch: `codex/rollback-before-ai-diagnostics-2026-11-12`.
Deployment remains owner-controlled; this follow-up does not change Auditor
finding statuses or claim successful authenticated inference.
