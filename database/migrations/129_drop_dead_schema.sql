-- Cleanup (2026-10-26 repo audit): drop schema elements confirmed dead.
-- story_factor_types: created + seeded in migration 014, never queried by any PHP.
-- plots.recommended_factor_max / plots.recommended_stats_per_role: deprecated in
-- docs/SCHEMA_DEPRECATIONS.md (Milestone 2), zero PHP consumers, hold only defaults.
-- Rollback: re-create story_factor_types from migration 014 and re-add the two
-- columns to plots (original definitions from migration 027:
--   recommended_factor_max TINYINT UNSIGNED NOT NULL DEFAULT 4
--   recommended_stats_per_role TINYINT UNSIGNED NOT NULL DEFAULT 2).
-- Deploy only with a current production backup.

DROP TABLE IF EXISTS story_factor_types;
ALTER TABLE plots DROP COLUMN IF EXISTS recommended_factor_max;
ALTER TABLE plots DROP COLUMN IF EXISTS recommended_stats_per_role;

INSERT IGNORE INTO schema_migrations(migration) VALUES('129_drop_dead_schema.sql');
