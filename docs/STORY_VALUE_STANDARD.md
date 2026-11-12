# Story Value Authoring Standard

This is the working standard for the consolidated player-experience repairs.
It supplements the shared Writer's Guide; it does not claim that every rule
below is enforced by the current validator. Story-specific rules remain
authored data. PHP must not branch on a story, character or option code.

## Define One Fact Once

Before creating a value, answer these questions in the story's authoring notes:

| Field | Required Decision |
|---|---|
| Meaning | The exact fact this value establishes and what it does not establish |
| Authority | The single value consumers must read to decide that fact |
| Scope | Shared world, individual character, factor, knowledge, location or relationship |
| Type | Preset for named states; numeric for quantities; text only for genuinely open prose |
| Domain | Every allowed preset, or numeric bounds and units |
| Initial State | A deliberate starting value; distinguish absent, unknown and false |
| Transitions | Each allowed before/after pair and the prerequisite for changing it |
| Writers | Every option, event, opportunity and no-action path that can change it |
| Readers | Every condition, prompt, ending, integrity rule and displayed outcome that uses it |
| Evidence | Player-visible result that makes the change understandable |
| Verification | Fresh-game paths proving success, failure, inaction and alternative outcomes |

A label such as **Guest Status** describes one authoritative fact. A second
value for a narrower task stage can coexist, but its relationship to that fact
must be explicit. Finishing a task must update every authoritative consequence
in the same ordered effect chain. Otherwise a completed carry can coexist with
a later decision that still treats its subject as waiting for rescue.

## Choose Scope And Vocabulary Deliberately

- Use stable codes for identifiers and readable labels for people. Do not rename
  a code to fix player prose after effects or conditions depend on it.
- Keep a numeric count numeric. Do not change an existing preset count to a
  number until all effects, conditions, aliases and pinned versions are checked.
- Use location effects for movement. Prose saying that someone leaves does not
  move the character's displayed location.
- Knowledge means that someone learned something; it does not necessarily make
  the learned statement true. Shared truth and private knowledge are separate.
- Do not use a factor threshold as proof that a concrete event occurred. A high
  safety score alone does not prove a rescue boat arrived.
- Specify what missing means. A missing value must not silently stand for both
  “not yet asked” and “refused.”

## Review Every Writer And Reader

For a value change, inventory normal options, dynamic choices, world-event
effects and missed-choice effects. Check their conditions as well as their
assignments. Include ending conditions, prefixed factor references and legacy
entity/property aliases. A dropdown listing the new preset does not prove every
runtime reader has been updated.

The authoring audit currently checks managed definitions before legacy metadata.
Runtime entity-state assignments still use the aliased legacy property metadata
when it exists. A matching dropdown alone therefore cannot prove that a runtime
assignment is accepted. The audit reports domain disagreement; it does not
reconcile it automatically. Preserve
both inventories before a repair. Add legitimate missing vocabulary through
the studio, or revise incorrect references there. Never weaken a guard merely
to make a warning disappear, and never repair production story content with SQL
or a content migration.

## Make Effects And Prose Agree

Describe the immediate result of the selected action. Distinguish offering a
bargain from accepting it, opening a way from someone crossing it, and starting
an evacuation from completing it. State a bargain's price before the choice;
later freedom must explain how the same obligation ends.

Unconditional shared prose must be compatible with every state that can reach
it. Where that would erase an important consequence, author conditioned content
instead. A repeatable hint is different from identifying a person twice as a
first revelation. Keep the first identification at the intended reveal.

An ending needs both local coverage and agreement across sections. A fallback
in one section does not make another section complete. Test conjunctions such
as rescue signal plus low safety, not just each variable separately. Across all
selected fragments, custody, departure, family fate and remaining knowledge must
describe the same outcome.

## Publish And Prove

1. Preserve original fields and the previous published revision. Record the
   source rollback tag separately from content rollback instructions.
2. Edit through studio controls. Wait for saves to finish, reload and inspect
   actual conditions, targets, scope, order and active state.
3. Validate the draft. Treat advisory diagnostics as findings to resolve or
   explain, not as evidence that a story is correct because publication allows it.
4. Submit, review and publish a fixed numbered revision. Record that revision.
5. Create fresh games pinned to it. Verify each affected role, opposite branches
   and no-action paths through the finished page, shared timeline, personal
   column and ending panel.
6. Keep code checks, studio readback and player experience evidence distinct.
   A seeded engine test does not verify the production-authored draft, and an
   old finished game does not test a newly published revision. Recap transitions
   are read from the live draft and can differ from pinned decision prose.

The existing diagnostics cover many references and some finite preset ending
partitions. They do not prove multi-value coverage, emotional continuity,
balanced agency or an earned ending. Those still require authored reasoning
and fresh gameplay.
