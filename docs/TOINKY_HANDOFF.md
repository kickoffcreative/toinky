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
**Oct 23, 2026 (afternoon):** Fix-everything sweep deployed (repo HEAD `1b227b4b98`): the play timeline no longer drops a player's own decision entries whose result text is empty (the “You Chose to” receipt renders for main-event decisions); player pages use the shared in-page confirmation modal (`_confirm_modal.php`) instead of native `confirm()`; the workspace drawer starts closed on the play page; and the engine now enforces the studio's **Open only once** opportunity setting (migration 121; see `docs/STORY_AUTHORING.md` and Guide rule B15). The Story Writer's Guide gained rule B14 (image generation and likeness verification) after a folder audit caught four likeness failures a shallow recheck had passed, plus three near-duplicate Empty Throne hall images were regenerated with distinct beats. Full record: the Google Handoff's Oct 23 addendum.

**Oct 24, 2026 (consolidated, Oct 23–24):** Stories published Oct 23 — Whispers Rev 15 (EVENT_STORM P4 "unknown island" → "unknown shore"), Bellweather Rev 4, Empty Throne Rev 8; all verified live + hostable; held content items closed (ET narrative detail + Bellweather stamp sentences were already correct in storage). Drive archive correction Oct 23 (the morning "unarchive" was a mistype; all 49 wrongly-restored files moved back; Nut 42/40, Empty Throne 43/21, Bellweather 39/38, Whispers 40/45). Durations merged Oct 23 (migration 124 retires the story timeline clock; events fire at trigger_percent × run duration; Host Durations is the only control; 3/5/15-min are admin-only test lengths) and documented in `docs/STORY_AUTHORING.md` ("Run durations", commit `0fb6c3b6`). Host Purchase Path Stages 1–4 complete and LIVE: hosts buy occasions ($5 single / $12 for 3 / $19 for 5), free first occasion, occasions never expire, spent only when a game starts; Stage 1 ledger + settings (migration 125), Stage 2 Stripe Checkout + buy page, Stage 3 webhook + reconciliation, Stage 4 live launch Oct 24 — restricted key (`rk_live_`) recognised as LIVE (commit `8592072a`), mode LIVE, Purchases enabled, live test passed (May, purchase #2, $5.00; FREE_FIRST +1, PURCHASE +1, REFUND_REVOKE −1; balance 1 available, 0 held). Custom pages capability + Pricing and Refund Policy pages live. Six new hero scenes (one per public page) attached; hero editor built in Page Builder (`PageContentService::saveHero`; guard test passed 13/13 on the server Oct 24). Public-page fixes Oct 24: content width, Stories dedupe, play counts removed, story scroller (max 4 in a row; paging deferred to a 5th story), HIW Steps centered, New Page collapsible, Text Center button-layout dropdown + Force Same Width toggle (verified live), front-page buttons gold. Parse-error hotfix (commit `97ea3b6b`). User Accounts split Oct 24 (commit `a7777740`): Active users on top, Disabled users at the bottom, each paginated at 25 per page, Status column kept. Likeness-correction program running (Oct 24 evening): Rowan/Soren/Lady Mara/Captain Tomas, Bellweather group, Whispers group — regenerate with canon likeness, save to Drive, replace in Toinky, archive superseded versions.

**Oct 25, 2026:** Likeness-correction program COMPLETE — all 18 images regenerated with canon likeness, verified individually, saved to Drive (byte-verified), superseded versions archived, and replaced in the studio: Empty Throne 10 (GOVERNMENT p10/p20/p30, CROWN p18/p20/p60, LETTER p10, REVIEW p8, COUNCIL_DIVIDES event, cover), Bellweather 6 (FARAH p30/p40/p100, TRUTH p20/p30, STORY_START event), Whispers 2 (BRIANNAS_ANSWER o20, TRUTHS_COST o20). Media library 195 → 213 items. Upload fix (commit `08480dcd`, deployed Oct 25): the studio's "Upload New Media" panel was inside a collapsed details element so the file input was hidden ("No file chosen" on every upload); now open by default. Cover uploaded as PRIMARY (the Media Type dropdown has no COVER option; matches the existing cover). Picker display note: on a fresh page load the image pickers show "Keep current image" even where an image is attached — verify via the Artwork tab. User Accounts split verified live Oct 25 (Active users (5) / Disabled users (3), Status column kept, dashboard "Recent users" unchanged; pagination at 25/page confirmed in code — controls render only when a section exceeds 25). Stories republished Oct 25 (all validations passed, all verified live + hostable): Empty Throne Rev 9, Bellweather Rev 5, Whispers Rev 16. Next work: voice narrator (player-pickable voices, open-source commercially usable TTS, per-player narration controls); story scroller paging (verify once a fifth story is published).


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

- Voice narrator — the next product feature after commerce; scope it when Randi gives it a slot. Game tests pointed at it: narration needs pause-on-decision plus private briefings handled differently.
- Story scroller paging — verify the arrows and paging once a fifth story is published.
- Continue guided authoring actions for individual Story Writer entities while
  preserving explicit review, server validation, and generic persistence.
- Keep the Inky mood engine, profile preference schema, renderer fallback, and
  optimized asset loading stable while adding future character packages.
- Expand production smoke coverage for Inky, Story Writer, hosting, and AI
  offline/error states.
- Keep story-specific content in database migrations or authoring data, never
  in shared PHP engine conditionals.

**Oct 26, 2026:** Location program complete — overnight audit (00:46–03:01 MDT) fixed 16 LOCATION effects across all four stories and verified in-game (Lab sim 2915, live game 50 group move to The Siren's Fangs); Whispers Rev 17, Bellweather Rev 6, Nut Rev 4, Empty Throne Rev 10 published and live. Morning fixes (07:35–08:39): 21 more LOCATION effects + 2 new Empty Throne locations (Throne Room, Royal Archive); Writer's Guide gained B16–B20; Whispers Rev 18, Bellweather Rev 7, Empty Throne Rev 11 published and live. Nut follow-up (11:52–12:27): 3 remaining gaps fixed, Nut Rev 5 published and live. Flag (no action yet): Nut event 'The Score Is Settled' has no decision group. Super Admin rules built, deployed, verified live, 2FA tested end-to-end (migration 128; goldwynlau@gmail.com — never needs occasion credits, can never be deactivated or demoted; 2FA email code after 2 weeks without sign-in; simulated 15-day gap, code email arrived, resend issued a fresh code, sign-in succeeded; temporary simulation page removed). Menu consolidation (HEAD `2205c1221e`, verified live): admin sidebar 19 → 14 links (Theme Manager → Brand, Story Engine Lab → Simulations, Voice Narrator → AI Settings, Writer Requests + Invitations → Users sub-tabs); role gating preserved for super admin, admin, writer, player. Architecture breakdown: 8 systems mapped; 'Toinky Architecture Overview.pdf' in the Drive project root. Cleanup batch (HEAD `6fbe964853f0`, verified live): migration 129 drops `story_factor_types` + two `plots.recommended_*` columns; stale docs deleted; ~6 MB of unoptimized Inky images out of the deploy path. Page Builder Section Settings + Pages view removal (HEAD `d0f0bbc33dd8`, verified live 5/5): `save_section` action + Section Settings panel; the old Pages editor deleted (36,481 chars); stale Inky files confirmed gone from the live server (404). Audit remediation program — all 20 tasks implemented and deployed: Task 1 shutdown writes removed; Tasks 2/3/6 atomic decision resolution + transaction ownership + idempotent lobby creation/start (migration 131 idempotency key); Task 4 authoring runtime guard + Retire (role deletion blocked when cast, retire instead); Task 5 Phase 1 version pinning (migration 130: `plot_version_id` + `content_checksum` on simulations, `published_plot_version_id` on plots; hosted games fail closed without a published version); Tasks 7–10 Inky draft endpoint wrapper, Page Builder dead-field disable + atomic saves + fingerprint, admin dead code + pages redirect + unknown-action rejection; Tasks 11–14 game worker `tools/process_running_games.php` (cPanel cron not yet added), migrations out of all 17 public entry points, deploy manifest + shadow-asset cleanup, `install.php` rebrand + MariaDB 10.6 CI + `--reset` guard (database name must end `_test`, local host only); Tasks 15–20 cast gate, simulation classifier, AI-log retention (migration 132, `store_raw_payloads='0'`), token/media prune tools, 5 dead methods removed, correlation IDs + Logger + SCHEMA/OWNERSHIP/RECOVERY docs. Site-down hotfix (15:18–15:39 MDT): the audit batch deploy shipped a duplicated `public static` on `CommerceService::consumeForGame`/`releaseForGame` (commit `085d873e7602`) — a fatal parse error that took the live site down; CI had been red since ~14:42 and nobody noticed (lesson: check Actions status before deploying, never deploy while CI is red). Hotfix `19e307ec35` deployed 15:33, verified live 6/6 (dashboard, studio Retire button, Page Builder Section Settings, pages redirect, lobby create/delete game 51, Inky). Guard tests (13 new): first run 9/13 passed; the 5 failures were diagnosed and fixed the same evening (one real product bug — `simulate.php` never parsed the DSN host because the first segment's key reads `mysql:host`; the rest were test bugs: expire off-by-one, NPC slot collision, `headline` vs `headline_text`, a comment false-positive, and `.cpanel.yml` reads on the server where the file is not deployed); re-runs passed, and the final run has all 13 green on the server. Published version pointers confirmed: Nut 41, Bellweather 39, Empty Throne 40, Whispers 38. Voice narrator stays parked (Disabled). Still open: workflow files (GitHub token lacks workflow scope — Randi to apply prepared `php-validation.yml` + `simulation-lab.yml` and delete `config/database.test.php` in the same change); game-worker cron; Task 5 Phases 2–3 (Phase 2 in progress Oct 26 evening). Writer's Guide gained B21–B23 (role deletion-immutable / retire rules).
