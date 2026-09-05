# Story Writer UI Standards

This is the current visual and navigation standard for Story Writer. It
describes the implemented workspace structure, not a future design proposal.
The source of truth is `public/_writer_navigation.php`,
`public/writer.php`, `public/assets/toinky-writer-navigation.css`, and
`public/assets/toinky-writer-navigation.js`.

## Primary navigation

The top-level tabs appear in this order:

1. How It Works
2. Story Framework
3. Characters
4. Events
5. Decisions
6. Map, Dependencies, Opportunities
7. Endings
8. Media
9. Submit for Review (administrators only)

`Timeline` is the old name for **Events**. `Story Map` is now the more
descriptive **Map, Dependencies, Opportunities** tab. Artwork is not a
top-level tab; it is a sub-tab of **Media**.

## Sub-tab navigation

Sub-tabs use the same navigation treatment as the primary tabs: they are a
single wrapped navigation row inside the tab panel, followed by the panel's
content. The current sub-tabs are:

- **How It Works:** How Story Writer Works; Identifier Codes.
- **Story Framework:** Story Overview; Guided Answers; Locations; Story Values.
- **Map, Dependencies, Opportunities:** Story Map and Dependencies;
  Conditional Opportunities.
- **Endings:** Ending Fragments; Ending Sections.
- **Media:** Media Library; Artwork.

Other primary tabs do not need sub-tabs. An invalid or missing sub-tab routes
to the first valid sub-tab. Older links remain compatible: `artwork` routes to
Media > Artwork, and Overview > Ending Sections routes to Endings > Ending
Sections.

## Standard panel layout

Every tab panel follows this order:

1. One left-aligned panel title.
2. The sub-tab navigation, when the tab has sub-tabs.
3. A short description of the current view.
4. Quick Add / Edit Story Values, when the view supports it.
5. Filter by name or code, when the view contains repeatable records.
6. The view's records, editor, map, table, or upload workspace.

The panel is a single bounded surface. Sub-tabs, descriptions, controls, and
content stay inside that surface and use the same left alignment. Long labels
wrap instead of widening or shifting the workspace.

## Shared controls

### Quick Add / Edit Story Values

The shared Quick Add / Edit Story Values disclosure is placed after the view
description and before the filter. It uses the same Story Value fields as the
full Story Framework editor: target code, scope, value type, label, numeric
default or allowed presets, and writer guidance. Full definitions remain in
Story Framework > Story Values.

Quick values appear on Characters, Events, Decisions, both Map sub-tabs,
Ending Fragments, and both Media sub-tabs. Story Framework > Story Values and
Ending Sections use their dedicated editors instead of the quick editor.

### Filter

The shared filter is labelled **Filter by name or code** and appears before
the records. It searches the visible record's name, label, title, headline, or
stable code. The filter includes Expand All and Collapse All when the view has
collapsible records and shows **No matching entries** when appropriate. New
record forms remain available while a filter is active.

## View-specific standards

- **How It Works** keeps the two explanatory views under one standardized tab
  shell. How Story Writer Works explains the workspace and authoring model;
  Identifier Codes explains stable identifiers, scopes, value types, and code
  locking.
- **Story Framework** owns the shared premise, Guided Answers, Locations, and
  Story Values. Guided Answers is always a visible sub-tab, even when no
  answers have been saved yet. Story Values has its description, filter, and
  full definition editors.
- **Characters** shows the description, Quick Add / Edit Story Values, filter,
  collapsible character editors, and the Add Character editor. Character type
  choices respect the player and AI-character limits in Story Framework.
- **Events** is the chronological world-event editor. It uses the shared quick
  values and filter controls before its collapsible event records.
- **Decisions** keeps Quick Add / Edit Story Values, the filter, and decision
  records aligned to the same left edge. Each decision contains its options and
  ordered effects.
- **Map, Dependencies, Opportunities** keeps both sub-tabs inside one panel.
  Each sub-tab has its own description, Quick Add / Edit Story Values, filter,
  and content. The map shows event-to-decision dependencies; Conditional
  Opportunities shows authored conditions, choices, and effects.
- **Endings** contains Ending Fragments and Ending Sections. Ending Fragments
  uses the quick values and filter; Ending Sections uses its dedicated editor
  and filter. Ending Sections is no longer under Story Framework.
- **Media** contains Media Library and Artwork. The library manages owned
  uploads, titles, alternative text, crops, thumbnails, and deletion. Artwork
  assigns available media to the story, characters, events, scenes, outcomes,
  and endings. Artwork includes the scrollable thumbnail picker beside the
  assignment form on desktop and above it on narrow screens.
- **Submit for Review** is available only to administrators. It runs the
  server-side audit and freezes a numbered revision for review.

## Editor and record behavior

- Repeatable records use collapsible editors so a long story remains scannable.
- Existing records show their current stable code and display label in the
  summary. New record forms are open or remain discoverable at the end of the
  relevant view.
- Stable codes are shown before dependent fields and display their dependency
  status. Once used, a code is locked by the server and cannot be renamed by
  changing the browser form.
- Shared helper controls and field order should be reused instead of creating
  a one-off toolbar for a single tab.
- Save actions are explicit. Story Writer does not silently save while a writer
  is browsing, filtering, or expanding records.

## Responsive and accessibility rules

- Panels have `min-width: 0` so long content can wrap inside the workspace.
- Primary and sub-tab links wrap when needed; long labels may break at word
  boundaries instead of creating horizontal overflow.
- Grid editors become one column at widths of 800px or less.
- Artwork's desktop two-column workspace becomes a stacked layout on narrow
  screens, with the media library below the assignment form.
- Search inputs retain an accessible label, and the current tab exposes
  `aria-current="page"`.
- Hidden sub-panels use the native `hidden` state and are not left in the
  keyboard order.
- Buttons and controls retain visible spacing and stable dimensions when text
  wraps or validation messages appear.

## Change rule

Any new Story Writer tab or sub-tab must define its title, description,
sub-navigation, quick-value behavior, filter behavior, record layout, mobile
layout, save boundary, and accessibility labels before it is added. Add or
update the navigation regression test with the same change.

Detailed Inky proposal and assistant-menu rules are maintained separately in
[docs/INKY_ACTION_RULES.md](INKY_ACTION_RULES.md).
