-- 127: World event effect targets — the same universal target as decision
-- effects, so a world event can hit specific characters, a subset, or the
-- whole group (e.g. an explosion injures some characters more than others;
-- a portal sends each character to a different location).
-- target_role_code: NULL/'' = whole group (world events have no decider);
-- 'GROUP' = whole group; otherwise a comma-separated list of role codes.
ALTER TABLE plot_world_event_effects ADD COLUMN target_role_code VARCHAR(500) NULL DEFAULT NULL AFTER target_code;

INSERT IGNORE INTO schema_migrations(migration) VALUES('127_world_event_effect_targets.sql');
