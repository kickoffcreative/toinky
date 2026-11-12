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
| H1 | Zero-credit hosting action fails too late | Code implemented: preflight occasion message and disabled hosting; runtime verification pending |
| H2 | Failed hosting loses story/duration selection | Code preserves failed-POST selection and compatible duration on story switch; runtime verification pending |
| D1 | Choice acceptance/failure needs clear feedback | Investigate submission and polling behavior; no inferred click-failure cause |
| D2 | Single-option prompts presented as meaningful alternatives | Inventory per role and gate before changing |
| P1 | Long solo idle periods | Design and implement optional reading-paced solo progression without affecting multiplayer |
| P2 | Tomas's oath conflict never tested after first choice | Inventory current story; add a consequential late character beat |
| P3 | Sparse Elian, Soren, Jerome, Ghost paths | Inventory current gates and add meaningful participation where justified |
| B1 | Rescue completed, then guest again needs carrying | Reconcile actual state, event prompts, and recap chronology |
| B2 | Second helper repeatedly requested after commitment | Verify helper state versus authored prompts |
| B3 | Leila omitted from aftermath | Verify all relevant variants; handoff reports partial fix |
| S1 | Stranded ending also says leaving/coming home | Verify all selected fragments; make departure claims path-consistent |
| S2 | Ghost offered bargain; ending credits refusal | Verify exact result/fragment conditions and repair attribution |
| S3 | Bargain cost versus freedom at dawn unclear | Clarify the same price in prompt, result, and aftermath |
| S4 | Bernard exchange lacks an explicit outcome | Reconcile trade path and family fate |
| S5 | Mother revealed early and again at dawn | Preserve hints while reserving first identification for intended meeting |
| S6 | Exorcised cave reappears as active in teaser | Condition teaser or write a compatible closing invitation |
| S7 | Known truth and remaining mystery contradict | Qualify knowledge per path |
| S8 | Departure prose and displayed locations disagree | Verify final-state conditions and group movement |
| S9 | Storm repetition, missing charm/footage transitions | Edit exact current passages after inventory |
| N1 | Comedy action labels/results combine or contradict | Trace authored results versus recap merging; fix each confirmed mismatch |
| N2 | Study recognition follows an already-open door | Reconcile timing and prompt wording |
| N3 | First Nuts announcement after earlier awards | Adjust announcement to state-aware or nonexclusive wording |
| W1 | Confessions, mimes, reunion summarized rather than enacted | Add dialogue and character reactions while preserving branch meaning |
| W2 | Administrative/engine language interrupts prose | Separate or revise mechanics-heavy story text |
| W3 | Vivian's ending uses a reductive label | Review and revise in context |
| T1 | Throne status report lacks personal aftermath | Add role-specific cost/payoff while preserving legitimate unresolved politics |
| V1 | Managed and legacy state vocabularies disagree | Diagnostics delivered in PR 91; underlying data repair pending backup and inventory |
| V2 | Ending compatibility across sections | Diagnostics delivered in PR 89; actual story outcomes still need repair |
| V3 | Future authoring standard | Document value purpose, scope, domain, initial state, transitions, and consequences |
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
