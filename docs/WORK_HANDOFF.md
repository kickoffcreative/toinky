# Toinky Shared Work Handoff

This file coordinates work between separate computers and AI tasks working on
the same GitHub repository. Chat histories are not assumed to be shared.

## Current Handoff

- **Date:** 2026-09-08
- **Task/computer:** Current Codex task on the local `main` checkout
- **Current request:** Apply the approved Toinky logo and icon across the
  website shell, browser/app identity, and branded email templates.
- **Branch:** `main`
- **Implementation commit:** `9c2b180` (`Apply Toinky brand logo and icon
  assets`).
- **Remote:** `origin/main` was last verified at `7879857`; the implementation
  and this handoff update are being pushed together.

## Completed Work

- Removed the duplicate `Story Framework Values` renderer from Continue Draft.
- Unified direct Story Writer values and accepted Cowriter values under
  `Story Values`.
- Standardized Cowriter group names and order:
  `Story Overview`, `Locations`, `Characters`, `Story Values`, `World Events`,
  `Decisions`, `Opportunities`, `Ending Fragments`, `Ending Sections`.
- Kept direct Writer entries in their matching groups with `Direct Entry`
  labeling and Writer links.
- Preserved dependency locking, decision option previews, and the existing
  Story Writer handoff links.
- Pushed the implementation to `origin/main` in commit `b73a293`.
- Fast-forwarded this checkout to the other task's mail commit `a8cafbc` after
  fetching GitHub.
- The mail implementation uses `accounts@toinky.com` as the default sender,
  supports authenticated SMTP, and records delivery results in
  `email_delivery_log`.
- The earlier production path used PHP `mail()` successfully; direct SMTP is
  optional and should not be required unless the cPanel mail transport fails.

## Brand Asset Update

- Copied the approved `Toinky Logo.svg`, `Toinky Logo.png`, and `Toinky Icon.png`
  into `public/assets/toinky-logo.svg`, `public/assets/toinky-logo.png`, and
  `public/assets/toinky-icon.png` respectively. Source and destination hashes
  were verified during implementation.
- Website shell, home hero, and public footer now render the SVG wordmark
  directly so the approved gold fill is preserved. Email composition points
  `{{logo_url}}` at the PNG wordmark for mail-client compatibility.
- Workspace and signed headers expose the icon as the browser/mobile identity.
- **Tests:** Source and destination SHA-256 checksums match for all three
  assets; static reference checks passed; `git diff --check` passed. PHP lint
  could not run because PHP is not installed in this Windows checkout.
- **Outstanding:** Confirm the push and run the production PHP lint checks on
  cPanel after deployment.

## Mail Configuration Audit

The application-side settings and their locations are:

- `GAME2_MAIL_FROM=accounts@toinky.com`
- `GAME2_BASE_URL=https://toinky.com` so invitation and reset links use the
  production domain.

`GAME2_MAIL_FROM` and `GAME2_BASE_URL` are optional overrides; the sender also
comes from the database setting. Migration
`database/migrations/096_toinky_account_mail_sender.sql` changes an existing
legacy `MAIL_FROM_ADDRESS` value, while migration 064 supplies the same sender
for new installations. `MAIL_FROM_NAME` remains `Toinky`.

The repository scan found no mail library, no committed SMTP credentials, and
no separate mail configuration file. `app/AuthService.php` uses PHP `mail()`
when SMTP values are missing, and uses a small raw authenticated SMTP client
only when the SMTP host, username, and password are all present. If direct SMTP
is used, configure these outside Git: `GAME2_SMTP_HOST`, `GAME2_SMTP_PORT`,
`GAME2_SMTP_USERNAME`, and `GAME2_SMTP_PASSWORD`.

## Verification

Local verification passed:

- JavaScript syntax checks for `public/assets/toinky-inky-guide.js` and
  `public/assets/toinky-inky-draft.js`.
- `git diff --check`.
- Static assertions confirming the duplicate renderer and legacy stage name
  are absent and all requested group names are present.

Production verification reported by the user on 2026-09-07:

- `app/StoryAuthoringService.php` passed PHP 8.4 lint.
- `tools/inky_draft_dependency_test.php 5` passed without changing story
  data.
- The latest scan reported `accepted_count=18`, `mapped_count=18`,
  `entry_count=21`, `manual_writer_count=3`, `decisions_with_options=1`, and
  `locked_count=7`.

## Remote Review and Coordination

- Fetched `origin` with pruning before starting this task.
- `origin/main` advanced from `a347bdc` to `a8cafbc` with the other task's mail
  commit; this checkout was fast-forwarded safely because it had no local
  changes.
- The mail commit overlaps this request directly and was reviewed before any
  further edits.
- Recent main-history commits relevant to the Cowriter work were reviewed,
  including the dependency map, Writer scan, decision options, location merge,
  performance, and grouping changes.
- No overlapping or conflicting pushed work from another task was visible.
- This record cannot reveal another computer's uncommitted or unpushed work.

## Documentation Note

`docs/TOINKY_HANDOFF.md` still contains older wording that says Continue Draft
displays a grouped `Story Framework Values` section. The current implementation
uses the unified `Story Values` group. Update the shared Google handoff and
writer documentation first if that wording is part of the official product
contract, then synchronize repository mirrors in a separate documented change.

## Outstanding Issues

- No known implementation failure from the latest Cowriter dependency scan.
- A live delivery test is not verifiable from this local workspace.
- Production verification reported on 2026-09-08: migrations are current and
  `platform_settings` contains `MAIL_FROM_ADDRESS=accounts@toinky.com` and
  `MAIL_FROM_NAME=Toinky`. The PHP `mail()` fallback can now be tested without
  SMTP environment variables.
- Live delivery verification reported on 2026-09-08: the latest
  `email_delivery_log` record shows `delivery_status=SENT` to the test address
  with `error_text=NULL`. PHP `mail()` delivery from the configured sender is
  confirmed; direct SMTP configuration is unnecessary for the current host.
- Added `email_templates` storage, branded defaults, sender settings, HTML/CSS
  sanitization, supported template keys, multipart HTML plus plain-text mail,
  and the Brand → Email Settings editor. Welcome, account invitation, game
  invitation, password reset, and email-change messages now use the template
  renderer. The existing PHP `mail()` transport remains the fallback.
- Added `docs/EMAIL_TEMPLATES.md` and `tools/email_template_test.php` for the
  terminal-first template contract and deployment procedure.
- Local verification for the email-template change: `git diff --check` passed and
  the embedded `public/admin.php` JavaScript parsed with Node. PHP lint and the
  database-backed email contract test are pending on cPanel because PHP and
  the project database are not available in this checkout.
- The production cPanel deployment state is not assumed from the GitHub push.
- Production PHP lint and database tests must continue to run on cPanel; PHP
  is not installed in this local workspace.
- The email-template feature is pushed to GitHub but has not been deployed to cPanel or
  verified against production database data yet.
- Do not assume the cPanel deployment has received a commit until deployment
  or a live verification is explicitly reported.

## Next Steps

1. Before new work, fetch `origin`, read this file, inspect local changes, and
   review recent commits relevant to the request.
2. Avoid editing the same files or behavior area as another task. Use a
   separate branch when overlap is recorded.
3. For Inky failures, follow `docs/INKY_DIAGNOSTICS.md`: classify the request
   path and inspect the terminal interaction record before changing behavior.
4. Before pushing, update this file with the request, decisions, tests,
   outstanding issues, and exact branch/commit state.
5. When switching computers, commit and push this handoff to the active branch;
   do not merge into `main` unless explicitly requested.
6. For mail changes, run the migrations and
   `tools/email_template_test.php` on cPanel, then verify the sender settings
   and send a controlled test email through PHP `mail()`. Configure direct SMTP
   only if that test fails; never expose an SMTP password in shell output or
   handoff records.
7. For this brand update, deploy the three asset files and run the production
   PHP lint checks before pushing or declaring the update live.
