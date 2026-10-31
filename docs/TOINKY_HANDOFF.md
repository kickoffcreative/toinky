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

**Oct 26, 2026:** Location program complete — overnight audit (00:46–03:01 MDT) fixed 16 LOCATION effects across all four stories and verified in-game (Lab sim 2915, live game 50 group move to The Siren's Fangs); Whispers Rev 17, Bellweather Rev 6, Nut Rev 4, Empty Throne Rev 10 published and live. Morning fixes (07:35–08:39): 21 more LOCATION effects + 2 new Empty Throne locations (Throne Room, Royal Archive); Writer's Guide gained B16–B20; Whispers Rev 18, Bellweather Rev 7, Empty Throne Rev 11 published and live. Nut follow-up (11:52–12:27): 3 remaining gaps fixed, Nut Rev 5 published and live. Flag (no action yet): Nut event 'The Score Is Settled' has no decision group. Super Admin rules built, deployed, verified live, 2FA tested end-to-end (migration 128; goldwynlau@gmail.com — never needs occasion credits, can never be deactivated or demoted; 2FA email code after 2 weeks without sign-in; simulated 15-day gap, code email arrived, resend issued a fresh code, sign-in succeeded; temporary simulation page removed). Menu consolidation (HEAD `2205c1221e`, verified live): admin sidebar 19 → 14 links (Theme Manager → Brand, Story Engine Lab → Simulations, Voice Narrator → AI Settings, Writer Requests + Invitations → Users sub-tabs); role gating preserved for super admin, admin, writer, player. Architecture breakdown: 8 systems mapped; 'Toinky Architecture Overview.pdf' in the Drive project root. Cleanup batch (HEAD `6fbe964853f0`, verified live): migration 129 drops `story_factor_types` + two `plots.recommended_*` columns; stale docs deleted; ~6 MB of unoptimized Inky images out of the deploy path. Page Builder Section Settings + Pages view removal (HEAD `d0f0bbc33dd8`, verified live 5/5): `save_section` action + Section Settings panel; the old Pages editor deleted (36,481 chars); stale Inky files confirmed gone from the live server (404). Audit remediation program — all 20 tasks implemented and deployed: Task 1 shutdown writes removed; Tasks 2/3/6 atomic decision resolution + transaction ownership + idempotent lobby creation/start (migration 131 idempotency key); Task 4 authoring runtime guard + Retire (role deletion blocked when cast, retire instead); Task 5 Phase 1 version pinning (migration 130: `plot_version_id` + `content_checksum` on simulations, `published_plot_version_id` on plots; hosted games fail closed without a published version); Tasks 7–10 Inky draft endpoint wrapper, Page Builder dead-field disable + atomic saves + fingerprint, admin dead code + pages redirect + unknown-action rejection; Tasks 11–14 game worker `tools/process_running_games.php` (cPanel cron not yet added), migrations out of all 17 public entry points, deploy manifest + shadow-asset cleanup, `install.php` rebrand + MariaDB 10.6 CI + `--reset` guard (database name must end `_test`, local host only); Tasks 15–20 cast gate, simulation classifier, AI-log retention (migration 132, `store_raw_payloads='0'`), token/media prune tools, 5 dead methods removed, correlation IDs + Logger + SCHEMA/OWNERSHIP/RECOVERY docs. Site-down hotfix (15:18–15:39 MDT): the audit batch deploy shipped a duplicated `public static` on `CommerceService::consumeForGame`/`releaseForGame` (commit `085d873e7602`) — a fatal parse error that took the live site down; CI had been red since ~14:42 and nobody noticed (lesson: check Actions status before deploying, never deploy while CI is red). Hotfix `19e307ec35` deployed 15:33, verified live 6/6 (dashboard, studio Retire button, Page Builder Section Settings, pages redirect, lobby create/delete game 51, Inky). Guard tests (13 new): first run 9/13 passed; the 5 failures were diagnosed and fixed the same evening (one real product bug — `simulate.php` never parsed the DSN host because the first segment's key reads `mysql:host`; the rest were test bugs: expire off-by-one, NPC slot collision, `headline` vs `headline_text`, a comment false-positive, and `.cpanel.yml` reads on the server where the file is not deployed); re-runs passed, and the final run has all 13 green on the server. Published version pointers confirmed: Nut 41, Bellweather 39, Empty Throne 40, Whispers 38. Voice narrator stays parked (Disabled). Still open: workflow files (GitHub token lacks workflow scope — Randi to apply prepared `php-validation.yml` + `simulation-lab.yml` and delete `config/database.test.php` in the same change); game-worker cron; Task 5 Phases 2–3 (Phase 2 in progress Oct 26 evening). Writer's Guide gained B21–B23 (role deletion-immutable / retire rules).

**Oct 27–31, 2026 (consolidated):** Perceptions editor built Oct 27 (HEAD 6b1bbede), deployed and verified live Oct 27; guard test 23/23 passed on the server Oct 30 (Randi, cPanel Terminal) — full studio editor for plot_event_perceptions (StoryAuthoringService::savePerception; Bellweather WALL_SHIFT block shows all 6 rows live). GitHub token with workflow scope (Randi) unblocked the Oct 26 workflow-files item: Oct 30 I replaced .github/workflows/php-validation.yml (commit 6e0eeff1b9a5f, byte-identical, 12,059 bytes) and deleted config/database.test.php (commit 7cdd045a249). Orphaned tables deleted (Randi's decision Oct 30): migration 137 drops plot_focus_option_weights, plot_focus_profiles, platform_story_audit_rules (HEAD 93fba0a5de); CI ALL GREEN Oct 30 17:35 MDT (php-lint, multiplayer-smoke, mariadb-10-6, Simulation Lab — the installer port fix cleared the last 10.6 failure); deployed by Randi Oct 30 evening and verified live 18:05–18:06 CST (homepage clean with all four stories, dashboard + Super Admin note, Bellweather WALL_SHIFT Perceptions block intact, install.php locked screen with no PHP error); Randi confirmed on the server Oct 30 18:32 CST that migration 137 is recorded with 0 orphaned tables remaining; audit cleanup fully closed. Same batch: install.php accepts db_port (default 3306). Recap cohesion build Oct 30 (Randi's asks: remove the 'Your Story So Far' heading + subtitle when the game ends; the recap reads disjointedly — no arrivals/transitions, no beat-mode attribution, no resolutions; affects all stories): commit 72c3ad3a hides heading + live-note when COMPLETE; migration 138 adds plot_world_events.recap_transition; commit 1c8d2f082 reworks story flow (opening place line 'The story begins at {place}.'; LOCATION history rows promoted to italic scene lines, multiple moves in one second collapsing first-from → last-to; scene titles above actor-led beats, deduped per second; authored override via LIVE query of recap_transition so writers can repair finished games' recaps without republishing); StoryAuthoringService + writer.php 'Recap Transition (optional)' field; SCHEMA.md; CI green; deployed by Randi Oct 30 19:08 CST and verified live 19:09–19:11 CST on game 50 (heading absent, place line + scene titles present, studio field present). Reason-aware transitions commit 17b6d038 (LOCATION scene line renders the history row's reason prose when it is real prose, else the templated 'From {old}, you arrive at {new}.'); deployed by Randi Oct 30 19:55 CST and verified live 19:55–19:57 CST (homepage + all four stories, game 50). Delegation Oct 30 19:18 CST (Randi): I decide recap transition wording myself — no more approval rounds on prose for this work; I own prose quality and report exactly what went in. Scope correction Oct 30 ~19:58 CST (Randi): the three sample points were samples — scan the whole story before the next: per story, all decision effects with movements, all event effects with movements, ALL endings; order Whispers → Bellweather → Empty Throne → Nut. Location-change + endings content program COMPLETE for all four stories Oct 30–31: Whispers — 8 events, 17 decision templates, 5 conditional opportunities; 15 LIVE location-change rows (14 decision-option + 1 no-action), corrected Oct 31 from 16 (the SIGNS LEAVE_IT template row is dead in pinned games; its detail fill is harmless dead content); 9 detail fills applied + verified; 2 event recap_transitions (FIRE_AND_STORES, BEACH_GIVES_UP) applied + verified live on game 50; SIGNS result_text fix applied to the decision template AND to the opportunity choice's Result (the opportunity record is what pinned games render — the template text alone would not have shown); endings 21 fragments / 7 sections, all Active, strongly written and well partitioned; ISLANDS_SECRET Priority-100 fallback replaced (old text contradicted unrescued runs; new 'island closes over its own story like water over a stone' text); BRIANNA_GOAL 4 values match the 4 Brianna's Answer fragments 1:1 (open: blank initial value — can a run end with goal unset? — unverified). Bellweather — 6 events, 24 perceptions, 28 decision templates, 4 conditional opportunities; 41 location-change rows (2 event-perception, 33 decision-option, 6 dynamic-option); 12 detail fills applied + verified; no event recap-transitions; endings finding made then WITHDRAWN (the full-page Endings view under-renders Outcome Conditions rows — always read conditions in per-section filtered views): conditions are present on all 18 fragments (14 matched the proposal exactly, 4 differ defensibly), endings DO branch, left as-is; flags not fixed: opportunity choices don't always match decision options (Wren opportunity choices carry only the SELF effect while decision options carry LEILA+SELF; WREN_RESCUE_GAP opportunity JOIN_RESCUE has no location effect though the decision option does; NIKO_LEADERSHIP_GAP has no location effects); Aftermath fragment names five leavers and not Leila. Empty Throne — 6 events, 6 perceptions (all SEALED_ARCHIVE, all 'No move'), 29 decision templates, 25 conditional opportunities; 14 location-change rows, all decision-option; 13 detail fills applied + verified; endings 26 fragments / 8 sections, all Active, conditioned and coherent, fallbacks for Aftermath/Crown/Government/Invitation/Letter, no prose fixes; flags not fixed: EVERY opportunity choice (all 25) drops the LOCATION effect its decision option carries (systematic divergence); header says 'DYNAMIC OPPORTUNITIES 2' while the sub-tab lists 25; Review's lowest fragment is conditional (REVIEW_STATUS EQ BLOCKED), not a pure fallback — confirm REVIEW_RESOLUTION coverage. Nut — 8 events, 0 perceptions, 42 decision templates (35 event + 7 gap), 7 conditional opportunities, no no-action rows; 10 moves, all decision-option (6 already had detail text); 4 detail fills applied + reload-verified Oct 31 00:19–00:28 CST; no event recap-transitions; endings 22 fragments / 8 sections, all Active, every section has an unconditional Priority-100 fallback — no fixes needed; flag: SHOW_HIM_PRIVATELY opportunity mirror drops its LOCATION effect (same systematic divergence). Systematic flag across stories (not fixed): opportunity-path games diverge from decision-path games on LOCATION effects (Bellweather partial, Empty Throne total, Nut SHOW_HIM_PRIVATELY, Whispers SIGNS LEAVE_IT dead row). Decision-delivery investigation Oct 31 (headline item): five agent test games (54–58, incl. Bellweather 55) never presented ANY decision to the human player at any check, though all content was verified in the studio; focused Lab batch 92 FAILED 0/2 with 'Pinned simulation 2949 is missing snapshot content perceptions. The pinned version predates this content section — re-submit the story to re-pin it.' Root cause, code + DB verified: every hosted game pins to plots.published_plot_version_id, and all four Oct 26 published versions (Whispers v38, Bellweather v39, Empty Throne v40, Nut v41) predate the 'perceptions' snapshot section added Oct 27 with the perceptions editor; BehaviorService::onWorldEvent reads pinned perceptions BEFORE creating any decision for an event, and SimulationContent::content() fails closed (throws) on a missing key; opportunity opens write the INTERACTION 'Dynamic Opportunity' (considered) row first, then throw — so games show the considered row, no decision, no 'Opportunity Passed' row, yet complete normally via skip guards; Randi's game 50 is unpinned (sim 2916), which is why his game played normally; test-game pins confirmed by DB join (54→sim 2944/v38, 55→sim 2945/v39, 56→sim 2946/v38, 57→sim 2947/v38, 58→sim 2948/v38). Fix (Randi: 'Re-submit Whispers first, verify, then the rest'): submit freezes a new revision; admin review decision PUBLISHED moves the pointer (submit alone does not publish). Whispers Rev 18 (v38) → Rev 19 (v47), published Oct 31 12:16–12:17 CST. Verification: focused batch 93 Complete 2/2 (Activated 2/2, Delivered 2/2, Applied 2/2; SEARCH_THE_CAMP 1/1, LEAVE_IT 1/1); fresh live game 59 (code VTU93U, 3-min, Anaise) — BOTH SIGNS choices appeared at 00:01, SEARCH chosen at 00:08, game completed, recap contains the fixed sentence verbatim ('They leave the wreckage on the shore and push inland, and find the hidden camp.'). Then the rest, Oct 31 12:25–12:29 CST: Bellweather Rev 7→Rev 8, Empty Throne Rev 11→Rev 12, Nut Rev 5→Rev 6, all published (Bellweather publish needed one retry after a transient ERR_HTTP2_PROTOCOL_ERROR); no content changed in any re-submit; the other three are fixed by the same mechanism, live-game verification so far for Whispers only. Test games left for manual removal: 54 ZXZEFE, 55 LC5Y7Z, 56 HN2JK7, 57 79MEGL, 58 5JRZPH, 59 VTU93U (completed games offer only Open/ARCHIVE; dashboard DELETE is disabled once a character is claimed). Standing lessons: a published version predating a snapshot section breaks decision creation in ALL pinned games — re-submit to re-pin; the full-page Endings view under-renders conditions — always use per-section filtered views. Oct 26 open items resolved: workflow files (above); Task 5 Phases 2–3 pinning/snapshot reads are live production behavior (demonstrated by the Oct 31 diagnosis). Still open: Page Builder import rejects {'rows':[]} though row-less sections now exist; story scroller paging verification once a fifth story is published; Nut event 'The Score Is Settled' has no decision group; game-worker cron (tools/process_running_games.php, cPanel cron not yet added); Task 5 Phase 4 candidates (state-machine metadata into the snapshot if it gains a studio editor; zero-window pinning for create() cast/factor copying); Story ownership FAIL in CI (no users in a fresh build — structural, does not gate smoke); optional Writer's Guide B-rule for perceptions authoring; world-event LOCATION backward-compat branch unused (keep as safety net); Whispers BRIANNA_GOAL blank-initial question; Empty Throne REVIEW_RESOLUTION coverage; opportunity-vs-decision LOCATION divergence (above); perception fallback arrival line still unobserved live (Bellweather game 55 check was void by design — Leila starts at East Stairwell and the perception moves her to East Stairwell, and moves only write a row on actual change); reason-aware move line still unobserved live (no test game produced a move after the fix). Deployed commits this period (recorded separately): 6b1bbede (perceptions editor, deployed Oct 27), 114d4f010a (multiplayer-smoke green, deployed Oct 27 07:12 MDT), 6e0eeff1b9a5f (php-validation.yml), 7cdd045a249 (delete database.test.php), 93fba0a5de (migration 137 + installer port fix, deployed Oct 30 evening), 72c3ad3a + 512b38f74f + 1c8d2f082 + 237c9633b + a632589c + 8d282a2dc (recap build, deployed Oct 30 19:08 CST), 17b6d038 (reason-aware transitions, deployed Oct 30 19:55 CST); Oct 31 re-publishes are content versions only (Whispers v47; Bellweather Rev 8, Empty Throne Rev 12, Nut Rev 6).

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
