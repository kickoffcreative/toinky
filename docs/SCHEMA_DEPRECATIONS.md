# Deprecated Database Fields

This register documents schema elements retained for backward compatibility but unavailable for new application development.

## Deprecated after Milestone 2

| Table | Field | Status | Replacement or source of truth |
|---|---|---|---|
| `plots` | `recommended_factor_max` | Deprecated; do not use | Actual factor definitions in `plot_factors` |
| `plots` | `recommended_stats_per_role` | Deprecated; do not use | Actual role statistics in `plot_role_stats` |

Both fields:

- contain their original default values for existing stories;
- have no active runtime PHP consumer;
- remain present to avoid destructive schema changes during the Milestone 2 stabilization period;
- must not be referenced by new features, reports, imports, or authoring tools.

Removal requires a separate cleanup milestone, a current production backup, a renewed repository-wide reference audit, and an explicit rollback plan.


## Removed in migration 129 (2026-10-26 cleanup)

| Table | Field | Removed by |
|---|---|---|
| `plots` | `recommended_factor_max` | `129_drop_dead_schema.sql` (repo audit 2026-10-26: zero PHP consumers, defaults only) |
| `plots` | `recommended_stats_per_role` | `129_drop_dead_schema.sql` (repo audit 2026-10-26: zero PHP consumers, defaults only) |

`story_factor_types` (created + seeded in migration 014) was also dropped by migration 129 — it was never queried by any PHP.
