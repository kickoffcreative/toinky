-- Generic writer studio, review workflow, invitations, genres, and theme system.
-- No plot codes or character codes are interpreted by PHP.

SET @has_genre_id=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='plots' AND COLUMN_NAME='genre_id');
SET @sql=IF(@has_genre_id=0,'ALTER TABLE plots ADD COLUMN genre_id BIGINT UNSIGNED NULL AFTER genre','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_workflow=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='plots' AND COLUMN_NAME='workflow_status');
SET @sql=IF(@has_workflow=0,'ALTER TABLE plots ADD COLUMN workflow_status ENUM(''DRAFT'',''SUBMITTED'',''CHANGES_REQUESTED'',''APPROVED'',''PUBLISHED'',''ARCHIVED'') NOT NULL DEFAULT ''PUBLISHED'' AFTER status','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_updated=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='plots' AND COLUMN_NAME='updated_at_utc');
SET @sql=IF(@has_updated=0,'ALTER TABLE plots ADD COLUMN updated_at_utc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS story_genres (
  genre_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  genre_code VARCHAR(80) NOT NULL,
  label_text VARCHAR(120) NOT NULL,
  description_text VARCHAR(500) NOT NULL,
  sort_order INT NOT NULL DEFAULT 100,
  active TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_story_genre_code(genre_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS genre_themes (
  genre_id BIGINT UNSIGNED PRIMARY KEY,
  primary_color CHAR(7) NOT NULL DEFAULT '#3d345f',
  secondary_color CHAR(7) NOT NULL DEFAULT '#6d5aae',
  accent_color CHAR(7) NOT NULL DEFAULT '#b8913e',
  background_color CHAR(7) NOT NULL DEFAULT '#f2efe8',
  surface_color CHAR(7) NOT NULL DEFAULT '#ffffff',
  text_color CHAR(7) NOT NULL DEFAULT '#252739',
  heading_font VARCHAR(180) NOT NULL DEFAULT 'Georgia, Times New Roman, serif',
  body_font VARCHAR(180) NOT NULL DEFAULT 'Inter, system-ui, sans-serif',
  background_image_path VARCHAR(500) NULL,
  divider_image_path VARCHAR(500) NULL,
  card_image_path VARCHAR(500) NULL,
  updated_by_user_id BIGINT UNSIGNED NULL,
  updated_at_utc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_genre_theme_genre FOREIGN KEY(genre_id) REFERENCES story_genres(genre_id) ON DELETE CASCADE,
  CONSTRAINT fk_genre_theme_user FOREIGN KEY(updated_by_user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO story_genres(genre_code,label_text,description_text,sort_order) VALUES
('ADVENTURE','Adventure','Action, discovery, danger, and consequential journeys.',10),
('COMEDY','Comedy','Character-driven conflict shaped by wit, mistakes, and reversal.',20),
('CONTEMPORARY','Contemporary','Stories grounded in recognizable present-day life.',30),
('CRIME','Crime','Investigation, wrongdoing, evidence, loyalty, and consequence.',40),
('DRAMA','Drama','Human conflict, relationships, and difficult choices.',50),
('FANTASY','Fantasy','Imagined worlds shaped by wonder, power, and myth.',60),
('HISTORICAL','Historical','Stories grounded in a particular historical setting.',70),
('HORROR','Horror','Dread, danger, uncertainty, and survival.',80),
('MYSTERY','Mystery','Hidden truth revealed through clues, motives, and investigation.',90),
('POLITICAL_INTRIGUE','Political Intrigue','Power, legitimacy, alliances, institutions, and betrayal.',100),
('ROMANCE','Romance','Emotional intimacy, attraction, commitment, and sacrifice.',110),
('SCIENCE_FICTION','Science Fiction','Speculative technology, society, discovery, and consequence.',120),
('STEAMPUNK','Steampunk','Industrial invention, ornate machinery, and alternative history.',130),
('SURVIVAL','Survival','Scarcity, rescue, endurance, and difficult priorities.',140),
('THRILLER','Thriller','Escalating pressure, urgent danger, and high-stakes choices.',150)
ON DUPLICATE KEY UPDATE label_text=VALUES(label_text),description_text=VALUES(description_text),sort_order=VALUES(sort_order),active=1;

INSERT IGNORE INTO genre_themes(genre_id) SELECT genre_id FROM story_genres;
UPDATE plots p JOIN story_genres g ON UPPER(REPLACE(p.genre,' ','_'))=g.genre_code SET p.genre_id=g.genre_id WHERE p.genre_id IS NULL;
UPDATE plots p JOIN story_genres g ON g.genre_code='POLITICAL_INTRIGUE' SET p.genre_id=g.genre_id WHERE p.code='EMPTY_THRONE' AND p.genre_id IS NULL;
UPDATE plots p JOIN story_genres g ON g.genre_code='THRILLER' SET p.genre_id=g.genre_id WHERE p.code='CONCEALED_FAILURE' AND p.genre_id IS NULL;
UPDATE plots p JOIN story_genres g ON g.genre_code='DRAMA' SET p.genre_id=g.genre_id WHERE p.genre_id IS NULL;

CREATE TABLE IF NOT EXISTS writer_access_requests (
  writer_request_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  request_text TEXT NULL,
  status ENUM('PENDING','APPROVED','DECLINED','WITHDRAWN') NOT NULL DEFAULT 'PENDING',
  reviewed_by_user_id BIGINT UNSIGNED NULL,
  reviewer_notes TEXT NULL,
  created_at_utc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reviewed_at_utc DATETIME NULL,
  KEY idx_writer_request_queue(status,created_at_utc),
  CONSTRAINT fk_writer_request_user FOREIGN KEY(user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  CONSTRAINT fk_writer_request_reviewer FOREIGN KEY(reviewed_by_user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS game_invitations (
  game_invitation_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  game_id BIGINT UNSIGNED NOT NULL,
  email VARCHAR(190) NOT NULL,
  token_hash CHAR(64) NOT NULL,
  invited_by_user_id BIGINT UNSIGNED NOT NULL,
  expires_at_utc DATETIME NOT NULL,
  accepted_by_user_id BIGINT UNSIGNED NULL,
  accepted_at_utc DATETIME NULL,
  created_at_utc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_game_invitation_token(token_hash),
  KEY idx_game_invitation(game_id,email,expires_at_utc),
  CONSTRAINT fk_game_invitation_game FOREIGN KEY(game_id) REFERENCES games(game_id) ON DELETE CASCADE,
  CONSTRAINT fk_game_invitation_sender FOREIGN KEY(invited_by_user_id) REFERENCES users(user_id),
  CONSTRAINT fk_game_invitation_acceptor FOREIGN KEY(accepted_by_user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS platform_settings (
  setting_key VARCHAR(100) PRIMARY KEY,
  setting_value TEXT NOT NULL,
  updated_by_user_id BIGINT UNSIGNED NULL,
  updated_at_utc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_platform_setting_user FOREIGN KEY(updated_by_user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO platform_settings(setting_key,setting_value) VALUES('MAIL_FROM_ADDRESS','accounts@toinky.com'),('MAIL_FROM_NAME','Toinky') ON DUPLICATE KEY UPDATE setting_key=VALUES(setting_key);

CREATE TABLE IF NOT EXISTS email_delivery_log (
  email_delivery_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  recipient_email VARCHAR(190) NOT NULL,
  message_type VARCHAR(80) NOT NULL,
  related_game_id BIGINT UNSIGNED NULL,
  sent_by_user_id BIGINT UNSIGNED NULL,
  delivery_status ENUM('SENT','FAILED') NOT NULL,
  error_text VARCHAR(500) NULL,
  created_at_utc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_email_delivery(recipient_email,created_at_utc),
  CONSTRAINT fk_email_game FOREIGN KEY(related_game_id) REFERENCES games(game_id) ON DELETE SET NULL,
  CONSTRAINT fk_email_sender FOREIGN KEY(sent_by_user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plot_versions (
  plot_version_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  plot_id BIGINT UNSIGNED NOT NULL,
  version_no INT UNSIGNED NOT NULL,
  workflow_status ENUM('SUBMITTED','CHANGES_REQUESTED','APPROVED','PUBLISHED','ARCHIVED') NOT NULL DEFAULT 'SUBMITTED',
  snapshot_json LONGTEXT NOT NULL,
  submitted_by_user_id BIGINT UNSIGNED NOT NULL,
  reviewed_by_user_id BIGINT UNSIGNED NULL,
  review_notes TEXT NULL,
  created_at_utc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reviewed_at_utc DATETIME NULL,
  UNIQUE KEY uq_plot_version(plot_id,version_no),
  KEY idx_plot_review_queue(workflow_status,created_at_utc),
  CONSTRAINT fk_plot_version_plot FOREIGN KEY(plot_id) REFERENCES plots(plot_id) ON DELETE CASCADE,
  CONSTRAINT fk_plot_version_submitter FOREIGN KEY(submitted_by_user_id) REFERENCES users(user_id),
  CONSTRAINT fk_plot_version_reviewer FOREIGN KEY(reviewed_by_user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS story_audit_runs (
  story_audit_run_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  plot_id BIGINT UNSIGNED NOT NULL,
  plot_version_id BIGINT UNSIGNED NULL,
  requested_by_user_id BIGINT UNSIGNED NOT NULL,
  audit_text LONGTEXT NOT NULL,
  blocking_error_count INT UNSIGNED NOT NULL DEFAULT 0,
  warning_count INT UNSIGNED NOT NULL DEFAULT 0,
  created_at_utc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_story_audit_runs(plot_id,created_at_utc),
  CONSTRAINT fk_story_audit_plot FOREIGN KEY(plot_id) REFERENCES plots(plot_id) ON DELETE CASCADE,
  CONSTRAINT fk_story_audit_version FOREIGN KEY(plot_version_id) REFERENCES plot_versions(plot_version_id) ON DELETE SET NULL,
  CONSTRAINT fk_story_audit_user FOREIGN KEY(requested_by_user_id) REFERENCES users(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO platform_story_audit_rules(rule_code,priority_no,category_code,severity,automated_check_code,guidance_text) VALUES
('VIEWER_POV_RENDERING',35,'IMMERSION','ERROR','VIEWER_POV','Render every public and private event as the viewing player would read it: you/your for that player and authored pronouns for everyone else.'),
('MECHANICAL_LANGUAGE',45,'IMMERSION','ERROR','MECHANICAL_LANGUAGE','Reject player-facing prose containing raw state keys, factor summaries, stat deltas, controller labels, or lifecycle tokens such as ACHIEVED.'),
('DEPENDENCY_REACHABILITY',25,'STORY','ERROR','DEPENDENCY_GRAPH','Every condition must reference an authored source, every effect must have a valid target, and every major outcome must be reachable.'),
('SUBMITTED_REVISION',5,'WORKFLOW','ERROR','REVISION_SNAPSHOT','Review a fixed submitted revision so approved content cannot change beneath the reviewer.')
ON DUPLICATE KEY UPDATE priority_no=VALUES(priority_no),category_code=VALUES(category_code),severity=VALUES(severity),automated_check_code=VALUES(automated_check_code),guidance_text=VALUES(guidance_text),active=1;

-- Normalize the generic authoring vocabularies so the studio can edit every
-- condition/effect type supported by the engine, regardless of migration age.
ALTER TABLE plot_dynamic_opportunity_conditions
  MODIFY source_type ENUM('WORLD_STATE','FACTOR','LOCATION','GOAL','FEAR','STAT','KNOWLEDGE','RELATIONSHIP','PHASE','ACTION_COUNT','PLAYER_ACTION_COUNT','OPTION_CHOICE_COUNT') NOT NULL DEFAULT 'WORLD_STATE';
ALTER TABLE plot_option_effects
  MODIFY effect_scope ENUM('FACTOR','STAT','LOCATION','KNOWLEDGE','RELATIONSHIP','WORLD_STATE','GOAL','FEAR') NOT NULL;

INSERT IGNORE INTO schema_migrations(migration) VALUES('064_writer_studio_workflow_and_themes.sql');
