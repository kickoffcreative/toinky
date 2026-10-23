-- 120: Percent-based event triggers, writer game durations, public standings.
-- Events gain trigger_percent as the authoring source of truth; event_second
-- stays as the derived runtime value so the engine and simulations are unchanged.
-- Changing a story's Duration rescales every event from its percent.

SET @has_trigger_percent=(
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='plot_world_events' AND COLUMN_NAME='trigger_percent'
);
SET @sql=IF(@has_trigger_percent=0,
  'ALTER TABLE plot_world_events ADD COLUMN trigger_percent DECIMAL(6,3) NULL AFTER event_second',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE plot_world_events pwe
JOIN plots p ON p.plot_id=pwe.plot_id
SET pwe.trigger_percent=ROUND(pwe.event_second*100/NULLIF(p.timeline_duration_seconds,0),3)
WHERE pwe.trigger_percent IS NULL;

SET @has_standings_code=(
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='plots' AND COLUMN_NAME='standings_stat_code'
);
SET @sql=IF(@has_standings_code=0,
  'ALTER TABLE plots ADD COLUMN standings_stat_code VARCHAR(100) NULL AFTER timeline_duration_seconds',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_standings_title=(
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='plots' AND COLUMN_NAME='standings_title'
);
SET @sql=IF(@has_standings_title=0,
  'ALTER TABLE plots ADD COLUMN standings_title VARCHAR(120) NULL AFTER standings_stat_code',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

INSERT INTO platform_game_durations(duration_seconds,label_text,sort_order,active) VALUES
  (1800,'30 Minutes',31,1),
  (3600,'1 Hour',32,1),
  (5400,'1.5 Hours',33,1),
  (7200,'2 Hours',34,1),
  (9000,'2.5 Hours',35,1),
  (10800,'3 Hours',36,1)
ON DUPLICATE KEY UPDATE
  label_text=VALUES(label_text),sort_order=VALUES(sort_order),active=VALUES(active);

INSERT IGNORE INTO schema_migrations(migration)
VALUES('120_duration_percent_and_standings.sql');
