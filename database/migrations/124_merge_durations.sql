-- 124: Merge the story Duration into Host Durations. Events trigger by percentage
-- of the duration the host picks, so the story-level timeline clock
-- (plots.timeline_duration_seconds) and its derived per-event second
-- (plot_world_events.event_second) are retired. Simulation-space seconds
-- (simulation_events, character_history, ...) are unaffected.
SET @has_col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='plots' AND COLUMN_NAME='timeline_duration_seconds');
SET @sql := IF(@has_col>0, 'ALTER TABLE `plots` DROP COLUMN `timeline_duration_seconds`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @has_col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='plot_world_events' AND COLUMN_NAME='event_second');
SET @sql := IF(@has_col>0, 'ALTER TABLE `plot_world_events` DROP COLUMN `event_second`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
INSERT INTO schema_migrations (version) VALUES (124) ON DUPLICATE KEY UPDATE version = version;
