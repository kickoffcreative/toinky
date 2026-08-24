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

## Milestone 2 — Multiplayer Platform (feature branch)

- Public registration, sign-in, password reset, and invitations
- Hosted games with shareable six-character codes
- Near-real-time lobby polling and atomic character claims
- Host-controlled start after every player selects a character
- AI control for every unclaimed character
- Private player screens that do not expose other characters' private state
- Story ratings and hosted/joined history
- Administrator-only Story Engine Lab
- Grouped admin panel for users, simulations, human games, and simulation games

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
