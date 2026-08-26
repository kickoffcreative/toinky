-- Processed media thumbnails and database-managed public page sections.

SET @has_thumbnail_path=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='writer_media_assets' AND COLUMN_NAME='thumbnail_path');
SET @sql=IF(@has_thumbnail_path=0,'ALTER TABLE writer_media_assets ADD COLUMN thumbnail_path VARCHAR(500) NULL AFTER file_path, ADD COLUMN thumbnail_crop_x INT UNSIGNED NULL AFTER thumbnail_path, ADD COLUMN thumbnail_crop_y INT UNSIGNED NULL AFTER thumbnail_crop_x, ADD COLUMN thumbnail_crop_size INT UNSIGNED NULL AFTER thumbnail_crop_y','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS site_pages (
  site_page_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  page_code VARCHAR(60) NOT NULL,
  label_text VARCHAR(120) NOT NULL,
  sort_order INT NOT NULL DEFAULT 100,
  active TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_site_page_code(page_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS site_page_sections (
  site_page_section_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  site_page_id BIGINT UNSIGNED NOT NULL,
  section_code VARCHAR(80) NOT NULL,
  label_text VARCHAR(120) NOT NULL,
  eyebrow_text VARCHAR(190) NULL,
  title_text VARCHAR(500) NULL,
  content_html MEDIUMTEXT NULL,
  image_media_id BIGINT UNSIGNED NULL,
  background_mode ENUM('DEFAULT','COLOR','MEDIA') NOT NULL DEFAULT 'DEFAULT',
  background_color CHAR(9) NULL,
  background_media_id BIGINT UNSIGNED NULL,
  sort_order INT NOT NULL DEFAULT 100,
  active TINYINT(1) NOT NULL DEFAULT 1,
  updated_by_user_id BIGINT UNSIGNED NULL,
  updated_at_utc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_site_page_section(site_page_id,section_code),
  CONSTRAINT fk_site_section_page FOREIGN KEY(site_page_id) REFERENCES site_pages(site_page_id) ON DELETE CASCADE,
  CONSTRAINT fk_site_section_image FOREIGN KEY(image_media_id) REFERENCES writer_media_assets(media_id) ON DELETE SET NULL,
  CONSTRAINT fk_site_section_background FOREIGN KEY(background_media_id) REFERENCES writer_media_assets(media_id) ON DELETE SET NULL,
  CONSTRAINT fk_site_section_editor FOREIGN KEY(updated_by_user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO site_pages(page_code,label_text,sort_order) VALUES
('HOME','Home',10),('HOW_IT_WORKS','How It Works',20),('STORIES','Stories',30),('CREATE','Create',40),('ABOUT','About',50)
ON DUPLICATE KEY UPDATE label_text=VALUES(label_text),sort_order=VALUES(sort_order);

INSERT INTO site_page_sections(site_page_id,section_code,label_text,eyebrow_text,title_text,content_html,sort_order)
SELECT p.site_page_id,s.section_code,s.label_text,s.eyebrow_text,s.title_text,s.content_html,s.sort_order
FROM site_pages p JOIN (
 SELECT 'HOME' page_code,'HERO' section_code,'Hero' label_text,'Live Character-Driven Stories' eyebrow_text,'Every player sees a different story.' title_text,'<p>Step into one shared world. Follow private motives, make decisions in real time, and discover how differently the same story can unfold.</p>' content_html,10 sort_order
 UNION ALL SELECT 'HOME','HOW','How Toinky Works','How Toinky Works','One world. Many truths.','<p>Everyone enters the same story, but no one receives the same information. Your character’s history, relationships, and private choices shape what you believe—and what happens next.</p>',20
 UNION ALL SELECT 'HOME','PERSPECTIVES','Different Perspectives','Different Perspectives','You never know the whole story.','<p>A prince protects a kingdom. A reporter follows a lie. A stranger knows what happens next.</p>',30
 UNION ALL SELECT 'HOME','WORLDS','Story Worlds','Story Worlds','Go anywhere. Become anyone.','<p>Every genre carries its own atmosphere, pace, relationships, and rules.</p>',40
 UNION ALL SELECT 'HOME','PATHS','Audience Paths','For Players, Hosts, And Writers','Choose how you enter the story.','<p>Play a role, host a shared world, or build stories people can inhabit.</p>',50
 UNION ALL SELECT 'HOME','FINALE','Final Call To Action','Your Story Is Waiting','Who will you become?','<p>The same moment can mean something different to everyone inside it.</p>',60
 UNION ALL SELECT 'HOW_IT_WORKS','INTRO','Introduction','How Toinky Works','One shared world. Many private truths.','<p>See how a hosted story moves from invitation to final reveal.</p>',10
 UNION ALL SELECT 'HOW_IT_WORKS','JOURNEY','Player Journey','The Experience','Every choice changes what comes next.','<p>Choose a story, meet the cast, receive private knowledge, and shape a shared ending.</p>',20
 UNION ALL SELECT 'HOW_IT_WORKS','FAQ','Frequently Asked Questions','Questions','Before you enter the story.','<p>Explain timing, character selection, hosting, and what players need to begin.</p>',30
 UNION ALL SELECT 'STORIES','HERO','Stories Hero','Story Worlds','Find a world worth entering.','<p>Browse published stories by genre, cast size, and playing time.</p>',10
 UNION ALL SELECT 'STORIES','CATALOG','Story Catalog','Published Stories','Choose your next life.','<p>Featured and published stories appear here.</p>',20
 UNION ALL SELECT 'CREATE','HERO','Create Hero','Create For Toinky','Build stories people can inhabit.','<p>Write characters with competing truths, meaningful decisions, and earned consequences.</p>',10
 UNION ALL SELECT 'CREATE','PROCESS','Creation Process','The Writing Process','From premise to playable world.','<p>Develop the overview, characters, timeline, decisions, opportunities, and endings.</p>',20
 UNION ALL SELECT 'CREATE','ACCESS','Writer Access','Become A Story Writer','Bring your world to the table.','<p>Explain how prospective writers request access and submit stories for review.</p>',30
 UNION ALL SELECT 'ABOUT','HERO','About Hero','About Toinky','The same moment means something different to everyone.','<p>Toinky is a live, character-driven storytelling platform built around perspective.</p>',10
 UNION ALL SELECT 'ABOUT','PHILOSOPHY','Creative Philosophy','Our Philosophy','Stories become richer when nobody knows everything.','<p>Describe the principles behind shared worlds, private stakes, and human choice.</p>',20
 UNION ALL SELECT 'ABOUT','CONTACT','Contact','Contact','Start a conversation.','<p>Add the preferred contact details for players, hosts, writers, or partners.</p>',30
) s ON s.page_code=p.page_code
ON DUPLICATE KEY UPDATE label_text=VALUES(label_text);

INSERT IGNORE INTO schema_migrations(migration) VALUES('073_media_thumbnails_and_site_pages.sql');
