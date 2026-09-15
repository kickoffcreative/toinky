-- Unified media versions, configurable upload processing, and parallax media links.

CREATE TABLE IF NOT EXISTS writer_media_versions (
  media_version_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  media_id BIGINT UNSIGNED NOT NULL,
  version_code ENUM('THUMBNAIL','MEDIUM','LARGE') NOT NULL,
  file_path VARCHAR(500) NOT NULL,
  mime_type VARCHAR(80) NOT NULL,
  width_px INT UNSIGNED NOT NULL,
  height_px INT UNSIGNED NOT NULL,
  file_size_bytes BIGINT UNSIGNED NOT NULL,
  crop_x INT UNSIGNED NULL,
  crop_y INT UNSIGNED NULL,
  crop_width INT UNSIGNED NULL,
  crop_height INT UNSIGNED NULL,
  created_at_utc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at_utc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_writer_media_version(media_id,version_code),
  CONSTRAINT fk_writer_media_version_media FOREIGN KEY(media_id) REFERENCES writer_media_assets(media_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @media_type_has_parallax=(SELECT LOCATE('PARALLAX',COLUMN_TYPE) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='writer_media_assets' AND COLUMN_NAME='media_type');
SET @sql=IF(@media_type_has_parallax=0,'ALTER TABLE writer_media_assets MODIFY media_type ENUM(''BACKGROUND'',''DIVIDER'',''CARD'',''PRIMARY'',''CHARACTER'',''OPENING'',''SCENE'',''TURNING_POINT'',''OUTCOME'',''ENDING'',''PARALLAX'',''OTHER'') NOT NULL','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_parallax_media_id=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='site_parallax_layers' AND COLUMN_NAME='media_id');
SET @sql=IF(@has_parallax_media_id=0,'ALTER TABLE site_parallax_layers ADD COLUMN media_id BIGINT UNSIGNED NULL AFTER image_path, ADD KEY idx_site_parallax_media_id(media_id), ADD CONSTRAINT fk_site_parallax_media FOREIGN KEY(media_id) REFERENCES writer_media_assets(media_id) ON DELETE SET NULL','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

INSERT INTO platform_settings(setting_key,setting_value) VALUES
('MEDIA_MAX_UPLOAD_MB','10'),
('MEDIA_MASTER_MAX_PX','1920'),
('MEDIA_THUMBNAIL_SIZE_PX','150'),
('MEDIA_GENERATE_MEDIUM','0'),
('MEDIA_MEDIUM_MAX_PX','250'),
('MEDIA_MEDIUM_MODE','LONGEST'),
('MEDIA_GENERATE_LARGE','0'),
('MEDIA_LARGE_MAX_PX','600'),
('MEDIA_LARGE_MODE','LONGEST'),
('MEDIA_JPEG_QUALITY','86'),
('MEDIA_PNG_COMPRESSION','7')
ON DUPLICATE KEY UPDATE setting_key=VALUES(setting_key);

INSERT INTO writer_media_versions(media_id,version_code,file_path,mime_type,width_px,height_px,file_size_bytes,crop_x,crop_y,crop_width,crop_height)
SELECT media_id,'THUMBNAIL',thumbnail_path,mime_type,
       COALESCE(NULLIF(thumbnail_crop_size,0),150),COALESCE(NULLIF(thumbnail_crop_size,0),150),
       0,thumbnail_crop_x,thumbnail_crop_y,thumbnail_crop_size,thumbnail_crop_size
FROM writer_media_assets
WHERE thumbnail_path IS NOT NULL AND thumbnail_path <> ''
ON DUPLICATE KEY UPDATE file_path=VALUES(file_path);

INSERT IGNORE INTO schema_migrations(migration)
VALUES('111_media_versions_settings_and_parallax_links.sql');
