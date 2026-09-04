# Inky Character Design and Delivery Playbook

This playbook defines the standard way to design, animate, integrate, test,
and document a new Inky character. It exists to turn character work into one
well-defined delivery package instead of a long sequence of disconnected
prompts and visual corrections.

The goal is not to make every character identical. The goal is to make every
character predictable to build: one approved visual reference, one layer
contract, one renderer decision, one asset package, one test pass, and one
status update.

## Non-negotiable architecture rules

1. The PHP engine stays generic. Do not add story names, plot branches,
   character-specific story rules, or mood decisions to PHP.
2. User preferences remain catalog-backed. A character is identified by a
   stable catalog code, not by a hard-coded display name or a story condition.
3. The visual character is a frontend concern. Mood state comes from the
   generic Inky state surface; the character package supplies layers and poses.
4. Keep approved source artwork and editable masters. Frontend copies are
   derivatives and must never replace the large files.
5. Every visible layer must have a stable filename, a documented coordinate,
   and an explicit stacking order.
6. A character is not ready when one idle screenshot looks good. It is ready
   when its moods, blink, mouth states, transparent shell, movement, loading,
   fallback path, and mobile behavior pass the checklist in this document.

## The single delivery package

Before implementation, create one short character package containing all of
the following. This package is the source of truth for the work and should be
reviewed as one unit.

| Field | Required decision |
| --- | --- |
| Catalog code | Stable code such as `INKY_DIRECTION_D` |
| Catalog label | Character type such as `Lantern`, not a personal companion name |
| Default status | Default, active selectable, or future selectable |
| Art reference | One approved image or mood board with a version/date |
| Renderer | DOM/CSS, PixiJS, or another approved frontend renderer |
| Canvas | One coordinate system, normally a 600 by 600 design canvas |
| Target size | Shell size and artwork scale, separately from status-dot size |
| Layers | Body, face, clothing, hands or appendages, effects, and foreground |
| Poses | Idle, hover, opened, listening, thinking, found, success, help, error, offline |
| Face states | Open eyes, closed lids, happy mouth, open mouth, small O, and any extra states |
| Motion contract | What moves, its pivot, its direction, and its limits |
| Asset paths | Master files, rig files, and optimized frontend files |
| Acceptance checks | Exact visual and runtime conditions that must pass |
| Documentation | Status entry, implementation note, and future follow-up |

Do not start frontend integration until the package has one approved art
reference and one approved layer map. This is the main control that prevents
hundreds of prompts from replacing a missing specification.

## Step-by-step process

### 1. Define the character contract

Write the character's catalog code, label, role in the Inky library, and
whether it is the default, active selectable, or future selectable option.
Keep personal companion naming separate. If a user names an Inky, that name
changes the companion identity shown in the UI; it does not rename the catalog
character type.

Record the following decisions before any art work:

- What the character communicates through its silhouette and accessories.
- Which parts are the body and which parts are independent animated layers.
- Which layer can act like a hand, pointer, cape, ribbon, or other appendage.
- Which moods need a distinct pose rather than only a CSS effect.
- What must remain visible at the smallest supported mobile size.

### 2. Approve one visual direction

Use one reference image as the visual authority. The reference should show the
full character on a transparent or plainly removable background and should be
large enough to inspect the face, edges, accessories, and silhouette.

Before implementation, mark the reference with a small layer map:

1. Background effects behind the body.
2. Main body or shell.
3. Independent clothing or appendage layers.
4. Face layers.
5. Foreground effects.

Also mark the intended pivot for every independently moving part. A scarf hand
that rotates around its image center will not attach correctly if its visual
attachment point is actually near the opposite edge.

### 3. Choose the renderer deliberately

Use the simplest renderer that can preserve the approved design.

- Use DOM/CSS for a small, mostly static rig with a few straightforward image
  layers.
- Use PixiJS when the character has many transparent layers, independent
  pivots, frequent motion, or needs one reliable scene coordinate system.
- Do not create a new renderer for every character. Reuse the existing generic
  surface and add a renderer adapter only when the layer behavior requires it.

The renderer decision belongs in the package. Do not discover it piecemeal
after the artwork has already been wired into production markup.

### 4. Build a canonical rig prototype

Create a standalone prototype under `docs/inky/<character>/rig-vN/`. The
prototype is for visual approval and coordinate discovery; it is not a place
for story-specific logic.

The prototype must:

- Use one fixed design canvas, normally 600 by 600 pixels.
- Show the body and every independent layer together.
- Provide buttons for all required mood states.
- Show open eyes, closed lids, and every mouth state.
- Demonstrate appendage pivots and foreground/background ordering.
- Render on a transparent-capable stage so background artifacts are obvious.
- Make clipping visible by keeping the full character inside the inspection area.

Approve the prototype before connecting it to profile preferences or Story
Writer. If the prototype is wrong, integration only makes the wrong design
harder to change.

### 5. Prepare layered assets

Export each independently animated visual as its own transparent PNG or other
approved browser asset. Use stable names that describe the layer, for example:

```text
body_blank.png
eye_left.png
eye_right.png
lid_left.png
lid_right.png
mouth_smile.png
mouth_open.png
mouth_o.png
scarf.png
cape_left.png
cape_right.png
halo.png
sparks.png
```

Asset rules:

- Remove matte, checkerboard, paper, and white backgrounds before export.
- Crop transparent padding consistently, but preserve enough padding for the
  intended pivot and motion.
- Keep left and right appendages as separate files when they move separately.
- Keep the body separate from clothing that should appear in front of it.
- Keep effects that belong behind the body separate from effects in front.
- Use the same visual scale and lighting across all layers.
- Align both eyes and all face states against the same approved face guide.
- Keep the original large files in the master or source location.
- Never hand-edit optimized frontend files as if they were masters.

### 6. Record coordinates, pivots, and z-order

For each layer, record:

```text
name: cape_left
design position: x=145, y=342
design size: width=185, height=124
pivot: x=96%, y=50%
z-index: 3
motion: gentle sway; help pose rotates downward
```

The face contract must explicitly state:

- Open-eye positions and sizes.
- Closed-lid positions and sizes.
- Mouth positions and sizes for every mood family.
- Whether a blink hides the open-eye textures or only changes opacity.

For a reliable blink, open-eye layers must be hidden while closed lids are
visible. Showing lids over still-visible open eyes often reads as a static
eyelid overlay rather than a blink.

### 7. Integrate through the generic Inky surface

Wire the character in this order:

1. Add or activate its catalog row and stable code.
2. Set its artwork root in the shared Inky component.
3. Add the layer markup or Pixi adapter using the documented filenames.
4. Reuse the generic mood names and state transitions.
5. Connect the documented coordinates and pivots.
6. Keep profile settings, naming, docking, and status-dot behavior outside the
   artwork implementation.
7. Keep story actions and AI contracts independent from the character art.
8. Add a fallback path if the chosen renderer can fail to initialize.

No story page should need to know that a character is a Lantern, Octopus, or
Squid in order to position it, blink it, or open the Inky panel. Character
identity belongs in catalog data and the asset package.

### 8. Create optimized frontend assets

The browser should load derivatives sized for the actual Inky widget, while
large files remain available for future artwork work, larger previews, or new
exports.

Use this directory pattern:

```text
public/assets/inky/<character>/source/       # large masters or source art
public/assets/inky/<character>/rig/          # approved rig exports
public/assets/inky/<character>/rig/optimized/ # frontend derivatives
```

For each frontend PNG:

1. Start from the approved rig export, not from a screenshot.
2. Scale to approximately 50% pixel dimensions when the maximum rendered
   layer remains below the asset's resulting resolution at the largest device
   pixel ratio. Use a larger derivative when inspection shows softness.
3. Preserve alpha transparency and the exact filename.
4. Generate optimized copies for every static layer and every dynamic mood
   effect, not only the idle body.
5. Keep the frontend asset root pointed at `optimized/`.
6. Keep cache versioning tied to the optimized set so updated derivatives are
   not hidden by stale browser caches.
7. Verify that no frontend request still points to the legacy large set.

For Pixi, load all required textures concurrently. Sequential texture loading
creates a visible delay even when each individual file is small. For DOM rigs,
use the optimized root for both initial `<img>` layers and dynamically created
mood-effect images.

### 9. Implement animation as a state table

Define one table before writing animation code:

| Mood | Eyes | Mouth | Effects | Independent motion |
| --- | --- | --- | --- | --- |
| Idle | Open, periodic blink | Smile | Resting effect | Gentle idle motion |
| Hover | Open, periodic blink | Smile | Focus effect | Small response |
| Opened | Open, periodic blink | Open | Greeting effect | Greeting pose |
| Listening | Open, periodic blink | Smile | Listening effect | Subtle attention |
| Thinking | Open, periodic blink | Small O | Thinking effect | Deliberate movement |
| Found | Open, periodic blink | Open | Discovery effect | Point or lift |
| Success | Open, periodic blink | Open | Celebration effect | Brief cheer |
| Help | Open, periodic blink | Small O | Broken or concern effect | Helpful gesture |
| Error | Open, periodic blink | Small O | Error effect | Error pose |
| Offline | Dim or resting | Small O | Resting or broken effect | Reduced motion |

The exact visual can vary by character, but the state names and behavior
contract should stay generic. Animation should never depend on a story code or
an AI response string.

### 10. Test the character before calling it complete

Run the following focused checks in order:

1. **Asset integrity**: every filename resolves, every PNG has transparency,
   and all optimized filenames match the rig filenames.
2. **Reference comparison**: compare the prototype and live widget side by
   side at the target size. Check silhouette, eyes, mouth, scarf, appendages,
   pivots, and background effects.
3. **Layer order**: confirm hands or cape panels attach behind the neck layer
   when required and that foreground effects do not cover the face by mistake.
4. **Blink**: capture an open-eye frame and a deterministic blink frame. The
   open-eye layers must disappear and the closed lids must be visible and
   aligned.
5. **Mouth states**: inspect idle, opened, thinking, help, error, and offline.
6. **Mood coverage**: click every mood and look for clipping, missing effects,
   incorrect pivots, or stale layers.
7. **Responsive behavior**: test desktop, tablet, and mobile. The character
   may scale independently, but the status dot must retain its shell-relative
   size.
8. **Placement behavior**: test left and right docking, open navigation, panel
   opening, dragging, and refresh persistence.
9. **Transparency and interaction**: the artwork button remains transparent and
   only visible Inky controls receive pointer input.
10. **Fallback**: disable or block the primary renderer and confirm that the
    DOM fallback still has a visible mouth, eyes, blink, and mood behavior.
11. **Network audit**: confirm optimized assets are requested, no legacy large
    frontend assets are requested, and the browser reports no load errors.
12. **Regression**: run the repository's PHP/source checks and the existing
    story and simulation validation required for the affected surface.

Do not accept a character based only on a static screenshot. A screenshot is
evidence for visual alignment; the browser checks prove that the live state
machine and asset paths work.

### 11. Update status and preserve the decision log

After the focused checks pass, update three places:

1. The character's prototype or design note with the approved version and
   coordinates.
2. `docs/INKY.md` with catalog status and any renderer or asset-path decision.
3. This playbook or the character note with any reusable lesson discovered.

Record rejected visual approaches briefly when they explain a technical rule,
such as why a cape needs a separate pivot or why the browser derivative must
remain separate from the large master. Do not preserve a transcript of every
prompt. Preserve decisions, inputs, outputs, and acceptance evidence.

## One-shot implementation brief

When work is delegated to another engineer or agent, use one brief containing
the following text and attach the approved reference and layer map:

```text
Character code and label:
Default/active/future status:
Approved reference version:
Renderer and reason:
Design canvas and target widget size:
Layer list with z-order and pivots:
Face states and blink rule:
Mood table:
Master asset location:
Optimized asset location:
Profile/catalog changes allowed:
Story-specific changes forbidden:
Acceptance checks required:
Documentation and status files to update:
```

The implementer should return one delivery report containing changed files,
asset counts and byte totals, visual checks, browser/network checks, failed
checks, and the commit or deployment state. This replaces prompt-by-prompt
coordination with a bounded engineering contract.

## Guided authoring workflow

Write New Story with [Inky name] is a conversation inside the floating Inky
panel for helping an amateur writer develop an idea without taking over the
authoring process. It replaces the old top-level Create With Inky form; the
normal New Story form remains available as a separate direct-authoring path.
The story metadata fields stay editable beside the conversation: Stable Story
Code, Title, Genre, Setting, Archetype, Players, AI Cast, and Story Concept.

1. Choose a genre.
2. Answer five questions, one at a time: the spark, protagonist and desire,
   place and time, central problem or choice, and tone or player feeling.
3. Let Inky acknowledge each answer and ask only the next single question.
4. Review the final story brief: title, setting, concept, tone, and notes.
5. Optionally use **Use Brief In New Story** to copy only the title, setting,
   and concept into the normal New Story form.
6. Author locations, characters, events, decisions, options, and endings in
   the normal Story Writer tabs.
7. Run the standard validation workflow before submission.

### Suggested character handoff

The guided character path is a proposal workflow, not an automatic import:

1. Enable **Suggest A Character** only when Story Framework has an active
   location and remaining player or AI-character capacity.
2. Show the complete suggestion in a bounded side card. Do not provide a
   second direct-save route from that card.
3. Use **Add and Continue Story with [Inky name]** to carry the suggestion into
   Continue Story as the active editable proposal.
4. Let the writer edit every character field, copy the current proposal, and
   review it before selecting **Accept and Save**.
5. Persist the approved value through `save_proposal` and the generic Story
   Authoring service. A suggestion is not part of the story until this step.
6. For **Suggest Another**, send the live values from the open editor, remove
   prior accepted character entries from the AI context, and merge only the
   fields selected for revision.

The conversation does not create any story records or pre-built structure.
The writer remains responsible for deciding what becomes part of the story.
The assistant must not generate an entire story in one unbounded response.
Every future guided authoring action should use a narrow context package,
return structured data, show a visible review, require approval before any
write, and preserve the generic engine boundary.

## Definition of done

A new Inky character is complete only when:

- Its visual reference and layer map are approved.
- Its catalog identity and future/default status are explicit.
- Its prototype and live renderer use the same coordinate system.
- Independent layers have correct pivots and stacking order.
- Eyes, lids, mouths, and moods are visibly correct.
- Full-resolution masters remain preserved.
- Optimized frontend assets load from the optimized root.
- The asset loader is concurrent where applicable.
- Desktop and mobile placement work without changing the status-dot size.
- Fallback and no-AI/offline states remain usable.
- Automated and focused visual checks pass.
- Documentation records the final decisions and remaining work.
