# Inky Action Rules

The shared Google Handoff and Story Writer's Guide are the primary
documentation sources for this behavior. This repository file is their
synchronized implementation mirror. The current behavior is derived from
`public/assets/toinky-inky.js`,
`public/assets/toinky-inky-guide.js`, `app/InkyGuideService.php`, and
`app/InkyCowriterService.php`. Update the shared Google documents first when
those contracts change, then synchronize this file.

## Shared rules

- The writer must be authenticated and allowed to edit the selected story.
- The browser first checks Inky availability. Offline or unavailable AI keeps
  AI-dependent actions disabled.
- A request uses the current story title, companion name, and bounded authored
  context. Credentials, provider endpoints, raw private payloads, and data
  outside the selected story are not sent to the browser.
- All action buttons are disabled while an AI request is in flight. This
  prevents overlapping requests and prevents a second action from changing
  the context of the first one.
- Cancel aborts the active browser request. Cancellation is non-mutating; a
  response that has not completed cannot save a proposal.
- Errors show a recoverable message and do not partially write story data.
- AI results are suggestions or review advice. The deterministic engine,
  database-authored story data, permissions, and server validation remain
  authoritative.

## Draft with Inky

The Continue Draft composer is a resumable, proposal-based workflow. The
writer edits and accepts each proposal; opening the composer or receiving an AI
response never adds story content by itself.

### Draft state

- Continue Draft is for an existing draft. Its guided answers, current brief,
  accepted proposals, and phase are loaded from the saved guide state.
- A refresh of a continued draft resumes the saved state through the draft URL
  marker and database progress record. Accepted proposals are not browser-only
  state.
- The composer uses the current live metadata and current field values when it
  creates a request. Older accepted character data is not a source for a new
  character suggestion.

### Proposal controls

- **Next Suggestion** advances through the fixed guided proposal sequence. It
  skips accepted items and items already cycled in the current session. It
  stops with a clear exhausted-state message when no unused guided item
  remains.
- **Suggest A Character** creates one new character proposal at a time. It is
  enabled only when Story Framework has an active location and an available
  player or AI-character slot. The server repeats those limits.
- Character suggestions scan the full bounded draft package: story metadata,
  guided answers, brief, accepted guide proposals, locations, story values,
  characters, world events, decisions, opportunities, and endings. A person
  explicitly named in that authored data is preferred when eligible; otherwise
  Inky may invent a fitting character.
- A suggested character must use an available Story Framework role type, an
  active location label, and a new role code/name/profile. Existing characters
  and previously shown unaccepted suggestions are exclusion constraints, not
  prose to copy. Retries must return a different eligible character.
- **Suggest a Story Value** requests the next unused Story Framework value slot,
  up to the current supported limit of sixteen. A rejected or pending value is
  included as rejection context so the same proposal is not repeated. If no
  new slot is available, the writer is told to edit an accepted value instead.
- Story Value suggestions are limited to the managed scopes FACTOR, STAT,
  LOCATION, KNOWLEDGE, RELATIONSHIP, WORLD_STATE, GOAL, and FEAR. Value types
  are NUMERIC, PRESET, or TEXT, with allowed preset values only where valid.

### Field suggestions

- Each proposal field has an unchecked **Request Suggestion** toggle. Only
  selected fields are eligible for a field-level revision.
- Field-level requests read the current value in the live editable control.
  They must not reload an older saved character or accepted proposal as the
  revision source.
- **Make it Longer** and **Make It More Creative** apply only to the selected
  fields and preserve the other live fields. The returned text remains editable.

### Approval and save

- **Accept and Save** reads the complete edited proposal, including changes
  made after the AI response, and sends it to `save_proposal`.
- The generic Story Authoring service validates the complete object, the story
  slot, stable keys, active references, and framework limits before saving.
- A successful save updates the accepted guide state and persists progress.
  AI never writes a character, story value, or other authored entity directly.
- Copy commands are presentation-only. **Copy Story Draft** includes the
  current draft metadata, story brief, latest draft additions, guided answers,
  accepted proposals, and current editable proposal in a structured format;
  copying does not save anything.

## What can I do for you?

This is the primary Inky assistant menu. Its current Story Writer actions are:

- **Write New Story with [companion name]** opens the five-question guided
  authoring flow. It creates no story records until the writer explicitly
  creates the draft.
- **Continue "[Story Title]" Draft with [companion name]** opens saved guide
  progress for the current story and exposes the three Draft proposal controls
  above.
- **Improve Story** contains the read-only review actions: Review Plot, Review
  Characters, Review Opportunities, Find Dependency Problems, Quality Check,
  and Prepare Focus Tests.

### Menu availability

- Every action requires an implemented contract and a healthy Inky connection.
- Review Plot requires a story package. Review Characters requires authored
  characters. Review Opportunities requires authored opportunities.
- Find Dependency Problems, Quality Check, and Prepare Focus Tests do not need
  those specific entity prerequisites, but still require an editable story
  context and an available AI connection.
- When any menu action or Draft proposal is running, every menu action is
  disabled, including Suggest A Character and Suggest a Story Value. New menu
  items must use the same shared busy and cancel controls.
- The active status header changes immediately to the selected action and
  current story title, such as `[companion name] is reviewing the [Story Title]
  story data...`.

### Review result rules

- Each action receives one bounded review package containing current story
  metadata, scenario briefing, guided state where applicable, locations,
  characters, story values, world events, decisions, options, effects,
  opportunities and conditions, endings, and automated audit findings.
- Results are advice-only. They do not rewrite, reorder, activate, delete, or
  save story entities.
- Each result keeps a visible action-specific header so the writer can identify
  which review produced the content:
  - **Plot Review** returns observed evidence, inferences, and stable-key
    recommendations.
  - **Review Characters** returns character knowledge and agency issues keyed
    to role codes, with evidence and authored-data or prose fixes.
  - **Review Opportunities** reviews all authored opportunities for clarity,
    agency, option contrast, consequences, conditions/effects, knowledge, and
    readability.
  - **Dependency Problems** returns severity, stable key, evidence, fix, and a
    manual structural review flag where needed.
  - **Quality Check** returns scored checks for immersion, viewpoint
    continuity, character agency, branch causality, dependency coverage,
    pacing, replayability, and mobile readability.
  - **Focused Tests** returns an advice-only validation plan using existing
    validation and Simulation Lab workflows. A seeded focused test proves a
    local branch from a manufactured state, not organic reachability.
- Structured output must distinguish observed evidence from inference and cite
  stable keys. A malformed, incomplete, timed-out, or cancelled response is
  shown as an error and cannot mutate authored data.

## Current draft-context update

- Continue Draft with Inky renders active Story Framework values together with
  accepted guided additions, so values authored directly in Story Writer are
  not hidden from the draft composer.
- The Continue Draft controls include **Suggest a Location**, **Suggest an
  Event**, and **Suggest a Decision**. These controls use the live bounded
  draft package, including active values, locations, characters, events, and
  decisions. A new location or event is saved only after **Accept and Save**;
  a new decision binds to an existing event and character and saves its two
  options as one reviewed proposal.
- Entity proposal keys advance from the existing records and the current
  browser cycle. A pending or rejected suggestion is excluded from the next
  request, and a full range reports exhaustion instead of repeating the same
  record. All proposal buttons remain disabled while an AI request is active.
- Published-story Improve Story reviews remain read-only. They use the
  readable current story package and a bounded audit package, so review size
  cannot prevent feedback when a published story has a large history.

## Change rule

Any future Draft proposal or assistant-menu action must define its trigger,
prerequisites, bounded context, response schema, validation boundary, busy and
cancel behavior, error behavior, and save boundary before its button is
enabled. Its result must follow the same visible-header and advice-or-explicit-
approval format used by the current actions.
