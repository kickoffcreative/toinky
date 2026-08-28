-- Character availability is separate from autonomous-simulation participation.

SET @has_character_active=(
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='plot_roles' AND COLUMN_NAME='active'
);
SET @sql=IF(@has_character_active=0,
  'ALTER TABLE plot_roles ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1 AFTER simulation_active, ADD KEY idx_plot_role_active(plot_id,active,slot_no)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

INSERT IGNORE INTO schema_migrations(migration) VALUES('080_story_character_status.sql');
