# Player Experience Remediation

Batch started November 12, 2026. Scope: all gaps in the twelve-game player
report, reconciled against current code and authored content. User authorized
routine tested merges and requested one consolidated batch. Deployment stays
with the user. Story changes use studio controls, never content SQL migrations.

Code baseline: `4594e0e2ff2087f382fc383b5baa7708772b1cfb`, preserved locally
as `rollback/before-player-experience-batch-2026-11-12`. Last user-confirmed
deployed revision: `0ab6e8487461f0ed260a4fc7f048d11fd172d31f`.

## Completion Standard

Every finding needs a disposition: fixed with evidence, already fixed with
evidence, not reproduced, deliberate design with explanation, or blocked with
the exact missing prerequisite. A proposed change is not a completed fix.
Do not silently substitute an advisory check for a behavioral/content repair.
Preserve original content and revision identifiers before studio edits. Existing
finished games remain pinned evidence, not tests of a newly published revision.

## Work Register

| ID | Finding / Work | Current Status |
|---|---|---|
| H1 | Zero-credit hosting action fails too late | Code implemented; occasion lifecycle and hosting guards pass; live UI verification pending |
| H2 | Failed hosting loses story/duration selection | Failed POST and compatible story-switch duration preserved; tab-scoped preferences retain selection across occasion purchase navigation; browser verification pending |
| D1 | Choice acceptance/failure needs clear feedback | Choice receipts, progress, polling interaction protection and error recovery implemented; 12 fixture checks pass; live reproduction cause remains unproven |
| D2 | Single-option prompts presented as meaningful alternatives | Single-option heading and take-action-or-wait explanation implemented; legal gates preserved |
| P1 | Long solo idle periods | Optional solo Skip Quiet Time implemented with clock offset; 16 database checks and multiplayer regression pass; live verification pending |
| P2 | Tomas's oath conflict never tested after first choice | Fixed late Tomas decision authored and reload-verified with three choices and six factor effects; fresh-version play pending |
| P3 | Sparse Elian, Soren, Jerome, Ghost paths | Soren fixed governing decision, Elian two final alternatives, and Ghost/Jerome late scenes authored and reload-verified; fresh-version role play pending |
| B1 | Rescue completed, then guest again needs carrying | Canonical rescue status synchronized in draft, completed-guest aftercare added; reload-verified; fresh-version play pending |
| B2 | Second helper repeatedly requested after commitment | Five helper/rescue prose fields revised and reload-verified; fresh-version play pending |
| B3 | Leila omitted from aftermath | Fallback aftermath now names Leila; saved and reload-verified |
| S1 | Stranded ending also says leaving/coming home | Verify all selected fragments; make departure claims path-consistent |
| S2 | Ghost offered bargain; ending credits refusal | Verify exact result/fragment conditions and repair attribution |
| S3 | Bargain cost versus freedom at dawn unclear | Clarify the same price in prompt, result, and aftermath |
| S4 | Bernard exchange lacks an explicit outcome | Reconcile trade path and family fate |
| S5 | Mother revealed early and again at dawn | Preserve hints while reserving first identification for intended meeting |
| S6 | Exorcised cave reappears as active in teaser | Condition teaser or write a compatible closing invitation |
| S7 | Known truth and remaining mystery contradict | Qualify knowledge per path |
| S8 | Departure prose and displayed locations disagree | Verify final-state conditions and group movement |
| S9 | Storm repetition, missing charm/footage transitions | Storm, charm route and token, phone origin and watcher frame revised; two authoritative charm movement effects reload-verified |
| N1 | Comedy action labels/results combine or contradict | Generic recap action-heading merge repaired and tested; live code deployment pending |
| N2 | Study recognition follows an already-open door | Vivian recognition result reframed as a recurring remembered sound; saved and reload-verified; event sequencing remains unchanged |
| N3 | First Nuts announcement after earlier awards | Score-neutral house announcement saved and reload-verified |
| W1 | Confessions, mimes, reunion summarized rather than enacted | Ten charades, Vivian disclosure, Jerome confessions, Ghost watch, mother reunion and Throne closing scenes dramatized; fresh player judgment pending |
| W2 | Administrative/engine language interrupts prose | Separate or revise mechanics-heavy story text |
| W3 | Vivian's ending uses a reductive label | Vivian priority 10 ending uses her name instead of reductive label; saved and reload-verified |
| T1 | Throne status report lacks personal aftermath | Shared Throne aftermath adds enacted Tomas and Soren closing exchanges; reload-verified; political outcome fragments preserved |
| V1 | Managed and legacy state vocabularies disagree | Read-only runtime comparison added to source; live domain/transition inventory and data repair await deployment |
| V2 | Ending compatibility across sections | Shore lit-fire/low-safety missing branch authored with two exact guards; broader fresh-path compatibility verification pending |
| V3 | Future authoring standard | Working authoring standard documented in STORY_VALUE_STANDARD.md; distinct from validator enforcement |
| Q1 | Regression for every selectable role | Pending fresh-version gameplay and code checks |
| Q2 | Consolidated release evidence and rollback | Pending final CI, content revision ledger, and report |

## Session Notes

- Studio authentication expired; user signed in manually. No credentials were
  requested or stored. Studio is available under the Randi account.
- Shore current overview shows four human players and two AI characters,
  nine events, and 47 version records. This is not itself proof of the current
  published revision number; verify that separately.
- The latest shared handoff records older fixes through games 96–116. The
  twelve-game report covers games 121–140, so repeated findings require current
  verification rather than being dismissed because the handoff says fixed.
- Shore draft: 13 ending passages rewritten and reload-verified; bargain prompt,
  label and result clarified; Brianna's return-father result dramatized; storm,
  dawn reunion and final event prose/transitions revised. Original fields are
  preserved in the Shore rollback documents. No new revision published yet.
- Shore departure: FINAL_STATE LOCATION At Sea effect had no conditions. Added
  WORLD_STATE SIGNAL_FIRE EQ Spotted and a boarding detail; reloaded and verified
  the persisted guard and unchanged four targets. Rescue and stranded runs still
  require fresh-version verification.
- Hosting and decision-interaction code changes are in progress on the batch
  branch. Eleven deterministic polling-interaction checks pass locally. No
  production code deployment and no new live games in this batch yet.
- The apparent missing ending conditions were an accessibility/viewport reading
  limitation. DOM/layout inspection proved the controls exist; do not report it
  as a confirmed editor defect.

## November 12 Continuation

- Bellweather draft rescue outcomes now synchronize canonical GUEST_STATUS with
  GUEST_EVACUATION_STATUS. Complete carry records EVACUATING then EVACUATED;
  shelter and wider-warning paths record AT_RISK. Opportunity eligibility excludes
  an evacuated guest. These saves were reload-verified, not published or played.
- Bellweather final rescue prompt acknowledges that the helper and route are
  ready. Leila final prompt revised; reload verification remains pending.
- Leila aftercare option was partially entered but not submitted before browser
  restoration. It is not a saved repair. Reconcile existing option codes before
  recreating it. Original fields are in the two Bellweather rollback documents.
- Restored browser is signed out; manual sign-in requested. Saved studio draft
  repairs remain distinct from the lost, unsaved option form.
- Choice errors pause polling and now provide a direct GET Refresh Story link;
  this does not retry the choice or assert that it was accepted.
- PHP syntax checks passed for app, public, tools and config in PHP 8.4.

## Next Run

- Paginate the signed-in **Your Games** table, as requested by the user. Keep
  this follow-up separate from the current consolidated story repair batch.

## Bellweather Checkpoint 2

- Signed-in studio access restored manually; no credentials received or stored.
- Both Leila REAR_EXIT aftercare options are now saved and reload-verified,
  each gated by WORLD_STATE GUEST_STATUS EQ EVACUATED. Reassurance changes
  TRUST by 3; the injury account changes EVIDENCE by 4. Both effects are active.
  These are draft additions, not fresh-game verification or a published revision.
- Five rescue prose fields were saved and reload-verified: PRESSURE_RISE shared
  event, REPORT_MISSING Leila perception, PRESSURE_RISE Leila perception, and
  REAR_EXIT Leila and Wren perceptions. They no longer assume help is absent or
  put an already evacuated guest beneath the east ceiling. Originals preserved.
- AFTERMATH priority 100 fallback omitted Leila while priority 90 already named
  her. Added Leila to the fallback list; save completion and reload confirmed.
- Recap, play-feedback, Now-heading, view-as, player-ready and dashboard-links
  guards passed. Ending/dependency/audit suites passed 155 checks; polling
  interaction fixture passed 11. PHP syntax checks passed. Commerce and version
  pinning need an isolated database; commerce stopped for missing local config,
  so neither is claimed as passed.
- Pagination is already supplied by public/assets/toinky-tables.js in main.
  The live Your Games table showed 25/50/100 rows, 123 total and page 1 of 5.
  Navigation behavior still needs verification before closing that follow-up.

## Comedy And Tomas Checkpoint 3

- A Nut To Crack: all ten CONFESSION_CHARADES result fields rewritten as short
  enacted scenes; all ten reload-verified. Existing labels and effects preserved.
- THE_HOUSE_JOINS_IN no longer announces the first Nuts after earlier awards.
  Score-neutral board prose saved and reload-verified.
- Vivian TELL_THE_TRUTH_PLAINLY now contains her actual four sentences, based
  on the existing Spain, chosen identity and Marvolo knowledge facts.
  KNOWS_THAT_SOUND now answers a recurring familiar sound without claiming the
  door is still locked or proving Marvolo is inside. Both reload-verified.
- VIVIAN priority 10 replaces the reductive label with her name; reload-verified.
  Other private-history variants remain unchanged. All originals are backed up.
- Generic recap merging now requires matching actor AND action heading. Different
  actions at the same beat retain separate headings; same-action details still
  combine. Updated behavioral recap guard and player-ready guard passed.
- New CORONATION_HOUR / TOMAS decision (studio id 124) reload-verified: active,
  PLAYER-owned, 90 seconds, three active choices and six active factor effects.
  REQUIRE_WRITTEN_ORDERS: COURT_TRUST +4, STABILITY -2. KEEP_FORCE_NEUTRAL:
  UNREST -4, LEGITIMACY -2. SERVE_COUNCIL_ORDER: STABILITY +5, COURT_TRUST -3.
  No option sets the crown, letter custody or a review judgment. Original
  conditional Tomas opportunities preserved. Fresh-version gameplay pending.
- Isolated PHP 8.4 + MariaDB 11.4 regressions passed: seeded engine baseline,
  commerce occasion lifecycle, checkout rejection handling, revision pinning,
  and multiplayer lifecycle. Initial collation mismatch resolved using CI
  database collation setup. Production database was not accessed by these tests.
- No new story revision has been submitted/published. No batch source push or
  deployment yet. Whole-batch completion and remaining register items are pending.

## Source Checkpoint 4

- Migration 141 adds only a zero-default solo clock offset. It does not edit
  story data. Real start times are preserved. Solo requests stop for new visible
  story content or an open player choice, use the per-game lock, and reject
  additional participants. One request is bounded to 120 thirty-second steps.
- Solo progression passed 16 isolated database checks including actual engine
  completion. Polling passed 12 checks including the continue-button path.
- Hosting preferences use per-user tab-scoped sessionStorage, validate against
  current offered stories/durations, and respect explicit host links and failed
  POST selection. They do not change account settings or store credentials.
- New working value standard separates the authoring audit’s managed-definition
  precedence from runtime aliased legacy property validation. A dropdown is not
  proof that a runtime transition will be accepted. Legacy repair remains open.
- Your Games pagination verified live: Next showed 26–50 of 123; 50-row choice
  showed 1–50, page 1 of 3; restored 25 rows, page 1 of 5. Existing shared script
  already supplies this feature; no pagination patch is needed.
- Throne AFTERMATH original preserved and revised personal scene reload-verified.
- Source checkpoint is for CI review, not a claim that all story gaps are closed.

## Authoritative Content And Editor Safeguards

- Source checkpoint 4 is mirrored remotely at 1b8bd4d5634f7d6aab24b0f3895bd6c9f3b2eace
  in draft PR 92. Full CI passed on both MariaDB versions. Later safeguards
  in this checkpoint require a refreshed run before merging.
- Conditional-scene results edited in Decisions can be overwritten by Opportunity
  synchronization. Vivian’s disclosure was initially saved only to that copy,
  then corrected in the authoritative Opportunity record and reload-verified.
  The Study recognition is a fixed decision, not a conditional scene. Its earlier
  save was already authoritative. Do not call both edits conditional.
- New server guards reject edits/deletion of generated prompt/choice/effect
  copies and direct writers to Conditional Opportunities. Owner-only edits are
  preserved. Targeted synchronization, choice-insertion and active-state tests pass.
  The standalone authoring-runtime guard skipped because its seeded fixture has
  no free NPC slot; do not count that skip as a pass.
- Story Values now includes a read-only comparison of managed world definitions
  with legacy aliases, property types, defaults, allowed values and active
  transitions. It performs no repairs. A direct request context renders no data;
  isolated database rendering passed. Live inspection requires this code deployment.
- Shore GROUPS_FATE missing Lit + SAFETY below 30 branch is saved with SIGNAL_FIRE
  EQ Lit and SAFETY LT 30; both exact persisted text values verified after reload.
  It does not claim a death, rescue or departure.
- Additional Shore fixed-choice prose and prompt repairs remove unconditional
  rescue/homecoming, active-spirit teaser and life-for-life assertions. Token
  result names the actual carved charm. Early appearance label no longer promises
  an identity disclosure. Fixed fields were reload-verified.
- Authoritative carved-charm route and phone opportunity prompts/results saved
  and reload-verified. Both charm choices now have active LOCATION LOCATION
  Tree Hut effects targeting SELF. The watcher frame matches its existing effects.
- New fixed scenes: COUNCIL_DIVIDES / HAND (id 125), LONG_NIGHT / GHOST (126),
  LONG_NIGHT / JEROME (127). All player-owned, 90 seconds. Soren has three choices;
  Ghost and Jerome two each. Elian CORONATION_HOUR adds two alternatives and a
  state-neutral prompt. All nine new results and their effects reload-verified.
- Jerome’s original wealth confession now contains spoken dialogue; existing
  trust and location effects preserved. Source/content rollback originals saved.
- Local player report archived and appended with a separate November 12 backend
  remediation appendix. Original twelve-game observations and ratings preserved.
- No new content revision published and no fresh production game claimed. Source
  deployment, live compatibility reconciliation and fresh-version play remain
  necessary before the whole remediation can be called complete.
