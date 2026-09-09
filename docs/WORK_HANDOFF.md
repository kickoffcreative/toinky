# Toinky Shared Work Handoff

This file coordinates work between separate computers and AI tasks working on
the same GitHub repository. Chat histories are not assumed to be shared.

## Current Handoff

- **Date:** 2026-09-08
- **Task/computer:** Current Codex task on the local `main` checkout
- **Current request:** Audit and configure website mail delivery from
  `accounts@toinky.com`.
- **Branch:** `main`
- **Current commit:** `a8cafbc` (`Use Toinky account SMTP for mail`)
- **Remote:** `origin/main` is also `a8cafbc`

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

## Mail Configuration Audit

The application-side settings and their locations are:

- `GAME2_MAIL_FROM=accounts@toinky.com`
- `GAME2_SMTP_HOST=toinky.com` (confirm the mailbox provider's exact SMTP host)
- `GAME2_SMTP_PORT=465` for implicit TLS; port `587` is also supported with
  STARTTLS.
- `GAME2_SMTP_USERNAME=accounts@toinky.com`
- `GAME2_SMTP_PASSWORD=<the real mailbox password>`; never commit this value.
- `GAME2_BASE_URL=https://toinky.com` so invitation and reset links use the
  production domain.

These `GAME2_*` values are read from the server process environment and are not
created by `.cpanel.yml`. Set them through the hosting/PHP environment used by
the web handler, not in Git-tracked source. Migration
`database/migrations/096_toinky_account_mail_sender.sql` changes an existing
legacy `MAIL_FROM_ADDRESS` value, while migration 064 supplies the same sender
for new installations. `MAIL_FROM_NAME` remains `Toinky`.

The repository scan found no mail library, no committed SMTP credentials, and
no separate mail configuration file. `app/AuthService.php` uses a small raw
SMTP client when all three SMTP values are present, and falls back to PHP
`mail()` when they are missing.

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
- SMTP environment variables, the real mailbox password, the provider's exact
  SMTP hostname, and a live delivery test are not verifiable from this local
  workspace.
- The production cPanel deployment state is not assumed from the GitHub push.
- Production PHP lint and database tests must continue to run on cPanel; PHP
  is not installed in this local workspace.
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
6. For mail changes, run the migration on cPanel, configure the five mail
   environment variables plus the production base URL, verify the web handler
   sees them, and send a controlled test email. Do not expose the password in
   shell output or handoff records.
