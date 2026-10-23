-- Event and ending images: link a media asset to one ending fragment.
-- Event images already link through plot_media_assets.event_code; ending
-- images link to the exact fragment the resolution picks, so the picture
-- follows the ending the players actually reached. Both stay optional:
-- with no linked asset, stories render exactly as before.

SET @has_rfid=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='plot_media_assets' AND COLUMN_NAME='resolution_fragment_id');
SET @sql=IF(@has_rfid=0,'ALTER TABLE plot_media_assets ADD COLUMN resolution_fragment_id BIGINT UNSIGNED NULL AFTER event_code, ADD KEY idx_plot_media_fragment(resolution_fragment_id)','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

INSERT IGNORE INTO schema_migrations(migration) VALUES('119_event_and_ending_images.sql');
