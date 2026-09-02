# Inky

Inky is Toinky's optional guided AI companion. Its first product role is a
co-writer inside Story Writer. It is not an unrestricted chat box: every action
has a named interaction contract, a bounded story-data context, and a
structured result.

## Product principles

- Inky is optional. Every authenticated user can enable or disable it from My
  Profile without administrator help.
- Inky never claims that Qwen produced a result when the configured AI service
  is unavailable.
- Story data, user permissions, authored stable keys, and the deterministic
  engine remain authoritative.
- Inky must not expose another character's private knowledge, administrator-only
  data, credentials, connection URLs, or raw AI payloads.
- Inky provides editing assistance. It does not silently change story content.

## Personal settings

Inky preferences belong to the user rather than to an administrator or story:

| Setting | Values | Default |
| --- | --- | --- |
| Enabled | On / Off | On |
| Companion name | Optional name root; Toinky adds “inky” | Empty → Inky |
| Character | Selectable character catalog | Octopus |
| Size | Small (72 px), Standard (96 px), Large (120 px) | Standard |
| Dock position | Auto, Left, Right | Auto |
| Saved anchor | Lower Left, Middle Left, Lower Right, Middle Right | Lower Left |
| Interaction sounds | On / Off | On |
| Animation | Full, Reduced, Off | Full |
| Proactive help | Helpful Suggestions, Only When Clicked | Only When Clicked |

The profile must contain a clear **Disable Inky** action. Disabling Inky removes
the companion from supported pages; My Profile remains the recovery path.

When Proactive help is set to **Helpful Suggestions**, supported pages may show
a passive nudge on the Inky character. The nudge is only an invitation to open
Inky; it does not auto-open the panel, perform a Qwen health check, or auto-run
AI actions. **Only When Clicked** suppresses that nudge.

Companion naming is playful but predictable. Entering `Bo` displays `Boinky`.
Leaving the field empty displays `Inky`, and entering a name that already ends
in `inky` does not add the suffix a second time. Names are stored separately
from character selection so a user can keep the same name when switching art.

Character selection is catalog-backed. Octopus is the default selectable
character type from approved character direction F; additional character artwork
and personalities may be added later without changing the user-preference
schema. Unavailable characters must be shown as coming soon or omitted, never
presented as selectable.

Lantern preserves approved character direction D as a non-selectable
coming-soon option. Squid preserves approved character direction G as a future
catalog option rather than becoming the default companion. Catalog labels should
describe character types, leaving personal names to the user's companion-name
setting.

## Placement and movement

Inky first appears in Story Writer.

- Desktop default: lower-left safe edge.
- Desktop artwork: 96 px by default. A greeting may briefly scale it by about
  ten percent.
- Desktop movement: dragging snaps to approved left/right edge anchors. Free
  placement is intentionally unsupported because it can cover controls or
  become unreachable after a viewport change.
- Mobile artwork: 64–72 px. Inky becomes a left/right edge tab and its panel
  behaves like a bottom sheet; mobile does not use free dragging.
- The panel opens inward from the selected edge and collapses when editing
  resumes.

The transparent shell uses no pointer input. Only the visible character,
controls, and open panel may receive clicks. Inky yields to focused fields,
dialogs, menus, sticky action bars, validation messages, and other essential
controls.

## AI availability

When Inky opens, Toinky performs an authenticated, server-side health check
against the configured Ollama endpoint. The browser never receives the endpoint
or its credentials.

- Online: the interface identifies the configured model and may enable guided
  actions whose contracts are implemented.
- Offline: the interface presents a friendly unavailable state and keeps
  AI-dependent actions disabled.
- The deterministic story reasoner is not presented as Qwen and is not a
  substitute for an Inky co-writer response.
- Completed guided-action results show the responding companion name, model,
  and automated story-package error/warning counts so authors can keep AI advice
  separate from deterministic validation evidence.

## Initial interaction contracts

The guided actions are:

1. Review Plot
2. What's Next?
3. Quality Check My Work
4. Find Dependency Problems
5. Check Character Knowledge
6. Suggest Story Values
7. Review This Opportunity
8. Prepare Focused Tests

`Review Plot` is the first implemented vertical slice. It sends the authenticated
writer's current authored review package to Qwen and requires compact JSON with
separate observed evidence, inference, and stable-key recommendations.

`What's Next?` is the second implemented vertical slice. It sends the same
authenticated, bounded review package and requires a short prioritized task list
for the author. Every step must cite a stable key, include a priority, explain
whether its reason is observed evidence or inference, remain advice-only, and
mark chronology-sensitive or structural-order changes as **MANUAL STRUCTURAL
REVIEW**.

`Quality Check My Work` is the third implemented vertical slice. It sends the
same authenticated, bounded review package and returns scored checks for
immersion, viewpoint continuity, character agency, branch causality, dependency
coverage, pacing, replayability, and mobile readability. Each check must cite a
stable key, distinguish observed evidence from inference, include one specific
authoring improvement, and remain advice-only.

`Find Dependency Problems` is the fourth implemented vertical slice. It sends
the same authenticated, bounded review package and returns dependency issues
such as missing active choices, missing effects, missing conditions, inactive
references, undefined managed story values, weak choice-to-outcome links,
unreachable endings, and player-visible engine leakage. Every problem must cite
a stable key, include severity, distinguish observed evidence from inference,
recommend an authored-data fix, and remain advice-only.

`Check Character Knowledge` is the fifth implemented vertical slice. It sends
the same authenticated, bounded review package and checks whether characters
have enough non-privileged information for meaningful choices, whether prompts
or results expose private knowledge, whether private knowledge is authored
clearly, and whether choices align with goals, fears, location, and known facts.
Every issue must cite a character role code and stable key, include severity,
distinguish observed evidence from inference, recommend an authored-data or
prose fix, and remain advice-only.

`Suggest Story Values` is the sixth implemented vertical slice. It sends the
same authenticated, bounded review package and suggests managed Story Framework
values only: factors, stats, locations, knowledge, relationships, world state,
goals, and fears. Every suggestion must cite a stable key, include scope, stable
target code, label, value type, optional preset values, reason, and example use.
It remains advice-only and does not change schema or PHP.

`Review This Opportunity` is the seventh implemented vertical slice. It sends
the same authenticated, bounded review package and reviews authored decision
opportunities for prompt clarity, player agency, option contrast, visible
consequences, AI rationale guidance, condition/effect fit, character knowledge,
and mobile readability. Every item must cite an opportunity code and stable key,
include severity, distinguish observed evidence from inference, recommend an
authored-data or prose improvement, and may include a focused validation idea.
When the author is editing an existing decision or conditional opportunity,
Story Writer sends that stable focus code so Inky can prioritize the selected
record while still using only the bounded story package.

`Prepare Focused Tests` is the eighth implemented vertical slice. It sends the
same authenticated, bounded review package and returns an advice-only validation
plan using existing Toinky validation and Game2 Simulation Lab workflows. Each
test must cite a stable key, name a validation, focused opportunity, broad
simulation, or manual smoke-test type, include a focus code, recommended run
count, reason, and expected passing evidence. Seeded focused-opportunity tests
must be described as proof of local branch behavior, not proof of organic
reachability from story start.

Future guided-action buttons stay visibly marked as forthcoming and disabled
until their individual contracts are implemented.

Each contract must define:

- the exact story entities and fields it may read;
- required stable keys in the result;
- an explicit observed-evidence versus inference distinction where applicable;
- the expected structured response schema;
- whether the result is advice-only or eligible for a later reviewed import;
- timeout, error, and offline behavior.

## Delivery sequence

1. Persistent user preferences, companion identity, character catalog, and profile controls.
2. Safe responsive shell, size, docking, movement, sound, and reduced motion.
3. Server-side Qwen/Ollama availability check.
4. Per-action context builders and structured response contracts.
5. Action-specific result rendering and reviewed application workflows.
6. Final animated artwork and interaction polish.

## Validation

- All profile settings save and reload for ordinary users, writers, and admins.
- Disable Inky removes it from Story Writer and Profile can restore it.
- Dragging snaps to a safe anchor and survives refresh.
- Mobile renders an edge tab and bottom sheet without covering active fields.
- The shell does not intercept clicks outside visible Inky controls.
- Reduced and Off animation settings are respected.
- AI actions remain disabled when the health check fails.
- No endpoint, access token, basic-auth value, or raw private story state appears
  in browser responses.
- Existing PHP validation and Game2 Simulation Lab checks continue to pass.
