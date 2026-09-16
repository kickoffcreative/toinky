-- Generic media library, theme media lookups, security settings, and genre color defaults.
CREATE TABLE IF NOT EXISTS writer_media_assets (
  media_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uploaded_by_user_id BIGINT UNSIGNED NULL,
  media_type ENUM('BACKGROUND','DIVIDER','CARD','CHARACTER','OPENING','SCENE','TURNING_POINT','OUTCOME','ENDING','OTHER') NOT NULL,
  title_text VARCHAR(190) NOT NULL,
  alt_text VARCHAR(500) NOT NULL,
  file_path VARCHAR(500) NOT NULL,
  mime_type VARCHAR(80) NOT NULL,
  width_px INT UNSIGNED NOT NULL,
  height_px INT UNSIGNED NOT NULL,
  file_size_bytes BIGINT UNSIGNED NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at_utc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_writer_media_owner(uploaded_by_user_id,active,media_type),
  CONSTRAINT fk_writer_media_owner FOREIGN KEY(uploaded_by_user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @has_bg_media=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='genre_themes' AND COLUMN_NAME='background_media_id');
SET @sql=IF(@has_bg_media=0,'ALTER TABLE genre_themes ADD COLUMN background_media_id BIGINT UNSIGNED NULL AFTER body_font, ADD COLUMN divider_media_id BIGINT UNSIGNED NULL AFTER background_media_id, ADD COLUMN card_media_id BIGINT UNSIGNED NULL AFTER divider_media_id','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_library_media=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='plot_media_assets' AND COLUMN_NAME='library_media_id');
SET @sql=IF(@has_library_media=0,'ALTER TABLE plot_media_assets ADD COLUMN library_media_id BIGINT UNSIGNED NULL AFTER uploaded_by_user_id','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

INSERT INTO platform_settings(setting_key,setting_value) VALUES
('SECURITY_CAPTCHA_MODE','BUILTIN'),('GOOGLE_RECAPTCHA_SITE_KEY',''),('GOOGLE_RECAPTCHA_SECRET_KEY','')
ON DUPLICATE KEY UPDATE setting_key=VALUES(setting_key);

UPDATE genre_themes t JOIN story_genres g ON g.genre_id=t.genre_id SET
t.primary_color=CASE g.genre_code WHEN 'ADVENTURE' THEN '#245c43' WHEN 'COMEDY' THEN '#8b3f70' WHEN 'CONTEMPORARY' THEN '#315d78' WHEN 'CRIME' THEN '#31343a' WHEN 'DRAMA' THEN '#70404b' WHEN 'FANTASY' THEN '#513b85' WHEN 'HISTORICAL' THEN '#744c2e' WHEN 'HORROR' THEN '#4b1825' WHEN 'MYSTERY' THEN '#34475d' WHEN 'POLITICAL_INTRIGUE' THEN '#3d345f' WHEN 'ROMANCE' THEN '#9b4961' WHEN 'SCIENCE_FICTION' THEN '#155b70' WHEN 'STEAMPUNK' THEN '#765025' WHEN 'SURVIVAL' THEN '#40582f' ELSE '#7a2936' END,
t.secondary_color=CASE g.genre_code WHEN 'ADVENTURE' THEN '#17382b' WHEN 'COMEDY' THEN '#542440' WHEN 'CONTEMPORARY' THEN '#203e52' WHEN 'CRIME' THEN '#17191d' WHEN 'DRAMA' THEN '#40252c' WHEN 'FANTASY' THEN '#2c244d' WHEN 'HISTORICAL' THEN '#3e2c20' WHEN 'HORROR' THEN '#190d12' WHEN 'MYSTERY' THEN '#1d2a39' WHEN 'POLITICAL_INTRIGUE' THEN '#171a2b' WHEN 'ROMANCE' THEN '#5e293a' WHEN 'SCIENCE_FICTION' THEN '#082f3b' WHEN 'STEAMPUNK' THEN '#3d2a18' WHEN 'SURVIVAL' THEN '#24321d' ELSE '#3c1119' END,
t.accent_color=CASE g.genre_code WHEN 'ADVENTURE' THEN '#d29b42' WHEN 'COMEDY' THEN '#efb84d' WHEN 'CONTEMPORARY' THEN '#d58b62' WHEN 'CRIME' THEN '#b99b61' WHEN 'DRAMA' THEN '#c9946d' WHEN 'FANTASY' THEN '#d3af58' WHEN 'HISTORICAL' THEN '#c39a5b' WHEN 'HORROR' THEN '#c34f4f' WHEN 'MYSTERY' THEN '#b89b62' WHEN 'POLITICAL_INTRIGUE' THEN '#b8913e' WHEN 'ROMANCE' THEN '#e4a092' WHEN 'SCIENCE_FICTION' THEN '#52c7c9' WHEN 'STEAMPUNK' THEN '#c58b3e' WHEN 'SURVIVAL' THEN '#c49a4c' ELSE '#e06a58' END,
t.background_color=CASE g.genre_code WHEN 'HORROR' THEN '#151113' WHEN 'SCIENCE_FICTION' THEN '#08171d' WHEN 'CRIME' THEN '#202329' ELSE '#f2efe8' END,
t.surface_color=CASE g.genre_code WHEN 'HORROR' THEN '#241b1e' WHEN 'SCIENCE_FICTION' THEN '#102932' WHEN 'CRIME' THEN '#2c3037' ELSE '#ffffff' END,
t.text_color=CASE g.genre_code WHEN 'HORROR' THEN '#eee4e6' WHEN 'SCIENCE_FICTION' THEN '#def4f5' WHEN 'CRIME' THEN '#f0ede7' ELSE '#252739' END,
t.heading_font=CASE WHEN g.genre_code IN('SCIENCE_FICTION','CONTEMPORARY') THEN 'Inter, system-ui, sans-serif' WHEN g.genre_code='COMEDY' THEN 'Trebuchet MS, system-ui, sans-serif' ELSE 'Georgia, Times New Roman, serif' END,
t.body_font=CASE WHEN g.genre_code IN('HISTORICAL','FANTASY','POLITICAL_INTRIGUE') THEN 'Georgia, Times New Roman, serif' ELSE 'Inter, system-ui, sans-serif' END;

INSERT IGNORE INTO schema_migrations(migration) VALUES('065_media_security_and_genre_defaults.sql');
