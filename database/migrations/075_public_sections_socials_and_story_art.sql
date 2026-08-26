-- Flexible public sections, social links, and primary story artwork.

ALTER TABLE writer_media_assets
  MODIFY media_type ENUM('BACKGROUND','DIVIDER','CARD','PRIMARY','CHARACTER','OPENING','SCENE','TURNING_POINT','OUTCOME','ENDING','OTHER') NOT NULL;

ALTER TABLE plot_media_assets
  MODIFY placement ENUM('PRIMARY','CHARACTER','OPENING','SCENE','TURNING_POINT','OUTCOME','ENDING','OTHER') NOT NULL;

SET @has_layout=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='site_page_sections' AND COLUMN_NAME='layout_type');
SET @sql=IF(@has_layout=0,'ALTER TABLE site_page_sections ADD COLUMN layout_type ENUM(''IMAGE_RIGHT'',''IMAGE_LEFT'',''THREE_COLUMN'',''SOCIALS'',''TEXT_CENTER'') NOT NULL DEFAULT ''IMAGE_RIGHT'' AFTER image_media_id, ADD COLUMN columns_json MEDIUMTEXT NULL AFTER layout_type, ADD COLUMN background_fit ENUM(''COVER'',''CONTAIN'',''TILE'',''STRETCH'',''CENTER'') NOT NULL DEFAULT ''COVER'' AFTER background_media_id, ADD COLUMN background_opacity TINYINT UNSIGNED NOT NULL DEFAULT 100 AFTER background_fit, ADD COLUMN overlay_mode ENUM(''NONE'',''COLOR'',''GRADIENT'') NOT NULL DEFAULT ''NONE'' AFTER background_opacity, ADD COLUMN overlay_color_start CHAR(9) NULL AFTER overlay_mode, ADD COLUMN overlay_color_end CHAR(9) NULL AFTER overlay_color_start, ADD COLUMN overlay_opacity TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER overlay_color_end','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

INSERT INTO platform_settings(setting_key,setting_value)
VALUES('BRAND_SOCIAL_URLS','{}')
ON DUPLICATE KEY UPDATE setting_key=VALUES(setting_key);

INSERT IGNORE INTO schema_migrations(migration) VALUES('075_public_sections_socials_and_story_art.sql');
