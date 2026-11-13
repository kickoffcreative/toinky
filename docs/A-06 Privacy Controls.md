# A-06 Privacy Controls

November 13, 2026. Implementation and rollout record. This is not legal approval
or an Auditor status change. See [Data Inventory](DATA_INVENTORY.md),
[Privacy Requests](PRIVACY_REQUESTS.md) and [Breach Response](BREACH_RESPONSE.md).

## Implemented Behavior

- Migration 144 adds versioned acceptance, time-bounded address history,
  explicit erasure holds, a disabled shared anonymous identity, and AI actor
  attribution. There are no passwords or production records in this migration.
- Registration starts with a neutral date check. Under thirteen is refused
  before account fields; the date/exact age is not stored. Eligibility and the
  refusal cookie expire after thirty minutes. Current versions and an unticked
  checkbox are required. Signed-in users must reaccept changed versions before
  ordinary account routes; legal pages and privacy controls remain accessible.
- Profile exports stream safe account-owned datasets after password/CSRF checks.
  Authored content is included; credentials, unrelated players' private history,
  raw mixed-person AI context and internal review notes are excluded. A manual
  access process covers those omitted categories where disclosure is appropriate.
- Identity erasure is password-confirmed and transactional. Unknown identity
  columns block erasure pending schema review. Photo files are staged privately
  and restored if the transaction fails. Known foreign and non-foreign actor
  references and actor IDs inside valid snapshot/import JSON are detached.
- Shared fiction/media/game history remain under the owner-confirmed decision.
  Reviews, profile, credentials, memberships, friendships, consent/address
  history, invitations, delivery logs and account security events are removed.
  Financial amounts remain, with direct account/provider references and notes
  removed. `FREE_FIRST` entries become `FREE_FIRST_ERASED` so their generated
  uniqueness key cannot collide when multiple accounts share the anonymous actor.
  Other players' free-grant eligibility is preserved. This is a documented
  privacy exception to ordinary append-only ledger handling.
- New AI records include a verified active actor or an explicitly background
  attribution version. Erasure clears that actor's text and legacy unattributed
  text conservatively; another attributed user's text remains. Legacy global
  text removal can reduce historical troubleshooting detail and is intentional.
- Retention clears all AI text and simulation/character/decision/actor links
  older than ninety days, keeping aggregate usage metrics. Per-game historical
  AI detail is consequently unavailable after pruning.

## Activation And Deployment

**A deployment with no approval flag closes new registration.** Existing
accounts can continue without accepting unreviewed drafts. The policy/terms
pages display a draft banner and acceptance stays disabled until the server
environment explicitly sets `GAME2_PRIVACY_APPROVED=1`.

Before setting that flag, the operator and qualified adviser must review the
actual public policy/terms, provider identities/locations, corporate operator
details, age handling, consumer/refund terms, legal jurisdiction, retained shared
content, financial exceptions, backup process and the proposed **365-day**
disabled-account period. The owner supplied Toinky, accounts@toinky.com and age
thirteen; that does not establish corporate/legal status or counsel approval.
Verify that the privacy mailbox is monitored and designate an incident deputy.

Deploy only through the owner's normal cPanel flow, which copies
`config/privacy.php`, verifies the manifest and applies migration 144. Review
the public draft and staged account flows first. Set the approval flag using
the host's server environment mechanism only after approval; do not expose it
through a public query parameter. Changing either configured version triggers
renewed acceptance. Keep both versions tied to archived approved document text.

Registration requiring neutral age screening is informed by the [FTC COPPA
FAQ](https://www.ftc.gov/business-guidance/resources/complying-coppa-frequently-asked-questions),
but this implementation is not a determination of COPPA scope or compliance.

## Scheduled Retention

From the private application directory, first run:

```sh
php tools/privacy_retention.php
```

This is a dry run with category counts only. Review the proposed deletions and
backup/legal-hold needs. The explicit apply command is:

```sh
php tools/privacy_retention.php --apply
```

Configure a daily **server CLI** schedule after operational review. Do not add
a public web cron endpoint. No production schedule or apply operation has been
performed by this implementation work. Old delivery logs use ninety days;
expired/consumed tokens and invitations use thirty days; inactive unlocked
throttle records use thirty days. Disabled erasure also requires the approval
flag, a prior acceptance record, no active hold, and no Super Admin/system role.
Dry-run output does not itself authorize a production purge. Keep operational
proof of successful runs without copying raw personal records into the repo.

## Validation And Limits

`tools/privacy_guard_test.php` uses a mandatory `_test` database, temporary
application/profile/session files, disabled mail and no outbound AI. It verifies
age boundaries, consent versions, safe streaming exports, former-email ownership
windows, financial uniqueness, shared-game/story preservation, rollback/photo
restoration, unknown-schema refusal, AI minimization, retention dry runs and
apply, legal holds, CSRF, forged owner IDs and real HTTP consent/deletion flows.
The CI workflow runs it on MariaDB 11.4 and 10.6.

Local validation: **51 privacy checks** and the existing A-01 **59**, A-02
**695**, A-03 **37**, A-04 **153**, AI diagnostic **62**, A-05 **733** checks,
plus the deploy-manifest guard, passed November 13. These counts describe the
tested implementation at this point; later changes require corresponding reruns.

Local Chrome with enforced CSP verified age-first registration, stale-version
consent routing, access to profile/privacy controls before renewed acceptance,
and a successful display-name correction on a disposable account. No console
warnings/errors were observed. No live legal agreement was accepted and no
production user or setting was changed. The preview/account were removed.

Free text and images can still identify someone after actor links are detached.
Snapshot JSON that is invalid cannot be safely transformed automatically.
Legacy address bindings begin at migration time, not account creation: earlier
address ownership cannot be reconstructed safely. Older received-only records
need a proportionate manual privacy review rather than a cross-owner export or
purge based on an invented acquisition date.
Provider copies, infrastructure logs, backup restore suppression, request-log
retention and legal holds need operator procedures and counsel review. The
automated export is not a complete statutory-access response. Do not mark
A-06 legally or operationally closed until these dependencies are evidenced.

## Rollback

`codex/rollback-before-a06-2026-11-12` preserves main `01c5222`.
Rollback application code through the owner's deployment process. Keep additive
migration 144 and its records; do not drop consent/history tables to undo UI
behavior. Erasure and retention apply are irreversible data operations: code
rollback cannot resurrect erased identities. An authorized backup restoration
requires privacy-request suppression and credential/session review first.
