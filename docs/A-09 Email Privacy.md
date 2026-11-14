# A-09 Email Changes And Account Privacy

November 13, 2026. Implementation and regression evidence; production verification
requires deployment. The Auditor owns the finding status and sign-off.

## Email Change

- Each request verifies the submitted current password. A prior ten-minute
  confirmation alone is insufficient. Invalid passwords are throttled before
  any address-availability decision.
- Available, already-used and own addresses have the same conditional public
  notice. Delivery failure does not change that notice. Internal services return
  delivery status for diagnostics/tests, never bearer tokens.
- The two-hour verification link binds the account's old and proposed addresses.
  Confirmation locks the account and token, checks availability, updates email
  history, invalidates pending account links/codes and revokes every session.
  Password resets also invalidate pending email-change requests.
- The old mailbox receives a random 192-bit, seven-day recovery link. Only its
  SHA-256 hash is stored. A clean confirmation page requires POST plus CSRF;
  opening a link does not restore the account. No sign-in is required to recover.
- Recovery is single-use and applies only to the latest completed email change
  while its new address is still current. It restores the previous address,
  revokes sessions, pending links and codes, removes authenticator/recovery
  credentials, and invalidates the password. The owner must use Forgot Password
  at the restored address. Disabled status is preserved. No recovery auto-login.
- If the old address was claimed by another account, recovery stops and directs
  the owner to support. Unique constraints and account/token locks also protect
  races. No account is reassigned from another owner.
- Mail uses the existing transport and private delivery log. A transport success
  is not proof of inbox receipt. Failed old-mailbox notices require support;
  there is no durable mail retry queue. Undelivered recovery tokens are rejected.
- Migration 146 adds recovery fields and expires older pending changes, which
  lack the old-address binding. No existing completed change gains a retroactive
  recovery link. Account erasure removes these rows. Existing thirty-day
  expired/verified-token cleanup outlasts the seven-day recovery window.

## Requests And Invitations

- Friend requests always say “Request sent if that player exists.” Unknown,
  self and already accepted/pending targets create no new relationship.
  Outgoing unaccepted target identity is hidden in the profile and export;
  recipients still see incoming requests and accepted friendships work normally.
- Account invitations create the same sender-owned invitation record for both
  cases. Existing mailbox owners receive a private notice directing them to
  sign in; their credentials and memberships are unchanged. New owners receive
  the ordinary registration invitation. The sender sees the same conditional
  notice regardless of existence or mail success. Delivery metadata uses the same
  invitation type for both paths; unconfirmed email-change delivery records are
  excluded from sender exports to avoid a secondary enumeration path.
- Game invitations already follow one service path without an account-existence
  branch. Registration and forgot-password uniformity remain covered by A-07
  and A-01. Deactivated-account sign-in notices follow successful password proof.
- These changes prevent differing public account-existence responses; they do
  not claim constant SMTP timing or identical randomized CSP nonce values.

## Validation

`php tools/email_privacy_guard_test.php` uses an explicitly configured `_test`
database, disposable accounts and captured mail. It tests real HTTP body/status
equality for email change, friend requests and account invitations after only
normalizing the per-response CSP nonce; password proof; friend/export privacy;
confirmation/session revocation; old-mailbox routing; hashed tokens; seven-day
expiry; CSRF and scanner-safe GET; password/authenticator revocation; password
recovery; replay; later-change supersession; and failed delivery.

Both MariaDB CI suites run this guard. Related A-01, A-06, A-07 and A-08 guards
must also pass. Browser review uses local disposable data; do not change a real
owner's email or authenticator for testing.

## Rollback

Pre-item reference: `codex/rollback-before-a09-after-utc-2026-11-13`, commit
`2b2957ac021407e411487e47008986e4eba87be0`. Preserve migration 146 and the external
security key during code rollback. Older code has no recovery endpoint; rolling
back therefore disables new recovery links and restores earlier email/privacy
behavior. Coordinate outstanding links with support before a rollback.

A-10 canonical URL enforcement remains a separate work order. The current URL
helper is unchanged here. Legal activation and A-19 production evidence remain
open. No production mailbox, credential or account identity was changed by tests.
