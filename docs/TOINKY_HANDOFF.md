# Toinky Project Handoff

**Handoff date:** 2026-09-03

**Primary handoff document:** [Toinky - Handoff Document](https://docs.google.com/document/d/18CDcgrcHctWcG6BRBd43ij43fJGQ0FGtwapqpN8UoVw/edit)

The shared Google Handoff is the primary continuity record, and the shared
Story Writer's Guide is the primary writer-facing source. This repository file
is the code-local implementation mirror and should be synchronized only after
the Google documents are updated.
When the two differ, verify the current GitHub `main` state and update this
mirror rather than treating older copied sections as current.

This document is the continuity note for the next engineer or coding agent.
Use it with `README.md`, `docs/STORY_AUTHORING.md`, and `docs/INKY.md`.

## Current state

Toinky is a database-authored, character-driven multiplayer story engine. The
generic engine is active in production development. Story content, stable
codes, conditions, consequences, integrity rules, Inky catalog entries, and
outcome data belong in the database or catalog data. PHP provides generic
orchestration and must not contain rules for one named story.

Inky v1 is active in Story Writer:

- Octopus is the default catalog character from direction F.
- Lantern is the active second option from direction D.
- Squid is catalog-backed future artwork from direction G.
- Lantern and Octopus use optimized frontend assets while source artwork is
  preserved; Lantern uses the pinned PixiJS runtime with a generic DOM
  fallback.
- User profile settings control enablement, companion naming, character,
  size, docking, movement, sounds, reduced motion, and proactive help.
- **Write New Story with [Inky name]** is a guided five-question conversation.
  It produces a reviewed brief and does not create a pre-built story
  structure until the writer explicitly creates the draft.
- The current Draft with Inky and What can I do for you? action rules are
  maintained in `docs/INKY_ACTION_RULES.md`. Keep that file synchronized with
  the frontend handlers and Inky services when adding or changing actions.
- Continue Draft now sends a bounded live package containing direct Story
  Framework values, locations, characters, events, and decisions, and displays
  those values in a grouped Story Framework Values section.
- Suggest a Location, Suggest an Event, and Suggest a Decision use advancing
  proposal keys, exclude declined or accepted repeats, and save only through
  explicit approval and generic authoring validation. Decisions attach to an
  existing event and character and include both options.
- Improve Story reviews remain advice-only for published stories. Oversized
  audit history is compacted before the AI request so readable published
  packages still receive feedback.
- Story Writer navigation and layout standards are maintained in
  `docs/STORY_WRITER_UI_STANDARDS.md`, with regression coverage in
  `tools/writer_navigation_test.php`.

Four published stories are live in production: The Storm At Bellweather Resort,
The Empty Throne, Whispers Of The Forgotten Shore, and A Nut To Crack (published
Oct 22, 2026; live Revision 3). The play screen renders authored prose verbatim,
shows per-event images in the Now box and timeline, and per-ending state-aware
images; every event and ending fragment in all four stories carries an attached
image, and every effect carries a Narrative Detail (completed Oct 23, 2026).
Full dated record, including the Oct 22 platform batches, the Oct 23 image and
narration programs, one played game test per story, and the media deletion
guard (built, awaiting deploy), is in the Google Handoff doc — the primary
continuity source.

## Milestone 3 — Guided Inky character authoring

**Completed 2026-09-04.** Story Writer character suggestions now have a
complete review-to-save handoff:

- `Suggest A Character` checks active locations and remaining Story Framework
  player or AI-character capacity.
- The result appears in a bounded side card with a visible close control.
- `Add and Continue Story with [Inky name]` opens the suggestion as the active
  Continue Story proposal.
- The writer can edit and copy the proposal, then `Accept and Save` persists it
  through `save_proposal` and the generic authoring service.
- `Suggest Another` receives the live edited field values and excludes older
  accepted character proposals from its AI context.
- The old direct Characters review link was removed because it did not persist
  the pending suggestion.

Implementation locations:

- `public/assets/toinky-inky.js`: side-card rendering, close behavior, and
  Continue Story handoff.
- `public/assets/toinky-inky-guide.js`: editable proposal state, copy
  fallback, approval, and live revision payload.
- `app/InkyGuideService.php`: authoritative live-proposal context and
  character-context isolation.
- `tools/inky_source_test.php`: regression markers for the handoff contract.

## Milestone 4 - Documentation authority and Story Writer standardization

**Completed 2026-09-04. Source commit: `9cf1af7`.** The shared Google
Handoff is the primary project continuity source and the shared Story Writer's
Guide is the primary writer-facing source. Repository documentation is their
synchronized implementation mirror.

This milestone records the standardized Story Writer navigation and layout,
Draft with Inky proposal rules, What can I do for you? action rules, Media and
Artwork organization, lifecycle safeguards, PHP 8.4/cPanel deployment checks,
validation requirements, and recovery expectations. Future changes update the
shared Google documents first, then synchronize the repository mirrors and
record implementation/deployment state separately from authored story data.

## Latest authoring safeguard

Story Writer now supports the following lifecycle:

1. A story with no authored records and no play history may have its Stable
   Story Code edited.
2. The same empty, never-played story may be permanently deleted from Story
   Framework → Story Overview.
3. Any location, character, event, decision, option, opportunity, story value,
   ending, artwork, revision, audit record, or simulation locks the story code
   and blocks deletion.
4. The server recalculates usage from live database references before both
   operations. UI visibility is only a convenience and is not the security
   boundary.
5. Duplicate stable story codes are rejected. A blank code submission on an
   existing story preserves its current code.

Implementation locations:

- `app/StoryAuthoringService.php`: `storyCodeUsage()`, `updateOverview()`,
  and `deleteStory()`.
- `public/writer.php`: Story Overview control and POST action.
- `tools/story_writer_stable_code_test.php`: dependency, rename, and deletion
  regression coverage.

## Validation and deployment

The terminal-first Inky diagnostic process and incident record are maintained
in [docs/INKY_DIAGNOSTICS.md](INKY_DIAGNOSTICS.md). Diagnose the endpoint and
latest recorded interaction before changing application behavior; temporary
request tracing must be removed after reproduction.

The local workspace does not include PHP, so PHP lint and database tests must
run on the cPanel host after deployment:

```bash
cd /home/toinkyadmin/public_html
PHP=/opt/cpanel/ea-php84/root/usr/bin/php
$PHP tools/migrate.php
$PHP -l app/StoryAuthoringService.php
$PHP -l public/writer.php
$PHP -l tools/story_writer_stable_code_test.php
$PHP tools/story_writer_stable_code_test.php
```

Also run the relevant Inky and Story Writer contract tests available in
`tools/`, then manually verify an empty draft can be renamed and deleted while
a story with a location or simulation is blocked. Never run destructive tests
against production data; the regression test uses a transaction and temporary
records.

The cPanel deployment now runs `tools/migrate.php` explicitly. The root
`.htaccess` blocks direct HTTP access to private application, database, and
tool directories, while CLI includes continue to work. If a deployment fails,
keep the previous commit available for rollback, inspect the deployment log,
and rerun the migration command before opening Story Writer. Do not work around
a failed migration by deleting `schema_migrations`.

The public web handler must be set to PHP 8.4 in cPanel's MultiPHP Manager or
Select PHP Version. Running `/opt/cpanel/ea-php84/root/usr/bin/php` in the
terminal only verifies the CLI runtime; it does not change the PHP version used
by Apache. A web request served by an older PHP version will fail while parsing
the public entrypoint, typically with `unexpected '?'`. The root entrypoints
now return a direct PHP-version message in that case. Application timestamps
default to UTC, so the web handler's `date.timezone` setting is no longer a
runtime prerequisite.

Before release, check `git diff --check`, inspect the changed files, deploy to
the intended Toinky host, run the cPanel tests, and perform a live Story Writer
smoke test. Do not edit files under `sources/`; they are synced reference
material when present in a ChatGPT project mirror.

## Next work

- Continue guided authoring actions for individual Story Writer entities while
  preserving explicit review, server validation, and generic persistence.
- Keep the Inky mood engine, profile preference schema, renderer fallback, and
  optimized asset loading stable while adding future character packages.
- Expand production smoke coverage for Inky, Story Writer, hosting, and AI
  offline/error states.
- Keep story-specific content in database migrations or authoring data, never
  in shared PHP engine conditionals.