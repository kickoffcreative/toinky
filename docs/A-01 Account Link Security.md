# A-01 Account Link Security

Work Order: Reset And Verification Links. Implementation date: November 12, 2026.
Baseline and rollback branch: `5da44ba1c6b32003d408c3fe96db057fe04c7b61`,
`codex/rollback-before-a01-2026-11-12`.

## Result

- Admin, profile and lobby responses no longer display generated account links.
  The four requested service methods return only `['sent' => bool]`.
  Failed delivery displays “Email could not be sent. Try again later.” The
  public forgot-password response retains its generic account-existence response.
- Reset tokens expire after 30 minutes and remain SHA-256 hashes in storage.
  Consuming one atomically consumes all outstanding reset tokens and sign-in
  codes, changes the password and increments the account session version.
  Every authenticated request checks that version. Pending two-factor sign-ins
  carry the version verified by the password check; stale versions cannot sign in.
  Password rehash updates cannot overwrite a concurrent password reset.
- Ordinary administrators cannot reset another administrator or Super Admin.
  The service reads current roles, records allowed/denied requests, and separately
  attempts a target notification even when reset-mail delivery fails.
- Signed-in password changes require the current password and sign out every
  session. A password-changed email is attempted after the transaction commits.
- Invitation-based verification requires a successfully submitted email and an
  address-matched token. Ordinary registrations receive a separate, address-bound,
  single-use verification link. Hosting, invitations and friend requests require
  verified email, including for existing accounts. My Profile supports resending.
- Incoming emailed bearer tokens are held in the browser session and removed
  from the URL before rendering; reset/registration forms contain no secret token.

## Deployment And Rollback

Migration 142 is required. Use the normal cPanel deployment, including its final
CLI migration step; keep the application unavailable during the copy/migration
interval. No production database or real account was modified during development.

All existing PHP sessions must sign in again because they lack a session version.
Pending invitations, password resets and email-change links issued before this
migration expire and must be resent. Existing verified accounts are preserved.
Mail acceptance means the configured transport reported success; it cannot prove
inbox arrival. Only possession of the emailed token verifies the mailbox.

The additive migration should remain in place during code rollback. Previously
exposed links are explicitly expired so old code cannot revive them. Restoring
the baseline AuthService would remove session-version enforcement and link
protections: do not use that as a security rollback. Prefer a forward fix or keep
these auth services while reverting unrelated UI. A full emergency baseline
restore requires clearing all server PHP session files and accepting the old
link-exposure behavior; do not restore pre-migration token data.

## Verification And Boundaries

`tools/secret_link_exposure_guard_test.php` runs 57 service and real local HTTP
checks with an isolated capture-only mail transport, including forced failures,
privileged-reset audit records, invitation proof, mailbox binding, current-password
changes, token reuse and an existing-session reset check. It is wired into both
MariaDB CI jobs. The test requires a database name ending in `_test`; it sends no
real mail. Local affected commerce, pinning, multiplayer, atomicity, solo-play,
view-as, player-ready, email-template and deployment guards also passed.

The per-account version provides immediate global revocation without depending
on A-08's future session registry. The minimal account security audit is not the
full A-13 audit program or proof of append-only production database privileges.
Rate limits, TOTP, generic public errors, canonical URLs and mail/server evidence
remain their separate ordered work items. The Auditor owns artifact status;
this document does not mark the shared audit closed. Live deployment verification
and production mail delivery remain pending.
