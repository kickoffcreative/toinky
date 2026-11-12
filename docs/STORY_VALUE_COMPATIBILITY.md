# Story Value Compatibility

Before changing a Story Value's type or allowed preset list, the writer save
checks its existing active effects and conditions. It rejects incompatible
changes and names the authored uses that must be updated first.

Coverage includes decision effects, dynamic opportunity effects and conditions,
world-event effects and their conditions, no-action effects and their conditions,
option conditions, and ending conditions. FACTOR-prefixed condition keys belong
only to the FACTOR scope. Bare keys retain the existing compatibility behavior.

Examples: removing a preset still assigned by an event is rejected; converting
a numerical value to a preset is rejected if a condition still uses a numerical
comparison. Inactive rows are ignored; no-action and other rows without an
active column remain dependencies. As before, active child rows are checked
even if their parent is inactive, so reactivation cannot restore a broken use.

This does not migrate existing content, alter running games or snapshots,
define new values, or validate the meaning of prose. State-machine metadata
outside the authoring workspace is not part of this check.

## Validation And Rollback

Run `php tools/story_value_dependency_test.php` without a database. CI also
runs the existing database-backed `story_value_type_guard_test.php`, which
verifies that an incompatible save is blocked through the authoring service.

The baseline is deployed merge commit `1c9bf5bf208d5bc59b6d185e79bb154e9f1bdf9b`,
tagged locally `rollback/before-story-value-dependencies-2026-11-12`.
There is no database migration. Revert the implementation commit and deploy
the reverted code to undo this change; authored data needs no recovery.
