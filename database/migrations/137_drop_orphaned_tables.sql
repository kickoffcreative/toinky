-- Drop the orphaned focus/audit tables (audit follow-up, Oct 30, 2026).
-- plot_focus_profiles and plot_focus_option_weights were reachable only
-- from tools/simulate.php --focus, which no workflow or web path ever
-- passes (the batch path hardcodes focus to empty). platform_story_audit_rules
-- only fed a printed checklist in tools/story_immersion_audit.php. No app/
-- or public/ code reads any of the three tables. The companion tool updates
-- ship with this migration: the immersion audit queried the audit table
-- unguarded, so dropping it alone would break the CI smoke step.
DROP TABLE IF EXISTS plot_focus_option_weights;
DROP TABLE IF EXISTS plot_focus_profiles;
DROP TABLE IF EXISTS platform_story_audit_rules;

INSERT IGNORE INTO schema_migrations(migration) VALUES('137_drop_orphaned_tables.sql');
