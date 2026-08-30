-- Integrate isolated, seeded opportunity tests into Simulation Review Batches.

ALTER TABLE simulation_review_batches
  MODIFY controller_mode ENUM('AUTONOMOUS','HUMAN_RANDOM','HUMAN_BIASED','FOCUSED') NOT NULL;

SET @has_focus_opportunity_code=(
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='simulation_review_batches' AND COLUMN_NAME='focus_opportunity_code'
);
SET @sql=IF(@has_focus_opportunity_code=0,
  'ALTER TABLE simulation_review_batches ADD COLUMN focus_opportunity_code VARCHAR(120) NULL AFTER controller_mode, ADD KEY idx_sim_review_focus(plot_id,focus_opportunity_code)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_focus_runs_per_option=(
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='simulation_review_batches' AND COLUMN_NAME='focus_runs_per_option'
);
SET @sql=IF(@has_focus_runs_per_option=0,
  'ALTER TABLE simulation_review_batches ADD COLUMN focus_runs_per_option INT UNSIGNED NULL AFTER focus_opportunity_code',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

INSERT IGNORE INTO schema_migrations(migration) VALUES('081_focused_simulation_review_batches.sql');
