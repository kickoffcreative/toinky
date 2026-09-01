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
| Size | Small (72 px), Standard (96 px), Large (120 px) | Standard |
| Dock position | Auto, Left, Right | Auto |
| Saved anchor | Lower Left, Middle Left, Lower Right, Middle Right | Lower Left |
| Interaction sounds | On / Off | On |
| Animation | Full, Reduced, Off | Full |
| Proactive help | Helpful Suggestions, Only When Clicked | Only When Clicked |

The profile must contain a clear **Disable Inky** action. Disabling Inky removes
the companion from supported pages; My Profile remains the recovery path.

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
separate observed evidence, inference, and stable-key recommendations. The
remaining buttons stay visibly marked as forthcoming and disabled until their
individual contracts are implemented.

Each contract must define:

- the exact story entities and fields it may read;
- required stable keys in the result;
- an explicit observed-evidence versus inference distinction where applicable;
- the expected structured response schema;
- whether the result is advice-only or eligible for a later reviewed import;
- timeout, error, and offline behavior.

## Delivery sequence

1. Persistent user preferences and profile controls.
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
