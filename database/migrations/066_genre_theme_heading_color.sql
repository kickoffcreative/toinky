-- Add a dedicated heading color to genre themes.
SET @has_heading_color=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='genre_themes' AND COLUMN_NAME='heading_color');
SET @sql=IF(@has_heading_color=0,'ALTER TABLE genre_themes ADD COLUMN heading_color CHAR(7) NOT NULL DEFAULT ''#3d345f'' AFTER text_color','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE genre_themes SET heading_color=primary_color WHERE heading_color='#3d345f' OR heading_color IS NULL;

INSERT IGNORE INTO schema_migrations(migration) VALUES('066_genre_theme_heading_color.sql');
