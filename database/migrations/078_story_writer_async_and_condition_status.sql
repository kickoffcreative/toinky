-- Add runtime-backed active/inactive controls for authored conditions.

SET @has_dynamic_condition_active=(
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='plot_dynamic_opportunity_conditions' AND COLUMN_NAME='active'
);
SET @sql=IF(@has_dynamic_condition_active=0,
  'ALTER TABLE plot_dynamic_opportunity_conditions ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1 AFTER sort_order, ADD KEY idx_dynamic_condition_active(dynamic_opportunity_id,active,sort_order)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_resolution_condition_active=(
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='plot_resolution_conditions' AND COLUMN_NAME='active'
);
SET @sql=IF(@has_resolution_condition_active=0,
  'ALTER TABLE plot_resolution_conditions ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1 AFTER sort_order, ADD KEY idx_resolution_condition_active(resolution_fragment_id,active,sort_order)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

INSERT IGNORE INTO schema_migrations(migration) VALUES('078_story_writer_async_and_condition_status.sql');
