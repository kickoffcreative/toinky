# Writing a Toinky Story

Toinky stories are authored as data. The PHP engine is shared by every story and must never contain plot names, character names, option codes, or special-case rules for one story.

## Ownership and roles

Every story has one `author_user_id`.

- **Administrator** is a platform role. Administrators manage users, simulations, stories, and writer access.
- **Story Writer** is a platform role. Writers may edit stories assigned to them.
- **Game Host** is a game-specific role. The user who creates a lobby is its host.
- **Player** is a game-specific role. Any user who joins a lobby is a player, including the host.

A person can therefore be an Administrator and Story Writer while also hosting and playing games. Game Host and Player are relationships to a particular game, not permanent permissions.

Every new account begins with the ability to host a game and participate in one. A user who wants to author stories requests **Story Writer** access from the player portal. An administrator reviews that request in **Admin → Writer Requests**.

## Guided Story Writer workflow

The Story Writer workspace is intentionally ordered by dependency:

1. **Overview** — premise, setting, genre, player capacity, and shared scenario.
2. **Characters** — public identity, private stakes, pronouns, starting location, and initial goal.
3. **Timeline** — fixed world events in chronological order.
4. **Decisions** — the character prompted by each event, legal options, narrative results, and ordered state/factor/location/knowledge effects.
5. **Story Map** — a readable event → decision → option → effect graph, plus state-driven opportunities, their AND conditions, choices, and effects.
6. **Endings** — ordered resolution fragments and the state conditions that select them.
7. **Artwork** — optional writer-uploaded images aligned to characters, openings, scenes, turning points, outcomes, and endings.
8. **Administrator validation** — only administrators can open **Validate & Submit**, run blocking structural checks, generate the reviewer-ready package, and freeze a numbered revision for review.

When the draft is ready, the writer asks an administrator to validate it. The administrator runs **Validate & Submit**, resolves any blocking findings with the writer, and creates an immutable numbered revision. An administrator reviews that exact snapshot, then requests changes, approves, publishes, or archives it. Later edits do not silently alter the submitted revision.

### Inky-assisted character authoring

Inky character suggestions follow the same authoring boundary. **Suggest A
Character** is a recommendation only. The writer must open it with **Add and
Continue Story with [Inky name]**, edit the proposal, and select **Accept and
Save** before a character is added to the draft. The direct Characters link is
not a save path for Inky suggestions.

When requesting another suggestion, the current values in the editable
proposal are the source of truth. The AI may revise only the fields selected by
the writer, while unselected fields remain unchanged. Previously accepted
character proposals must not be used as a substitute for the live proposal.

## Story code and deletion safeguards

The **Stable Story Code** is the durable namespace for a story package. It is
editable in **Story Framework → Story Overview** only while the story is empty
and has never been played. The server checks all database tables that reference
the story, including authored records, revisions, audit records, and
simulations. Empty submissions preserve the current code, and a code already
assigned to another story cannot be saved.

The same rule controls the **Delete Story** action. Story Writer shows the
action only for a story with no locations, characters, events, decisions,
story values, endings, artwork, revisions, audits, or play history. The delete
request repeats the database check and refuses the operation with the blocking
record types when anything has been added. Deletion is permanent, so a writer
should use it only to remove an accidental empty draft. Deactivate or revise a
story after authoring has begun; do not delete its history to make the action
available.

The generic implementation lives in `StoryAuthoringService` and is shared by
every story. Do not add story-specific exceptions or hard-coded plot names to
support this workflow.

## Genre themes

The story genre selects a reusable database theme. Administrators configure its colors, heading and body font stacks, and optional background, story-divider, and card-texture images in **Admin → Theme Manager**. Gameplay reads this configuration generically; a new story gains the same presentation simply by choosing that genre. Empty image fields use the clean default interface.

## Invitations

Hosts can invite players by email from an open lobby. The message is sent as `newsletter@sixpillarsmodel.com` by default and includes both a registration link and the game code. Lobby capacity and invitation validity are rechecked when a new user accepts. The live lobby continues to reserve selected characters in real time, and any unclaimed character becomes autonomous when the host starts.

## Decisions a writer must make

Before entering data, decide:

1. The premise, genre, setting, tone, timeline duration, and player promise.
2. The cast, including each character's public role, private knowledge, starting location, current goal, fear, and pronouns. Pronouns are deliberately limited to `HE`, `SHE`, or `IT`.
3. The world-state entities and properties that make outcomes authoritative.
4. The factors that represent broad pressure, trust, safety, unrest, or similar conditions.
5. The global events and which characters perceive each event.
6. Every decision window, its legal choices, its no-action consequence, and its delivery timing.
7. Every option effect, including state transitions, knowledge, location, goals, fears, and factor changes.
8. Dynamic opportunities and their database conditions.
9. Causal dependencies: what must already be true, who must participate, and which transitions are legal.
10. Outcome dimensions and integrity rules.
11. Resolution fragments and a 300–500 word epilogue.
12. Optional artwork and where it should appear.
13. The simulation strategies and story branches that prove the story works.

## Optional writer-uploaded artwork

Open **Story Writer** from the portal. Choose an owned story and upload JPEG, PNG, or WebP files up to 8 MB.

Available placements are:

- Character
- Opening Scene
- Story Scene
- Important Turning Point
- Specific Outcome
- Ending / Closing
- Other

An image may optionally be tied to a character, event, story second, or a world-state condition. Outcome images use a state key, operator, and value—for example `GUEST_STATUS EQ EVACUATED`.

Alternative text is required. Captions are optional. Only active uploaded images appear to players. Missing images never prevent a story from being submitted, simulated, hosted, or played. Toinky does not generate story images.

Uploaded binary files live in `public/uploads/story-media/`; their ownership, placement, conditions, captions, and accessibility text live in `plot_media_assets`.

## Player-immersion requirements

A player must always understand:

- the scenario;
- who they are;
- who the other named characters are;
- their current location and current goal;
- what happened publicly;
- what they personally experienced;
- what choices were available, including missed choices;
- who helped, opposed, witnessed, or was affected;
- why the ending followed from earlier actions.

Player prose uses **you/your** for the current player and a name followed by **he/she/it** for other characters. Do not expose factor totals, raw state keys, controller labels, stat deltas, transition arrows, or engine terminology in player view.

The ending should read like the closing pages of a story, not a report. Aim for 300–500 words across the selected resolution fragments. Include an immediate closing image, the player's fate, consequences for other named characters, the wider aftermath, and one lingering question or unexplored path that encourages replay.

## Validation order for every future story

Run checks in this order. Do not use polished prose to hide a broken state model.

1. **Schema and ownership** — apply migrations; confirm the story has an author, scenario, cast metadata, and valid pronouns.
2. **State integrity** — test legal transitions, prerequisites, helper requirements, custody, exclusivity, and every configured integrity rule.
3. **Decision coverage** — execute every option and no-action path; confirm later opportunities react to actual prior choices.
4. **Deterministic batch** — run weighted/manual batches across each supported focus and control mode; require zero integrity warnings.
5. **Autonomous behavior** — run all-autonomous samples and audit legal choices, character knowledge, reactions, tradeoffs, confidence, and continuity.
6. **Player continuity** — play once as every selectable character and verify that each perspective is comprehensible without privileged knowledge.
7. **Player immersion** — review POV, pronouns, named participation, natural time, missed-choice prose, deduplication, phase order, and the epilogue.
8. **Responsive interface** — test phone, tablet, and desktop; open decisions must remain visible and usable on mobile.
9. **Optional media** — confirm every supplied image appears only in its intended scene/outcome and has meaningful alternative text. Confirm the story still works with no images.
10. **Replayability** — compare batches and live playthroughs for meaningful outcome diversity without contradictions.
11. **Regression** — rerun the validation suite for existing stories to prove no engine change became plot-specific.
12. **Release evidence** — preserve audit output, migration status, commit/PR, deployment result, and a final live smoke test.

Administrators can run the same automated authoring audit from the server with:

```bash
/opt/cpanel/ea-php84/root/usr/bin/php tools/story_immersion_audit.php STORY_CODE
```

Then run the simulation and AI audit tools documented in [SIMULATION_LAB.md](SIMULATION_LAB.md).

## Current ownership

Migration 063 assigns **The Empty Throne** and **Concealed Failure** to `goldwynlau@gmail.com` when that account exists. New stories must set their own writer's `author_user_id`; they must not inherit the administrator automatically.
