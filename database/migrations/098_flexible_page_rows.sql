SET @has_rows=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='site_page_sections' AND COLUMN_NAME='rows_json');
SET @sql=IF(@has_rows=0,'ALTER TABLE site_page_sections ADD COLUMN rows_json MEDIUMTEXT NULL AFTER content_html','SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @has_default=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='site_page_sections' AND COLUMN_NAME='is_default');
SET @sql=IF(@has_default=0,'ALTER TABLE site_page_sections ADD COLUMN is_default TINYINT(1) NOT NULL DEFAULT 0 AFTER active','SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
UPDATE site_page_sections SET is_default=1 WHERE is_default=0;

