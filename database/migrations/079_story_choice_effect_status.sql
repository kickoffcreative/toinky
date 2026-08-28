-- Let writers retire choices and effects without deleting stable story structure.

SET @has_decision_active=(
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='plot_decision_templates' AND COLUMN_NAME='active'
);
SET @sql=IF(@has_decision_active=0,
  'ALTER TABLE plot_decision_templates ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1 AFTER decision_window_seconds, ADD KEY idx_decision_template_active(plot_id,active,event_code)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_option_active=(
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='plot_decision_template_options' AND COLUMN_NAME='active'
);
SET @sql=IF(@has_option_active=0,
  'ALTER TABLE plot_decision_template_options ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1 AFTER sort_order, ADD KEY idx_template_option_active(template_id,active,sort_order)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_option_effect_active=(
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='plot_option_effects' AND COLUMN_NAME='active'
);
SET @sql=IF(@has_option_effect_active=0,
  'ALTER TABLE plot_option_effects ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1 AFTER sort_order, ADD KEY idx_option_effect_active(template_option_id,active,sort_order)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_dynamic_option_active=(
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='plot_dynamic_opportunity_options' AND COLUMN_NAME='active'
);
SET @sql=IF(@has_dynamic_option_active=0,
  'ALTER TABLE plot_dynamic_opportunity_options ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1 AFTER sort_order, ADD KEY idx_dynamic_option_active(dynamic_opportunity_id,active,sort_order)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_dynamic_effect_active=(
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='plot_dynamic_option_effects' AND COLUMN_NAME='active'
);
SET @sql=IF(@has_dynamic_effect_active=0,
  'ALTER TABLE plot_dynamic_option_effects ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1 AFTER sort_order, ADD KEY idx_dynamic_effect_active(dynamic_option_id,active,sort_order)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

INSERT IGNORE INTO schema_migrations(migration) VALUES('079_story_choice_effect_status.sql');
