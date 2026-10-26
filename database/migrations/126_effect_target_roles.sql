-- 126: Universal effect targets — a decision / no-action / opportunity effect
-- can apply to the deciding character (default, NULL), one or more specific
-- characters (comma-separated role codes), or the whole group ('GROUP').
-- This lets one effect move or change several characters at once without
-- duplicating the effect per character (e.g. a group move to one location).
ALTER TABLE plot_option_effects ADD COLUMN target_role_code VARCHAR(500) NULL DEFAULT NULL AFTER target_code;
ALTER TABLE plot_no_action_effects ADD COLUMN target_role_code VARCHAR(500) NULL DEFAULT NULL AFTER target_code;
ALTER TABLE plot_dynamic_option_effects ADD COLUMN target_role_code VARCHAR(500) NULL DEFAULT NULL AFTER target_code;

INSERT IGNORE INTO schema_migrations(migration) VALUES('126_effect_target_roles.sql');
