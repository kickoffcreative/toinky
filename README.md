# Toinky

A database-authored, character-driven multiplayer story engine.

## Core Principle

One shared reality. Different personal stories.

## Architecture Rule

Story content, conditions, consequences, integrity rules, focus profiles, and outcome dimensions belong in the database. PHP provides generic orchestration and must not contain rules that apply only to Empty Throne, Concealed Failure, or any other individual story.

## Milestone 1 — Story Engine Lab

- Shared authoritative world timeline
- Character-specific knowledge, goals, fears, locations, relationships, stats, decisions, and consequences
- Database-defined dynamic opportunities and integrity rules
- Manual, weighted, and local-AI simulation support
- Configurable outcome dimensions
- Empty Throne and Concealed Failure validation stories

## Milestone 2 — Multiplayer Platform

- Public registration, sign-in, password reset, and invitations
- Hosted games with shareable six-character codes
- Near-real-time lobby polling and atomic character claims
- Host-controlled start after every player selects a character
- AI control for every unclaimed character
- Private player screens that do not expose other characters' private state
- Story ratings and hosted/joined history
- Administrator-only Story Engine Lab
- Grouped admin panel for users, simulations, human games, and simulation games

## Milestone 3 — Guided Inky Authoring

Completed 2026-09-04:

- Guided five-question story development inside the floating Inky companion,
  with resumable drafts and editable answers.
- Structured Story Writer proposals for overview fields, locations, story
  values, characters, events, decisions, opportunities, and endings.
- Explicit review and approval before proposed content is written to a story.
- `Suggest A Character` now opens the suggested character in Continue Story,
  where the writer can edit, copy, review, and approve it through the normal
  authoring service.
- Character revisions use the writer's current field values and exclude older
  accepted character proposals from the revision context.

## Administrator Bootstrap

After registering the intended administrator through the public portal, run:

```bash
/opt/cpanel/ea-php84/root/usr/bin/php tools/promote_admin.php you@example.com
```

The Story Engine Lab is then available at `public/lab.php`, and the admin panel at `public/admin.php`.

For correct email links, configure:

```bash
GAME2_BASE_URL=https://game2.sixpillarsmodel.com/public
GAME2_MAIL_FROM=no-reply@sixpillarsmodel.com
```

## Validation

Every pull request runs PHP 8.4 lint plus an isolated MariaDB engine baseline and multiplayer lifecycle test. To run the generic multiplayer smoke test against a configured non-production test database:

```bash
GAME2_DATABASE_CONFIG=/absolute/path/to/database.test.php \
/opt/cpanel/ea-php84/root/usr/bin/php tools/multiplayer_smoke.php
```

The smoke test creates temporary users and a temporary simulation, verifies lobby and ownership behavior, then removes its test records.

## Deployment safety

The cPanel deployment runs database migrations explicitly through
`tools/migrate.php` before the release is considered ready. Web requests use a
deployment marker and do not rerun migrations when the deployed migration set
is unchanged. If a deployment is interrupted, the web fallback can still
complete pending migrations; rerunning the deployment is the preferred
recovery path.

The repository also deploys a root `.htaccess` that blocks direct HTTP access
to `app/`, `config/`, `database/`, `tools/`, and the internal `public/` tree.
Keep diagnostic and migration tools CLI-only and do not expose them as web
endpoints. Deployment installs these rules using `tools/deploy_htaccess.php`,
preserving cPanel-generated PHP handler and PHP INI blocks from the live file.
If the PHP handler was removed by an older deployment, it restores the
`application/x-httpd-ea-php84` handler used by this host. Before replacing the
file, it saves a private backup under `config/htaccess-backup-*` and then
replaces `.htaccess` atomically. An incomplete cPanel block stops deployment.
Do not copy the repository `.htaccess` directly over the live file.

The cPanel domain must use PHP 8.4 for its web handler, configured in
MultiPHP Manager or Select PHP Version. This setting survives subsequent
cPanel Git deployments; it does not need to be reapplied for each release.
The shell binary used for linting and
migrations does not control Apache's PHP version. If the web handler is older,
the public entrypoint displays a compatibility error before loading the
application. The application explicitly uses UTC so it does not depend on the
server's timezone setting.

## Story writing

Story ownership, optional writer-uploaded artwork, player-immersion rules, and the required validation order are documented in [docs/STORY_AUTHORING.md](docs/STORY_AUTHORING.md).

## Inky

Inky is Toinky's optional guided AI companion and co-writer. Its approved
settings, safe placement rules, Qwen availability behavior, structured
interaction contracts, and validation requirements are documented in
[docs/INKY.md](docs/INKY.md). The current implementation status and the
repeatable workflow for designing and adding future characters are documented
in [docs/INKY_CHARACTER_PLAYBOOK.md](docs/INKY_CHARACTER_PLAYBOOK.md). Current
continuity, deployment checks, and handoff instructions are documented in
[docs/TOINKY_HANDOFF.md](docs/TOINKY_HANDOFF.md). The terminal-first failure
diagnostic process and incident record are documented in
[docs/INKY_DIAGNOSTICS.md](docs/INKY_DIAGNOSTICS.md). The shared Google Doc is the
[primary handoff record](https://docs.google.com/document/d/18CDcgrcHctWcG6BRBd43ij43fJGQ0FGtwapqpN8UoVw/edit).
