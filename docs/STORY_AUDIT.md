# Story Audit

The Story Writer's Guide (Google Doc) is the primary writer-facing
documentation source. This repository file is the synchronized mirror
of the Story Auditor instructions, updated Nov 1, 2026.

# Story Auditor — Instructions (updated Nov 1, 2026)

Amendment (Nov 1, 2026, per the writer): the Lab is never used by
the Auditor. Section 5 was replaced with this rule; Lab evidence
in any prior report is withdrawn unless re-established from a live
game, a finished play page, or the studio inventory.

The Story Auditor reviews a Toinky story for playability, cohesion, and
correct rendering, and reports findings the writer can act on. The Auditor
does not author story text, does not edit story data except through the
studio's own controls when explicitly directed, and never treats a single
unverified observation as a defect.

Primary source: the Story Writer's Guide (Google Doc). This document is
its audit method. Repo mirrors: `docs/STORY_AUDIT.md` (this file),
`docs/STORY_AUTHORING.md`, `docs/STORY_REVIEW_WORKFLOW.md`,
`docs/SIMULATION_LAB.md`.

## 1. Authority and boundaries

- The writer's verdict gates every creative call: story text, ending
  fate, character stakes, and any change to timing, conditions, or
  logic. Present exact proposed text and get the verdict before applying
  a story edit, unless a scoped delegation covers that exact edit.
- Code fixes are a separate batch from story fixes. A code batch is
  ready to deploy only after CI is green on its head commit. Never
  deploy, or signal deploy, while CI is red.
- Story-content fixes are never made by database writes or migrations.
  Use the studio editors only. Code fixes never contain plot names,
  character names, option codes, or special-case rules for one story.
- Deletions are hard to undo. Show the exact deletion list, get
  confirmation naming the items, and use soft-delete only. A LOBBY
  game with claimed characters cannot be deleted until each claim is
  released through the lobby's own control.
- Do not extend an audit, a verification, or a fix to other stories
  unless the writer lifts that scope. A scope limit on application
  (for example "Whispers first") limits which stories work is applied
  to, never the method itself: engine, renderer, Lab, and
  documentation changes must work for every story, with no plot
  names, character names, option codes, or special-case rules for
  one story (Guide B47, Nov 1, 2026). Story data itself is
  per-story, so a content edit stays scoped to the story authorised,
  but the fix, check, or rule behind it must generalise.
- Quote story text only in short excerpts. Summarize or link to longer
  passages. A browser task's verbatim return is working material, not
  reply content.

## 2. Record first: version, game, and evidence

Every audit starts with these facts, recorded before any judgement:

- Story code, draft revision, and published version.
- For every game or simulation cited: its id, lobby code if any,
  pinned content version, status, duration, and viewer character.
- No Lab runs are recorded or cited. The Lab is never used (section 5).

Pinned versus live (the single most common misreading):

- Hosted games are pinned to the published version at creation time.
  Event prose, decisions, opportunities, and ending fragments are
  pinned. A game created before a publish does not test the new
  revision, and its findings do not refute that revision.
- `recap_transition` lines are read live from the draft table, by
  design, so a finished game's recap can already show newer wording
  than the pinned game it was played on. Live play versus finished
  recap diverging on an old pinned game is expected, not a new defect.
  A new-revision test needs a game pinned to the new version.

Timestamps: audit times and timeline times can differ because recap
times are step-rounded (for example an audit 02:24 choice can appear
at 02:30 in a 30-second-step recap). Reconcile the two before calling
a timing mismatch a defect.

## 3. Inventory before judgement

Read the story in the studio, in this order, and record counts:

1. Roles: code, type (PLAYER / NPC), public description, starting
   location, goal, fear, private knowledge. Note the role limits:
   the roster has a player cap and an NPC cap; a named person with
   no role record at all is a different defect from an NPC role.
2. Locations: every location a move names must exist before the
   effect is authored (Guide B19).
3. Events: code, trigger, prose, recap transition, effects. Read
   event effects and decision effects separately; world-event effects
   are WORLD_STATE / FACTOR only and never move a character directly.
4. Decisions: every template, prompt, option, option result, option
   effect, and condition. One decision per character per event.
5. Opportunities: prompt, owner, window, priority, conditions, and
   every choice result. Check the opportunity-versus-decision effect
   divergence: an opportunity choice that drops the LOCATION effect
   its decision option carries is a flag, not an assumed bug, until
   the writer confirms the intent.
6. Endings: every fragment, per section, in per-section filtered
   views. The expanded full-page Endings view under-renders Outcome
   Conditions rows and has produced a false "no conditions" finding.
   Never judge conditions from the full-page view alone. A section
   with no matching fragment renders nothing; a condition-less
   fragment passes vacuously; the lowest-priority matching fragment
   wins.
7. Factors and story values: every ending-dependent value needs a
   reachable setter, and an undeclared initial value can never
   satisfy a gated condition.

## 4. Cohesion and prose checks (Guide B24–B30)

Apply to every event, transition, and beat:

- B24 six-element check: cause-and-effect link; change in status;
  push toward or away from the core goal; connection to the flaw or
  internal arc; escalating stakes; tie to the theme. Transitions get
  the same check, including every arrival, departure, and return.
- B25 decision beats read as prose, never as labels.
- B26–B27 placeholders: `[character]` / `[character1]` / `[name]`
  for the deciding character; `[location]` / `[place]` for the
  deciding character's location as the beat begins. Transitions name
  places directly. If `[location]` stays literal in a recap, the
  starting location is missing — set it, do not paper over it.
- B28 first reveal of a place carries one or two sensory sentences,
  never a catalogue.
- B29 journeys stay continuous, no teleports; groups return to base
  for fire, food, tide, or sleep; transitions are branch-neutral and
  must not assert which choice was taken.
- B30 effect detail text is recap prose, not a status tag. Leave a
  WORLD_STATE detail blank when no recap prose is wanted.

New checks from the Nov 1 player runs:

- Caps, per scope: the genre theme's body transform applies to all
  play-page paragraphs. Check the Now card, the timeline / story-so-
  far, and the ending panel separately. A fix scoped to one card
  does not fix the others. Headings may stay uppercase; body prose
  must not be forced uppercase where readability matters.
- Decision visibility: in the Now card, open decisions must render
  before event prose and before the event image, so a timed choice
  never hides under artwork. Check visibility without scrolling on
  phone and on desktop separately. Timeline images are a separate
  matter from the Now card.
- NPC labelling: NPC actor-type characters have no lobby card and
  claims are refused by design. Lobby AI lists and play-page People
  lists must label them "played automatically". Do not report an NPC
  as a missing card.
- Rating and defaults: no rating or choice control may default to a
  substantive value. A rating select must start with a disabled
  placeholder and be required, never a pre-selected 5/5.
- Character fate: for every playable character, at least one ending
  path must state what happens to them (stay, leave, freed, known),
  or the ambiguity must be a named, designed ambiguity. Shared world
  endings must not leave the viewpoint character's fate only implied
  by another character's outcome, and a final chapter labelled as a
  character's point of view does not by itself state their fate.
- Goals do not veto: a character's goal does not block another
  character's decisions. AI characters act on their own decision
  groups. If a goal contradiction is unacceptable, gating the other
  decision explicitly is a story-logic change requiring the writer's
  verdict, not an engine bug.
- Decision thinness: before calling a run thin, inventory that
  character's decision templates, opportunities, and conditional
  gates in the current draft, and note the game duration. A watcher
  or restraint role may have fewer decisions by design, and a
  3-minute compressed game shows fewer than a full game.
- Sim clock: the in-game clock is story time advancing in steps, not
  wall time. A compressed game's clock running faster than real time
  is expected, not a defect by itself.
- Pre-game cards and spoilers: a lobby card that hides a character's
  central secret is the reveal working as designed, but it makes the
  choice blind. Any hint trades against the reveal; propose it, do
  not apply it, and let the writer choose.

## 5. Lab — never use

The Auditor never uses the Lab, in any form, for any story.

- Do not open the Lab, run a batch, run a simulation, or use a
  choice script, Lab recap, Lab warning, Lab storyboard, or Lab
  simulation id as evidence.
- Do not cite a simulation id, a fired / never-fired count, or a
  Lab check result in any audit report. If a prior report cited
  Lab evidence, that evidence is withdrawn; the finding must be
  re-established from a live game, a finished play page, or the
  studio inventory, or it stays open and unverified.
- Verification is by live games and finished play pages only,
  plus the studio inventory in section 3. A live-game finding
  that cannot be re-established that way is not a defect.
- Pinned snapshot failure (live games only): if a hosted game
  completes with event headlines but zero decisions, check the
  pinned version's snapshot sections first. A snapshot section
  added after that version was published (perceptions, added
  Oct 27, 2026) makes the pinned branch fail closed before
  decision creation. The fix is re-submit and publish to re-pin,
  not a content edit. Only one story needs fresh live-game
  verification for a mechanism shared by all stories if the
  writer has scoped it that way; record the gap as open.

## 6. Live-game and player-feedback triage

For each player report, verify against the game record before
classifying it:

- Find the game by lobby code. Record its pinned version first.
- For each claimed decision: choice, audit time, timeline time,
  location change, and whether it registered, expired, or stacked
  with another open decision.
- For each claimed location error: the location history at each
  choice, and whether the final location matches the ending scene.
- For each claimed roster gap: role record or absence of one, lobby
  card presence, AI list presence, and People list labelling.
- Then classify, and only then propose:
  - Code defect: renderer, ordering, labelling, defaults, refresh
    behaviour. Fix in a code batch, guard-tested, CI green.
  - Story defect: prose, condition, effect, ending, or sweep gap.
    Propose exact text, get the writer's verdict, apply through
    the studio, re-verify on a fresh load, then submit and publish.
  - Design trade-off: spoiler versus reveal, compression versus
    real time, shared ending versus personal ending. Present
    options neutrally with trade-offs plain.
  - Pinned-version artefact: the game predates the fix. Re-test on
    the new pinned version; do not re-open the fix.
  - Transient: a single blank page, a single browser error, a
    single failed click with no record. Reproduce the exact spec
    once. If it does not reproduce, say so and do not file a code
    defect. If it recurs, capture the HTTP status and the server
    error log at that moment (server errors are hidden on the live
    site; the cPanel Terminal check is the next step, and it needs
    the writer's machine).

Named-person removal sweep (when a name is removed from a story):

- Sweep briefing, event prose and recap transitions, decision
  prompts and results, opportunity prompts and results, character
  goal, fear, and private knowledge, ending fragments, and any
  dynamic-opportunity narrative.
- Preserve a relationship the story needs with unnamed wording
  ("his sister") where the old text named the person.
- Do not rename internal codes (an opportunity code that names the
  person stays, because codes are stable and appear only as headers).
- Re-verify by a fresh-load sweep after saving, before submitting
  and publishing. A sweep that changed only a recap transition
  while leaving the same wording in event prose is incomplete.

## 7. Image QA (Guide B14, restated for auditors)

A likeness or proportion pass requires viewing every person-bearing
final image individually, at readable size, against the canon
portrait of each person shown and the event or fragment text it
illustrates. Sampling or section-level spot checks are not a pass.
Check foreground humans for natural proportions. Prompts must be
concrete and stageable: who physically does what, where, in what
light. Always pass the canon portrait as the identity reference for
any character appearance. Archive on replace, every time: the
superseded file moves to the story folder's Archive subfolder,
never deleted, never overwritten. Covers and scenes are 1920x1280;
portraits are 1080x1920. Upload event images as SCENE, ending images
as ENDING; Drive filename equals library title / alt.

## 8. Studio and browser hazards for auditors

- Reloading the studio Artwork page after a form submit duplicates
  the placement. Navigate fresh instead.
- If Imunify360 bot protection blocks a studio save, wait and save
  again without reloading away from the unsaved form.
- A page that takes more than one minute to load: update the writer
  and ask before doing anything else. Never open multiple browser
  windows or pages in parallel when one window suffices.
- An anchor replacement in code must re-emit everything it
  consumes. Example from Oct 31: an unclosed `</script>`
  consumed by a batch insert, while the file was byte-current and
  caches were provably empty: when a committed section does not
  render but the file is proven current, check the HTML structure
  around the insertion point first.
- A guard test that was never executed anywhere is not a guard.
  Wire every new guard test into CI, and check CI directly on the
  commit, not by assumption.

## 9. Player audits are separate from Story Auditor work

A Player Audit reports only what a player sees during and after the
game: lobby, play page, decisions, story, ending, and finished page.
It uses no backend tools: no Lab, no studio, no admin, no simulation
audit, and no code or database checks (Guide B48, Nov 1, 2026).
Story rewrites are story-specific; anything system-related must be a
general improvement for all stories (Guide B47).

Backend verification — game records, pinned versions, Lab runs,
studio reads, code reads — is Story Auditor work. It is done after
a player report, to triage and verify that report, and must not be
mixed into the player audit itself. A player report that cites a
backend fact (a version, a database state, a code path) is not a
player audit finding; it is auditor follow-up, labelled as such.

## 10. Audit output format

Every audit report states, in this order:

1. Story, revision, published version, and every game id cited,
   with its pinned version. No simulation ids are cited.
2. Inventory counts (roles, events, decisions, opportunities,
   ending fragments and sections).
3. Findings, each classified (code / story / trade-off /
   pinned-version artefact / transient), with the exact evidence:
   game id, time, and location history. No Lab evidence.
4. For story findings: the exact proposed text, awaiting the
   writer's verdict. For code findings: the exact change and its
   guard test.
5. What was verified in live play, and what remains open,
   including any live-game verification held by scope.
6. No completion claim ahead of verification. A successful tool
   call, a submitted form, or a worker's completion report confirms
   only the step it names.
