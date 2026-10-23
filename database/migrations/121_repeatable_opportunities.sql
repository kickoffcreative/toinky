-- 121: Repeatable dynamic opportunities.
-- plot_dynamic_opportunities.once_only ("Open only once") was stored by the
-- studio but never read by the engine; every opportunity behaved as
-- once-per-run because simulation_dynamic_opportunities allowed only one
-- instance per (simulation, opportunity, character). The unique key now
-- includes decision_id so a repeatable opportunity can open again -- as a
-- fresh event and decision -- after its previous instance resolves.

SET @has_old_key=(
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='simulation_dynamic_opportunities' AND INDEX_NAME='uq_sim_dynamic_once'
);
SET @sql=IF(@has_old_key>0,
  'ALTER TABLE simulation_dynamic_opportunities DROP INDEX uq_sim_dynamic_once, ADD UNIQUE KEY uq_sim_dynamic_instance (simulation_id, dynamic_opportunity_id, character_id, decision_id)',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
