-- Simplify the active simulated cast.
-- Rule: solo stories may use 1 player + 2 NPCs; multiplayer stories use X players + 1 NPC.
-- Other named people remain story/world entities rather than fully simulated characters.

ALTER TABLE plot_roles
  ADD COLUMN IF NOT EXISTS simulation_active TINYINT(1) NOT NULL DEFAULT 1 AFTER role_type;

ALTER TABLE plots
  ADD COLUMN IF NOT EXISTS recommended_factor_max TINYINT UNSIGNED NOT NULL DEFAULT 4 AFTER max_npcs,
  ADD COLUMN IF NOT EXISTS recommended_stats_per_role TINYINT UNSIGNED NOT NULL DEFAULT 2 AFTER recommended_factor_max;

-- Existing Bellweather and Empty Throne are three-player stories: retain one autonomous NPC only.
SET @bell=(SELECT plot_id FROM plots WHERE code='CONCEALED_FAILURE' LIMIT 1);
UPDATE plots SET max_npcs=1,recommended_factor_max=4,recommended_stats_per_role=2 WHERE plot_id=@bell;
UPDATE plot_roles SET simulation_active=CASE WHEN role_type='PLAYER' OR role_code='FARAH' THEN 1 ELSE 0 END WHERE plot_id=@bell;

SET @throne=(SELECT plot_id FROM plots WHERE code='EMPTY_THRONE' LIMIT 1);
UPDATE plots SET max_npcs=1,recommended_factor_max=4,recommended_stats_per_role=2 WHERE plot_id=@throne;
UPDATE plot_roles SET simulation_active=CASE WHEN role_type='PLAYER' OR role_code='ROWAN' THEN 1 ELSE 0 END WHERE plot_id=@throne;

-- Empty Throne: reduce the five-factor display/model to four active factors.
-- SECRET_CONTROL was useful while testing the larger cast, but evidence state now carries that causal job directly.
DELETE sf FROM simulation_factors sf JOIN simulations s ON s.simulation_id=sf.simulation_id WHERE s.plot_id=@throne AND sf.factor_code='SECRET_CONTROL';
DELETE FROM plot_factors WHERE plot_id=@throne AND factor_code='SECRET_CONTROL';

-- Retired full-character roles must not keep generating autonomous opportunities.
UPDATE plot_dynamic_opportunities pdo
JOIN plot_roles pr ON pr.plot_id=pdo.plot_id AND pr.role_code=pdo.role_code
SET pdo.active=0
WHERE pr.simulation_active=0;

-- Queen/Hand remain valid fictional/world entities in authored world events and consequences,
-- but no longer receive autonomous decision loops in new simulations.

INSERT IGNORE INTO schema_migrations(migration) VALUES('027_simplify_active_cast_and_story_budget.sql');
