# A-08 Session Security

November 13, 2026. Implementation draft from main `768e873` (A-07). Migration
145 is additive. No production deployment or Auditor sign-off is claimed here.

## Sign-In And Session Controls

Every administrator/Super Admin sign-in now requires a second factor, regardless
of the previous sign-in date. A configured authenticator is preferred; single-use
recovery codes or emailed codes are available. If the authenticator key/runtime
is unavailable, the sign-in still requires an emailed code. Email fallback is an
explicit work-order requirement; it is not equivalent to phishing-resistant MFA
and depends on the security of the mailbox. Ordinary players/writers require a
second factor when they enable an authenticator.

Session IDs regenerate when entering pending second-factor verification and
again after successful sign-in. Pending sign-in lasts ten minutes, is tied to
the account's session version and permits at most five failed code attempts,
in addition to the existing user/IP throttles. Password-only pending sessions
cannot use authenticated pages. The registry stores SHA-256 session-ID hashes,
not browser bearer values, plus owner, device description and security times.
Emailed factor records include a hashed issuance-address snapshot. Verification
checks the current address and account version under an account lock, then
marks that matching mailbox verified. A code from a previous address cannot
authenticate or verify a changed address. Legacy unbound codes require reissue.

`currentUser` rejects missing, revoked, expired or version-stale registry rows.
Admin/writer idle expiry is thirty minutes and maximum lifetime twelve hours;
players use twenty-four idle hours and thirty days maximum. Browser requests,
including polling, count as activity; they never extend the maximum lifetime.
View-as presentation does not change the role used for expiry or authorization.
Pre-migration sessions have no registry entry and must sign in again.

My Profile lists safe own session metadata, permits revoking a selected owned
session and provides Sign Out Everywhere. Revocation takes effect at the next
authenticated request; an operation already executing cannot be retroactively
undone. Password changes/resets, role changes, account status/consent withdrawal,
and authenticator changes revoke sessions. Sign-out and role changes clear
view-as. Registry checks supplement the existing account session version.

Sensitive email, account and admin commerce/mail/security/AI changes require
password confirmation within ten minutes. New sign-in supplies that proof;
My Profile renews it without storing the password. Existing password-change,
privacy download and identity-deletion forms still verify the supplied current
password directly. Forgotten passwords still use the protected mail/reset flow.
CLI maintenance commands are trusted server operations; they do not emulate
browser password prompts. Browser entry points enforce the recent-password gate.

## Authenticator And Recovery Data

Authenticator codes use SHA-1, six digits, thirty-second steps and a one-step
clock-skew window. Accepted steps cannot be reused, even across concurrent
requests. Setup requires a current code; ten random 128-bit recovery codes are
shown once and stored only as SHA-256 hashes. Recovery verification consumes
the matched code atomically. Replacing codes requires a current authenticator
code, invalidates the previous set and signs sessions out. Setup seeds are
never sent to an external QR renderer. Users enter the key manually in their
chosen authenticator app.

The calculation is verified against all six SHA-1 vectors in [RFC 6238](https://www.rfc-editor.org/rfc/rfc6238).
Secrets use [PHP Sodium authenticated secretbox encryption](https://www.php.net/manual/en/function.sodium-crypto-secretbox.php)
with a fresh random nonce per envelope. Session-regeneration/strict-cookie
handling follows the design principles in [PHP's session-security guidance](https://www.php.net/manual/en/features.session.security.management.php).
The exact timeout values come from the work order, not a universal PHP default.

Enrollment expires after fifteen minutes; it can be restarted. Setup confirmation
consumes the code, so users should wait for the next authenticator code when
signing back in, or use recovery/email. The recovery screen explains this.
Seeds, recovery hashes/codes and session hashes are excluded from account
exports and private exception diagnostics. Identity erasure deletes both new
tables' rows; retention prunes session rows thirty days after stored maximum
expiry or explicit revocation, and pending enrollment one day after expiry.

## Private Key And Deployment

The cPanel deployment applies migration 145 and then runs:

```sh
php /path/to/deployed-app/tools/create_security_key.php
```

The CLI-only initializer generates a 32-byte random key once, prints only a
status, uses a private directory and mode 0600, and never overwrites an existing
file. By default the key is in the deployment directory's parent:
`.toinky-private/<hash-of-application-path>.key`. It is outside the application
tree, so the deployment manifest and old-version rollback cleanup cannot delete
it. Existing invalid keys/permissions require private operator review rather
than automatic replacement. Nothing generates or writes keys from a web request.

Operators can instead supply base64 `GAME2_SECURITY_KEY` (32 decoded bytes),
or `GAME2_SECURITY_KEY_FILE` pointing to a private regular key file outside the
application directory. An invalid explicit environment key fails closed rather
than silently selecting another key. Sodium must be enabled for authenticator
verification; emailed codes remain mandatory/available for admins if it is not.
The runtime account must be able to read the private key. Confirm the directory
is not published by another virtual host, and protect it in restricted encrypted
backups separately from ordinary application downloads.

Do not rotate/remove the key or move the installation path without a reviewed
key/credential migration. Existing authenticator envelopes require their original
key. Preserve it with database backups and configure the original private path
when restoring into another directory. Losing it requires mailbox/recovery-based
access and safe authenticator replacement; code rollback does not decrypt data
with a different key. A-12 covers the broader secret-management/rotation program.

Privacy notice version becomes `2026-11-13-v1` for these data disclosures; terms
remain unchanged. The A-06 legal/operational review gate still applies. Review
the actual new notice before enabling registration/acceptance, including where
`GAME2_PRIVACY_APPROVED=1` was set previously. It is an operational flag, not
evidence of legal approval. No production retention schedule/apply is claimed.

## Verification And Rollback

The isolated guard covers role-specific idle/absolute expiry, missing registry,
revocation ownership, all-device sign-out, password/role revocation, recent-password
expiry, RFC vectors, authenticated encryption, key initialization/permissions,
OTP replay, one-use recovery, all three HTTP factor methods, enrollment/recovery
display, sensitive role gates and privacy erasure. Captured test mail prevents
real delivery; no production user, key or integration is exercised.

Local results: **69** session-security checks, **51** privacy checks, **59**
account-link checks, **695** rate-limit checks, **110** public-error checks,
**62** AI diagnostic checks, **733** security-header checks and the deployment
manifest guard passed. PHP lint passed for app/public and the new tools. Local
Chrome with enforced CSP verified ordinary sign-in, authenticator availability,
current-session metadata, and successful ten-minute password confirmation; no
console warnings/errors were observed. Temporary preview, account and key were
removed. Browser checks used a disposable player; administrator and all factor
methods were exercised by the isolated real-HTTP guard, not a production account.

Production follow-up after owner deployment must verify sign-in with every role,
admin email/authenticator/recovery methods, profile controls, private-key readiness,
Sodium availability, and normal writer/lobby/play flows. Never inject faults or
erase a real account merely to obtain evidence. A-19 must verify hosting/runtime
permissions and actual key/log/backup handling.

Rollback branch `codex/rollback-before-a08-2026-11-13` preserves `768e873`.
Keep migration 145 and the external key when rolling back code; do not drop
credentials or histories. Older code does not enforce A-08 protections, so any
rollback needs an explicit operational security assessment and restricted access
where appropriate. No rollback/deployment has been performed by this agent.
