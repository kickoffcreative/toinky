# Authoring Reference Audit

Draft and submitted-version audits check managed values used by decision,
opportunity, event, and no-action effects, plus option, opportunity, event,
no-action, and ending conditions. Invalid references and preset assignments
are authoring errors. They must be repaired before submission, including when
they originate in older or imported content.

FACTOR-prefixed keys resolve only against FACTOR definitions. Bare keys also
resolve legacy entity aliases and direct entity-property references. Drafts
use current metadata; submitted versions use their own state_metadata snapshot.
Where both registries define a key, the managed definition retains its existing
authoring precedence. A differing type or preset domain produces a runtime
compatibility warning rather than changing that precedence or rejecting the
content solely because the registries disagree.
For a pre-metadata snapshot, an unknown option/event/no-action condition source
produces a verification warning rather than silently consulting live metadata.
Event LOCATION effects are a special case:
their target codes identify characters, so the audit validates their authored
destination rather than requiring a Story Value with the character's code.

Rows with an explicit inactive flag are skipped. Child rows without an active
flag remain subject to validation, as they do in the save-time guard. This is
reference/type validation, not proof of reachable outcomes or prose continuity.
It does not change gameplay or repair stored content automatically.

## Validation And Rollback

Run `php tools/story_value_audit_test.php` without a database. CI also rebuilds
test databases and audits the validation stories against the schema and seeds.

Baseline: `0ab6e8487461f0ed260a4fc7f048d11fd172d31f`, tagged locally
`rollback/before-authoring-audit-coverage-2026-11-12`. No migration or data writes
are included. Revert this implementation commit and deploy normally to restore
the prior validation behavior.
