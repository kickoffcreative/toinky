# Ending Coverage Checks

Draft and submitted-version authoring audits include structural warnings from
`EndingCoverageAudit`. They do not change gameplay, fragment selection, or
publication errors. Resolution sections still choose the first matching active
fragment ordered by priority, then fragment ID.

The audit reports:

- Active fragments hidden behind an earlier unconditional fragment.
- Missing coverage of configured preset values in a section conditioned on one
  unambiguous WORLD_STATE PRESET value.
- Fragments that match none of those presets, or always lose to earlier matches.

The finite check supports EQ, NEQ, IN, NOT_IN, EXISTS, and NOT_EXISTS, with
conditions combined by AND. It considers configured, present preset values only,
not unset or legacy out-of-domain values. It skips mixed-key sections, other
scopes/types, ambiguous definitions, unsupported operators, and domains larger
than 256 values. No warning is a guarantee of coverage or narrative consistency.
Some presets may be unreachable because of authored transitions; these are
review warnings, not publication blockers. A deliberate fallback is supported.

Contradictions between prose in different sections still require story review
against actual game state. This audit does not infer that a sentence asserts a
rescue, sacrifice, departure, or other fact.

## Validation

Run `php tools/ending_coverage_test.php` (no database required). CI runs this
alongside PHP lint. Normal story audits display the warnings in their review
package for both live drafts and pinned submissions.

## Rollback

Baseline: `67d32d8`, tagged locally as
`rollback/before-continuity-validation-2026-11-12`.

This change has no migrations or data writes. To undo it after merging, revert
its implementation commit and deploy the reverted revision through the normal
release process. Do not reset a shared branch or modify production data. For
local comparison, the baseline tag retains the exact pre-change code.
