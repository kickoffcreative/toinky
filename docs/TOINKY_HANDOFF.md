# Toinky Project Handoff

**Handoff date:** 2026-09-03

**Primary handoff document:** [Toinky - Handoff Document](https://docs.google.com/document/d/18CDcgrcHctWcG6BRBd43ij43fJGQ0FGtwapqpN8UoVw/edit)

The shared Google Doc is the primary continuity record. This repository file
is the code-local implementation mirror and should be updated alongside it.
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

The local workspace does not include PHP, so PHP lint and database tests must
run on the cPanel host after deployment:

```bash
cd /home/toinkyadmin/public_html
PHP=/opt/cpanel/ea-php84/root/usr/bin/php
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
