-- Generic player-immersion metadata and character-specific delivery offsets.
-- Story-specific briefing prose remains in plot data; PHP only renders it.

SET @has_scenario=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='plots' AND COLUMN_NAME='scenario_text');
SET @sql=IF(@has_scenario=0,'ALTER TABLE plots ADD COLUMN scenario_text TEXT NULL AFTER setting_name','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_perception_offset=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='plot_event_perceptions' AND COLUMN_NAME='delivery_offset_seconds');
SET @sql=IF(@has_perception_offset=0,'ALTER TABLE plot_event_perceptions ADD COLUMN delivery_offset_seconds INT UNSIGNED NULL DEFAULT NULL AFTER perception_text','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_decision_offset=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='plot_decision_templates' AND COLUMN_NAME='delivery_offset_seconds');
SET @sql=IF(@has_decision_offset=0,'ALTER TABLE plot_decision_templates ADD COLUMN delivery_offset_seconds INT UNSIGNED NULL DEFAULT NULL AFTER decision_window_seconds','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE plots SET scenario_text='The monarch is dead, the crown is unclaimed, and a sealed abdication letter could overturn the succession. The court is dividing between Prince Rowan Vale and Elian while officials decide what law, loyalty, and order require.' WHERE code='EMPTY_THRONE';
UPDATE plots SET scenario_text='A violent storm has damaged Bellweather House during a crowded event. An injured guest needs help while a failed inspection report, a concealed payment, and competing loyalties threaten to turn the emergency into evidence.' WHERE code='CONCEALED_FAILURE';

CREATE TABLE IF NOT EXISTS platform_story_delivery_settings (
  setting_code VARCHAR(100) PRIMARY KEY,
  minimum_game_duration_seconds INT UNSIGNED NOT NULL,
  perception_step_seconds INT UNSIGNED NOT NULL DEFAULT 0,
  decision_step_seconds INT UNSIGNED NOT NULL DEFAULT 0,
  npc_additional_seconds INT UNSIGNED NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO platform_story_delivery_settings
(setting_code,minimum_game_duration_seconds,perception_step_seconds,decision_step_seconds,npc_additional_seconds,active)
VALUES('LONG_GAME_STAGGER',86400,3,5,2,1)
ON DUPLICATE KEY UPDATE
  minimum_game_duration_seconds=VALUES(minimum_game_duration_seconds),
  perception_step_seconds=VALUES(perception_step_seconds),
  decision_step_seconds=VALUES(decision_step_seconds),
  npc_additional_seconds=VALUES(npc_additional_seconds),
  active=VALUES(active);

INSERT IGNORE INTO schema_migrations(migration)
VALUES('062_player_immersion_and_staggered_delivery.sql');
