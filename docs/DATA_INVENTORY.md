# Toinky Personal Data Inventory

November 13, 2026. **A-06 implementation draft, not a published privacy policy or
legal sign-off.** Baseline: main `01c5222`; schema and foreign-key metadata from
an isolated database built through migration 143, plus the referenced services.
No production personal records or private credentials were read for this file.
Production hosting, backups, mail routing and cron schedules are not verified.

## Owner-Confirmed Parameters

- Operator name supplied by the owner: **Toinky**.
- Privacy contact supplied by the owner: **accounts@toinky.com**.
- Minimum player age supplied by the owner: **13**.
- These decisions do not establish corporate registration, jurisdiction,
  counsel approval, or a completed privacy assessment.

## Current Storage And Proposed Handling

A-12 overlay (November 14; deployment verification pending): known provider
secret fields move out of platform_settings into an owner-only external file
or environment. The AI configuration's values move outside the web root behind
a loader. Admins see configured status and cannot edit secrets through web
forms. Private versions preserve migration recovery; rotate provider credentials
and restrict/expire obsolete copies through owner operations. Encrypted backup
tools use an independent key and require a verified private off-server copy;
no live backup/rotation/restore is claimed. Database configuration and the A-08
key have distinct recovery requirements described in RECOVERY.md.

A-09 overlay (migration 146; deployment verification pending): email-change
records also bind the old address and hold a hashed recovery token, seven-day
expiry, delivery time and consumption time. No plaintext token is stored or
exported. Existing expired/verified-token cleanup after thirty days preserves
the recovery window; account erasure deletes these rows. Unaccepted outgoing
friend identities and pending email-change delivery records are excluded from
self-service exports. Support can review necessary records after identity proof.

A-08 draft overlay (migration 145; not yet a deployment claim):

- `user_two_factor_codes.email_hash` binds emailed sign-in proof to the address
  at issuance. It has the same restricted verification access and thirty-day
  expired/used-code cleanup as its parent record, and is deleted on identity
  erasure. Authentication-code and mailbox hashes are excluded from exports.

- `user_sessions`: owner ID, hashed bearer ID, browser user-agent description,
  created/last-seen/absolute-expiry/revocation times, factor-proof and recent
  password-confirmation times. Purpose: revocable authenticated access and
  security self-service. The owner sees safe device/activity metadata; bearer
  hashes are never returned. Private operations use the table; no external
  recipient is added. Export includes safe own activity fields. Identity erasure
  deletes the rows; CLI retention removes records thirty days after stored
  maximum expiry or explicit revocation. Idle-expired records can remain until
  that maximum/revocation retention boundary if no later request records expiry.
- `user_totp_credentials`: owner ID, authenticated-encrypted secret, activation
  or pending-enrollment expiry, replay-prevention step and hashed random recovery
  codes. Purpose: authenticator/recovery verification. Secrets/hashes are excluded
  from exports and logs; plaintext setup/recovery values are displayed only in
  the authenticated setup response. Delete on disable or identity erasure;
  abandoned enrollment is pruned one day after its fifteen-minute expiry.
  No external QR/image service receives the seed. The user's selected
  authenticator app receives the key only when the user adds it there.
- The server encryption key is a non-personal operational secret, outside the
  database and application/deployment directory, with an environment override.
  It must be protected with restricted backup/recovery procedures. It is never
  included in account exports, Git or normal CLI output. Runtime access is
  required for verification; encryption does not protect against compromise of
  the application server itself. See the A-08 rollout record before activation.

The table below records the pre-implementation baseline through migration 143.
The following implementation overlay takes precedence for the draft A-06
branch; it does not assert that these controls are deployed or scheduled:

- Migration 144 adds `users.terms_version`, `privacy_version`,
  `policy_accepted_at_utc`, `privacy_erasure_hold_until_utc`,
  `privacy_erasure_hold_note` and `system_identity_key`; purposes are versioned
  consent, reviewed erasure exceptions and a shared disabled anonymous actor.
  Account/private operations access these fields; holds need a documented reason
  and review date. They are removed with the account except the system actor.
- `user_policy_acceptances` stores owner ID, policy versions and acceptance
  time. `user_email_history` stores owner ID, email and start/end ownership times.
  These support renewed consent, correct scoped access/erasure and email changes.
  No external recipient is added; authenticated exports contain only the owner's
  rows. Both histories are removed with account identity. Legacy address history
  before migration 144 cannot be reconstructed from the current address alone;
  seeded legacy bindings begin at migration time, never an assumed creation date.
- `ai_reasoner_interactions.actor_user_id` and `actor_attribution_version` track
  verified active actors/background/legacy attribution. They are internal; own
  exports include safe activity metadata. Own/legacy text is cleared during
  erasure, while ninety-day retention removes all text and identity/game links.
- The owner chose retained shared fiction/media/game history with account
  identity removed and a privacy-request process for personal details inside
  prose/images. Direct actors in snapshots are scrubbed; free text is not
  automatically treated as anonymous. This is a documented owner decision that
  requires Auditor/legal review against the original erasure requirements.
- Password-confirmed JSON export and identity deletion, correction/withdrawal,
  age-first registration and versioned acceptance are implemented on the draft
  branch. Proposed disabled erasure is **365 days**, requires prior policy
  acceptance and excludes active holds, Super Admin and the system actor.
  This period still requires legal/operational approval.
- The CLI privacy job supplies ninety-day email/AI cleanup and thirty-day
  expired/used token/invitation cleanup. No production schedule has been set
  or apply run claimed. Policy/terms acceptance and new registration remain
  unavailable until the explicit server approval flag is set after review.

See [A-06 Privacy Controls](A-06%20Privacy%20Controls.md) for exact behavior,
validation, deployment gate, financial-ledger exception and remaining limits.

The tables below identify personal, pseudonymous and user-authored data. Row
IDs and timestamps inherit the same purpose, access and retention as their
record. Fictional character/state data becomes account-linked through game
membership or authorship; it is not safe to classify all story text as
non-personal because a writer can include real people or personal material.

"No expiry found" means no corresponding automatic purge was found in code;
it does not establish what an operator may have configured outside the repo.
Listed access is the application function using the record, not an independent
verification of production privileges. A-13 covers broader role hardening.

| Records And Personal Fields | Purpose And Access | Current Retention | A-06 Export / Erasure Requirements |
| --- | --- | --- | --- |
| `users`: `user_id`, `email`, `password_hash`, `display_name`, `profile_image_path`, role flags, status, verification/login timestamps, `session_version` | Identity, sign-in, profile and account administration. Account owner; privileged account management. Display name and profile image can be visible to other users. | Deactivation disables sign-in but retains the row; no automatic account erasure found. | Export safe profile fields, never password hash. Erase identifiers/photo and credentials; revoke sessions. Resolve dependent references before deleting the identity row. |
| `password_reset_tokens`: `user_id`, `token_hash`, expiry/use/delivery timestamps | Mailbox-based reset; authentication services. | Reset lifetime 30 minutes. Existing cleanup removes expired or used rows when manually applied. | Never export usable bearer material or hashes. Erase all account tokens immediately on deletion; document scheduled cleanup. |
| `user_email_verification_tokens`: `user_id`, `email_hash`, `token_hash`, expiry/delivery/use timestamps | Prove mailbox control. | Expiry checked on use; existing token cleanup does not cover this table. | Erase at deletion; add to retention job. Export only safe lifecycle information if needed. |
| `user_email_change_tokens`: `user_id`, `new_email`, `token_hash`, expiry/verification/delivery timestamps | Verify a requested new address. | Expiry checked on use; existing cleanup does not cover this table. | Erase at deletion; never export bearer hashes. |
| `user_two_factor_codes`: `user_id`, `code_hash`, expiry/use timestamps, `failed_attempts` | Privileged sign-in challenge. | Ten-minute validity, five-attempt consumption; existing cleanup does not cover this table. | Erase at deletion and add expired-code retention. Never export code hashes. |
| `user_invitations`: recipient `email`, `token_hash`, `invited_by_user_id`, expiry/acceptance/delivery timestamps | Account invitations sent by an authorized inviter. Mail recipient and inviter; account administrators. | Existing cleanup removes expired or accepted invitations when applied. | Export safe invitation metadata. Erase invitations involving the deleted recipient or inviter, including unaccepted email-only records. |
| `game_invitations`: `game_id`, `email`, `token_hash`, `invited_by_user_id`, `accepted_by_user_id`, lifecycle timestamps | Invite a recipient to a hosted game. Host/authorized services and recipient. | Same existing invitation cleanup behavior. | Export safe invitation metadata; erase recipient/inviter/accepter links and email-only invitations. Do not reveal other recipients' information. |
| `user_friendships`: `user_low_id`, `user_high_id`, `requested_by_user_id`, status/timestamps; `user_friend_labels`: `user_id`, `label_text` | Friend requests and a user's private friend labels. Relevant users; label owner. | No expiry found. | Export the owner's relationships and own labels; erase relationships and private labels without exposing the other user's private labels. |
| `user_inky_preferences`: `user_id`, `companion_name_root`, character/size/dock/anchor choices, sounds, animation, proactive-help settings | Personalize the assistant. Account owner and application. | No expiry found. | Export preferences; erase the row at deletion. |
| `user_platform_roles`: `user_id`, `assigned_by_user_id`, role/timestamp | Application authorization and role provenance. Privileged role administration. | No expiry found. | Export account roles; erase assignments and anonymize assigner links. |
| `writer_access_requests`: `user_id`, `request_text`, `reviewed_by_user_id`, `reviewer_notes`, status/timestamps | Writer application and review. Applicant and authorized reviewers; internal notes need separate access assessment. | No expiry found. | Export applicant-supplied data and decision; avoid automatically exposing internal/third-party notes. Erase account-linked text and links at deletion. |
| `games`: `host_user_id`, `join_code`, creation/start/completion timestamps, `deleted_by_user_id`, `deactivated_by_user_id`, `idempotency_key` | Host a shared story and operate its lifecycle. Host, participants and authorized administration. | Soft deletion/archive retains rows; no final purge found. | Export owned/participated games safely. Remove host/operator attribution without destroying other players' game history; schema changes are required. Never disclose private invite capabilities in a public export. |
| `game_players`: `user_id`, `game_id`, `character_id`, join/last-seen/archive timestamps | Membership and a person's character/decision history. Relevant participant and authorized services. | No expiry found; user deletion currently cascades membership. | Export the owner's membership and their own character/decision history. Erase membership links; avoid revealing other players' private knowledge or choices. |
| `story_ratings`: `user_id`, `game_id`, rating, `review_text`, timestamps | Feedback and story quality review. Reviewer and authorized review interfaces. | No expiry found; user deletion currently cascades the row. | Export own ratings/reviews; erase identifying review text and account links. |
| `host_purchases`: `user_id`, Stripe checkout/payment-intent IDs, bundle, amount, currency, status/timestamps | Occasion purchases, reconciliation and refunds. Account owner and commerce administrators; Stripe. | No expiry found. User deletion currently cascades purchase rows. | Export own purchases. Define approved financial retention and anonymization before changing deletion behavior; Stripe IDs remain linkable through Stripe. |
| `host_occasion_ledger`: `user_id`, `game_id`, `purchase_id`, `note`, `created_by_user_id`, `free_first_key`, entry/timestamps | Occasion balance and financial audit trail. Owner and commerce administrators. | No expiry found. User deletion currently cascades ledger rows. | Export own entries; erase identifying notes, account/creator links and free-first identity keys under the approved financial-retention design. |
| `email_delivery_log`: `recipient_email`, `related_game_id`, `sent_by_user_id`, `error_text`, message type/status/timestamps | Delivery troubleshooting. Authorized operators and mail service. | No automatic purge found. Error text may contain recipient/provider detail. | Erase recipient/sender-linked rows on deletion. Add the audit's 90-day operational retention, with a safe dry run and scheduled application. |
| `account_security_audit`: `actor_user_id`, `target_user_id`, event/outcome, `correlation_id`, timestamp | Security investigation. Authorized security operators. | No expiry found. | Define a justified period; remove identity links on erasure or document a specific approved exception. Do not claim these rows are anonymous while user links remain. |
| `auth_throttle`: hashed `throttle_key`, action, window/lockout timestamps, attempts/penalties | Account/IP abuse protection. Authentication service and private logs. | Cleanup supports unlocked windows older than 30 days. | Erase derivable account/user-key counters. Shared IP counters are not directly attributable to a user: document their bounded retention without clearing other users' protections. Hashing a subject does not by itself make it anonymous. |
| `plots`: `author_user_id`, title/scenario and all author-supplied story content | Authorship and published story use. Author, reviewers, runtime and intended audiences. | No expiry found. Published snapshots pin game content. | Export the owner's authored work. Define removal/anonymization of unpublished and published work, preserving other users' legitimate game access without retaining personal attribution by accident. |
| `plot_versions`: `snapshot_json`, `submitted_by_user_id`, `reviewed_by_user_id`, `review_notes`, lifecycle timestamps | Immutable submissions/reviews and pinned game snapshots. Author, reviewers and runtime. | No expiry found. Submitter reference currently restricts identity deletion. | Remove identity and personal prose from snapshots/review notes where applicable; inspect nested JSON. Author erasure must not silently change existing game outcomes. |
| `inky_story_guide_drafts`: `author_user_id`, `plot_id`, `state_json` | Private guided authoring, including free-text answers. Author and optional AI backend. | No expiry found; user deletion cascades the row. | Export own draft state; erase at deletion and implement stated draft retention. |
| `writer_media_assets`: `uploaded_by_user_id`, title/alt text, original/thumbnail paths and image metadata; `writer_media_versions`: files/crops/version metadata through `media_id` | Uploaded artwork and version history. Uploader, authorized media users and intended public audiences. Images can contain personal data. | No expiry found; deleting a user only nulls the uploader link. | Export own media metadata and a secure retrieval path; delete personal profile/media files and derivatives when erasure requires it, after resolving shared/published references. Do not equate detaching an uploader with deleting the image. |
| `plot_media_assets`: `uploaded_by_user_id`, title/alt/caption text, file paths and linked media | Attach assets to story roles, events and endings. Authors, reviewers and runtime. | No expiry found; uploader reference restricts identity deletion. | Inspect attribution, files and shared references; erase personal fields under the same media policy. |
| `simulation_review_batches`: `requested_by_user_id`, `summary_json`, `review_package`, `last_error`; `simulation_review_batch_runs` through batch/simulation IDs | Author testing and review evidence. Authorized writers/reviewers. | No expiry found. Requester reference restricts identity deletion. | Export relevant owned summaries safely; erase identity and nested personal text. Never export other users' private game material. |
| `story_audit_runs`: `requested_by_user_id`, `audit_text`; `story_correction_imports`: `imported_by_user_id`, `original_filename`, `package_json` | Authoring audits and imported correction history. Authorized writers/reviewers. | No expiry found. Both actor references restrict deletion. | Export owned submissions/results; remove actor links and personal text/files as applicable. |
| `ai_reasoner_interactions`: simulation/character/decision IDs, `purpose`, endpoint/model, `request_json`, `response_json`, `parsed_response_json`, `reason_text`, `error_text`, action/confidence, timing/usage/device metrics | AI troubleshooting, usage and reasoning evidence. Authorized AI/review operators; the configured backend receives request content. | Raw request/response payloads older than 90 days may be nulled by the existing CLI job. Parsed JSON, reason/error text and metrics remain. Unfinished requests older than 24 hours are marked failed, not erased. | Add reliable actor/source attribution, especially for Inky calls with null game IDs. Apply the 90-day text rule to all text-bearing fields, handle legacy unattributed logs explicitly, and separate non-identifying metrics from retained personal text. |
| `platform_settings`, `email_templates`, `genre_themes`, `site_page_sections`, `site_parallax_layers`: `updated_by_user_id`; page/template free text, JSON, paths and embedded links | Operate public content and platform settings. Privileged editors; public visitors for rendered content. | No expiry found; some updater links already null on user deletion. | Remove personal attribution and any account-related public content under an explicit request process. Configuration credentials must never be included in account exports. |

## Files, Cookies And Infrastructure

- Profile images: `public/uploads/profiles/` plus the account's database path.
  Delete old/current files and derivatives with verified containment; never
  follow an arbitrary stored path outside the upload directory.
- Story/media uploads and their versions/thumbnails can contain real people.
  Published snapshots and indirect file references require a complete reference
  audit; a simple `uploaded_by_user_id=NULL` is insufficient.
- PHP session files contain account ID, CSRF/authentication state and pending
  account-link tokens. A-05 uses Secure, HttpOnly, host-only `__Host-toinky`,
  SameSite=Lax. Existing UI scripts also use local storage for preferences and
  session storage for interface state. Do not describe the site as having only
  cookies while omitting browser storage.
- Apache/cPanel, PHP error logs and backups can contain IPs, request URLs,
  timestamps and identifiers. Incoming emailed bearer URLs may reach access
  logs before application redirection. Production redaction, access, retention
  and backup-expiry evidence remain A-19 dependencies.
- SMTP configuration and Stripe/AI authentication secrets are operational
  credentials, not account-export data. They are private server/configuration
  concerns under A-12. This inventory does not read or reproduce them.

## External Recipients And Visibility

| Recipient / Audience | Confirmed Code Path | Remaining Disclosure / Evidence |
| --- | --- | --- |
| Hosting and mail delivery providers | PHP/database files, server logs, authentication/invitation/notification email. | Confirm provider identities, locations, subprocessors, access and retention; do not invent a Canadian-only processing claim. |
| Stripe | Hosted Checkout receives purchase reference, user ID and bundle metadata. Toinky stores Stripe session/payment-intent identifiers, amount and status. | Confirm provider terms, processing locations, financial-retention basis and request/refund handling. Payment details entered at Stripe are not a reason to export secrets or invent local card storage. |
| Operator-configured Ollama service through the approved tunnel | AI requests include prompts and selected story context. The approved hostname is `endurable-cleft-wildlife.ngrok-free.dev`. | Actual machine, operator access, transit/processing locations and retention are not verified. Hosted authentication/inference currently fails. Do not claim requests succeeded, that all AI stays in Canada, or that the model provider receives no personal text. |
| jsDelivr / Hugging Face, if narrator is enabled | The optional browser narrator imports code and model assets. It is currently disabled in production, confirmed through admin UI. | Disclose external network access and model downloads before enabling. Dynamic import integrity remains an audit concern; playback was not verified. |
| Other players, hosts, reviewers and public visitors | Relevant display names/profile images, membership, published stories/media, approved feedback, public pages. | Separate public character fiction from account identifiers. Exports must preserve other users' private knowledge, notes and recipient addresses. |
| Google font/challenge services | A-03 removed the challenge; A-05 self-hosts the existing font catalogue. | No current font/reCAPTCHA request is intentionally required by these code paths. This is not a blanket claim that every optional third-party feature is absent. |

## Retention And Deletion Dependencies

These are **requirements/proposals**, not completed controls:

1. Implement consent versions and an age-first flow using the owner-confirmed
   minimum of 13. Do not persist the under-age input or collect it in analytics.
2. Add password-confirmed export/erasure. Exports must select safe fields,
   exclude password/token hashes and credentials, and avoid other players'
   private data. Owner correction and consent-withdrawal routes must be stated.
3. Resolve the current `RESTRICT` references before account erasure, including
   hosted games, invitations, submitted snapshots, plot media and review/audit
   records. Audit actor columns without foreign keys also need explicit cleanup.
4. Replace purchase/ledger `ON DELETE CASCADE` semantics with the approved
   retention/anonymization design. Provider IDs that can be linked back through
   Stripe are pseudonymous, not automatically anonymous. Obtain the financial
   retention/legal exception decision before promising complete anonymization.
5. Cover email logs (90 days), expired tokens/invitations (audit's 30-day cleanup
   requirement), all AI text (90 days), and a stated disabled-account period.
   Show dry-run counts; apply only through authorized scheduled operations.
6. Document how published work, other participants' games, media copies, legacy
   unattributed AI logs, security records and backups are handled. Do not silently
   preserve identifying material while reporting successful erasure.
7. Add a complete breach/request procedure and obtain counsel review of policy,
   terms, age threshold, retention periods, rights and cross-border disclosures.

## Sources

Schema metadata through migration 143; `AuthService`, `AccountSecurityService`,
`RateLimitService`, `UserProfileService`, `LobbyService`, `CommerceService`,
`StripeService`, `AiInteractionLogger`, `AiConnectionService`, `NarratorService`,
authoring/media services; `tools/prune_expired_tokens.php` and
`tools/prune_ai_logs.php`; owner decisions and production UI checks recorded
November 12, 2026. This document does not change Auditor finding statuses.
