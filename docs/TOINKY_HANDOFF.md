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

2026-10-23 — Consolidated entry (covers Oct 22 build day + overnight Oct 23; written at the start of Oct 23 per the once-a-day cadence)

A NUT TO CRACK — BUILT, VERIFIED, PUBLISHED
- New comedy story (plot 7, A_NUT_TO_CRACK): Marvolo's will settles his estate via a game night in his Manhattan penthouse (2021 mid-COVID bubble as frame, never punchline). Five playable roles: Guran, Oliver, Ma Mamita, Vivian, Quay Lee. NUTS are a per-character stat awarded only for candor that costs the character something, never deducted; money splits equally, Nuts set inheritance-draft order. Design package: goals/marvolo-s-last-game-story-build/files/a-nut-to-crack-package.md.
- Revision 2 submitted + verified (25/25 endings, zero warnings). PUBLISHED Oct 22 as live Revision 3 (Randi submitted the final option-effects completion pass).
- Belinda removed entirely at Randi's ruling: unplayable characters are removed rather than converted — NPCs are excluded from the lobby roster in code (claims rejected server-side), and her character record was deleted from the story data Oct 22. She remains only as a voice in authored prose (the Marvolo pattern). Live cast is exactly 5.
- Writer's Guide additions Oct 22: B12 (opportunity arbitration) and B13 (no decisionless characters — officiants stay prose presences, not roster records). The Guide has no repo mirror.

CROSS-STORY REGRESSION + PUBLISHES (Oct 21–22)
- Verdict: NO regression. Bellweather Revision 2 PUBLISHED Oct 22 (batch 69 Human Random x25 PASS; WREN rescue chain proven 30/30). Empty Throne post-fix baseline Human Random x25 (14 ending families). Whispers Revision 13 PUBLISHED Oct 22 ~22:49 MDT — freezes the ISLANDS_SECRET priority fix (10/20/100); public page verified live and hostable.
- BRIANNAS_ANSWER fix verified closed (batches 88/89): renders 9/9 sampled endings with goal-matched variants. Root cause was dual effect appliers; chosen-option effects run through BehaviorService.

IMAGE PROGRAM (Oct 22–23) — COMPLETE
- Standards: covers AND location scenes 1920x1280 landscape (3:2); character portraits 1080x1920; no floating text on covers (text only as in-scene objects); same place keeps the same geography/orientation across all images (viewpoint locked from existing images first); approved character portraits are the identity reference whenever a character appears; foreground humans checked for natural proportions.
- Per-event + per-ending images for all four stories, all Drive-saved and studio-attached and verified in the Artwork tables: Empty Throne 32 (6 events + 26 endings), Bellweather 29 (6 + 23), Whispers 29 (8 + 21), A Nut To Crack 30 (8 + 22).
- Fixes inside the program: three Empty Throne endings proportion-corrected (CROWN p7, GOVERNMENT p10, REVIEW p10 — young Elian's oversized head in medium shots); Nut MARVOLO p100 regenerated against the canon portrait (v1 had drifted to a grey-haired stranger).
- Likeness + proportion recheck across all 101 first-pass images: PASS (record: goals/cross-story-image-standards/files/LIKENESS-PROPORTION-RECHECK-2026-10-23.md).
- Media library cleanup: 44 unused items deleted (32 first-pass BACKGROUND duplicates + 12 superseded versions), library 222 → 178 at cleanup time; all four Artwork tables verified unchanged afterwards. Media-library types gate the studio pickers (Event Image lists SCENE only, Ending Image lists ENDING only) — upload with the right type; there is no UI to retype an item.

NON-GENERATED IMAGES — LIST CLOSED (Oct 23)
- The 19 disaster/peril images skipped in the first pass (Bellweather 17, Nut 2) were retried once at the end of the run per Randi's ruling, with calmer framings: aftermath and stillness instead of the peril moment; rescues and report custody told by objects, traces and empty places. ALL 19 SUCCEEDED, were Drive-saved under their listed filenames (byte sizes verified), studio-attached with correct types, and verified in the Artwork tables. The running list stands EMPTY. File of record: goals/cross-story-image-standards/files/NON-GENERATED-IMAGES.md. Any future image the generator declines goes on that list and in this doc with its Drive save location + studio target; whoever generates it later ticks it off in both places.
- Framing note for future generators: the still-life framings were not a compromise in quality — the stretcher-and-scarf and report-and-radio images read clearly at card size and keep the story's tone.

EFFECT NARRATIVE DETAILS (Oct 23) — COMPLETE
- Bellweather: 239 effects read, all already narrated — no backfill needed. Empty Throne: 405 effects, all already narrated — no backfill needed.
- Whispers: 47 empty Narrative Detail fields filled. A Nut To Crack: 107 empty fields filled (87 across events/decisions + 20 across opportunities). Opportunity effects were authored ONLY in the Opportunity editor (Decisions-tab edits to opportunity effects are silently reverted by syncBehaviorEffects; the records are shared mirrors, so Opportunity-editor fills show in both views). All fills verified persisted on reload; the new lines render live in played games.
- Held for Randi's review (not edited): Empty Throne ELIAN_BLOCKED_CORONATION_CLIMAX · ELIAN · ACCEPT_COUNCIL_REGENCY · WORLD_STATE INTERIM_AUTHORITY=COUNCIL carries a pasted-wrong Narrative Detail (it repeats the UPHOLD_ROWAN text). Proposed fix: mirror the parallel INCONCLUSIVE record's council-regency text. The option is INACTIVE, so there is no live player impact.

GAME TESTS (Oct 23) — ONE PLAYED RUN PER STORY
- Bellweather game 2MGEF6 (as Farah Xu), Whispers game J6BE5K (as Anaise), Empty Throne game 8MCRJK (as Elian), A Nut To Crack game XTAARR (as Guran) — all played through to their endings and assessed against Randi's seven criteria (fun as it progresses; visuals; keeps the player reading; fun for non-readers; motivation to finish; voice-narrator potential; core loop + incentives). Full assessments and the cumulative improvement list: toinky-regression/overnight-2026-10-23-improvements.md.
- Passes: every attached event/ending image rendered; blank images degrade gracefully (clean text-only cards); the Now box shows only the current event's image and clears when the event has none (verified live, incl. Nut THE_GIGGLING before its image landed); authored prose renders verbatim — the past-tense/observer rewriting artifacts are gone.
- Cross-story findings (all four tests): (1) decision windows of ~30 seconds are too short — testers lost choices mid-read in every story, and expiry is logged in wording that blames the player ("You Let the Opportunity Pass"); (2) the ending screen stacks all ending images first, then one merged prose block with no per-fragment headings (all 4 stories), and Whispers/Empty Throne/Nut also duplicate the ending prose verbatim as the final timeline article; (3) "Host This Story" does not pre-select its story in the host form (defaults to A Nut To Crack); (4) autonomous characters' second-person result text renders under their names, so "you" changes person mid-timeline (all stories); (5) the admin workspace sidebar stays visible during play on the admin account.
- Story-specific findings: Whispers is missing from the public Stories listing page (its page works by direct link only) and its in-game sidebar "Your Current Location" never updates; Empty Throne never surfaces factor changes (LEGITIMACY/COURT_TRUST fire invisibly), and CROWN p30 / INTERIM_RULE p10 restate the same regency beat back-to-back; A Nut To Crack never shows a Nuts tally anywhere, though Nuts are the story's score, and its timeline choice summaries run choice + consequence together with no sentence break; a word goes missing before a full stop in Bellweather ("a red engineering stamp reads .") and Whispers ("stranded in an unknown .") — same failure shape in two stories, so investigate the text path in code before hand-fixing either.
- Voice narrator assessment (all stories): would improve UX, most of all for Whispers (atmosphere) and Nut (performed comedy) — provided decision timers pause or duck while narration plays.

MEDIA DELETION GUARD — BUILT Oct 23, AWAITING DEPLOY
- Used media is now harder to delete, per Randi's spec: in the media library and the admin Pages media panel, a used item shows a "View Usage" button opening a pop-up listing every place the item is used (story artwork placements, homepage parallax layers, genre themes, site page sections, Page Builder rows); inside the pop-up, a [Force Delete Media?] button is the deliberate override. Unused media keeps the normal delete. The service layer (MediaLibraryService::usageByMediaIds + force-aware delete) refuses non-force deletion of used media. NEEDS LIVE VERIFICATION after deploy.

DEPLOYED — 2026-10-22 (recorded separately per the definition of done)
- Platform batch through 818619a2: step-boundary opportunity reconcile (0c69d947), Inky draft acceptance truncation removal (22a30e59), solo-host lobby start, AI-characters confirmation panel, plus supporting commits 8c11fef9, 627a4054, 32464ddc, 90c2354c, 5f80039e, da274673, 03b1efa4. Deployed by Randi Oct 22; Nut batch 82 clean (O5/O6/O7 25/25 each), Whispers batch 83 no regression.
- ae6070de + 1a194407 — BRIANNAS_ANSWER dual-applier / managed-value gate fix. Verified batches 88/89.
- cfd1820697 + d45a9a3cd — play-screen overhaul batch 1 (Now box, readable options, oldest-first timeline, raw-code filter, live-ending fix) and batch 2 (Event Image + Ending Image fields, migration 119, state-aware ending images with generic ENDING fallback).
- 4927fd6905 batch (deployed Oct 22 23:27 MDT): lobby NPC exclusion + claim rejection, Now-box current-event image (4989e101d5), inactive-media dependency fix (4927fd69). Lobby verified: exactly 5 Nut players, Belinda absent.
- 93c3dc87 (entity delete clears character instance rows before plot_roles delete) + 13871ca33e (play.php renders authored prose verbatim — past-tense/observer converters removed). Deployed Oct 22 23:50 MDT; production HEAD 13871ca33e. Verbatim prose verified live in Nut game 33.

BUILT, AWAITING DEPLOY (repo HEAD 4b85e787a4ed)
- e6d27267 (app/MediaLibraryService.php), bd8c45e40bb4 (public/media.php), 4b85e787a4ed (public/admin.php) — the media deletion guard described above.
