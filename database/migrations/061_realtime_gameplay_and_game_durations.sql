-- Generic real-time game-duration configuration.
-- Plot timelines stay authored on their own clock while hosted games can run
-- for minutes or days without changing any story-specific PHP.

SET @has_timeline_duration=(
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='plots' AND COLUMN_NAME='timeline_duration_seconds'
);
SET @sql=IF(@has_timeline_duration=0,
  'ALTER TABLE plots ADD COLUMN timeline_duration_seconds INT UNSIGNED NOT NULL DEFAULT 300 AFTER max_npcs',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_play_duration=(
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='games' AND COLUMN_NAME='play_duration_seconds'
);
SET @sql=IF(@has_play_duration=0,
  'ALTER TABLE games ADD COLUMN play_duration_seconds INT UNSIGNED NULL DEFAULT NULL AFTER status',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS platform_game_durations (
  duration_seconds INT UNSIGNED PRIMARY KEY,
  label_text VARCHAR(80) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO platform_game_durations(duration_seconds,label_text,sort_order,active) VALUES
  (180,'3 Minutes',10,1),
  (300,'5 Minutes',20,1),
  (900,'15 Minutes',30,1),
  (259200,'3 Days',40,1),
  (432000,'5 Days',50,1),
  (604800,'7 Days',60,1)
ON DUPLICATE KEY UPDATE
  label_text=VALUES(label_text),sort_order=VALUES(sort_order),active=VALUES(active);

INSERT IGNORE INTO schema_migrations(migration)
VALUES('061_realtime_gameplay_and_game_durations.sql');
