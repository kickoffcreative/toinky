-- Generic authorship, platform roles, pronouns, optional writer media, and immersion audit rules.
-- Story-specific values are data; the runtime remains plot-agnostic.

SET @has_author=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='plots' AND COLUMN_NAME='author_user_id');
SET @sql=IF(@has_author=0,'ALTER TABLE plots ADD COLUMN author_user_id BIGINT UNSIGNED NULL AFTER plot_id','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_role_pronouns=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='plot_roles' AND COLUMN_NAME='pronoun_set');
SET @sql=IF(@has_role_pronouns=0,'ALTER TABLE plot_roles ADD COLUMN pronoun_set ENUM(''HE'',''SHE'',''IT'') NOT NULL DEFAULT ''IT'' AFTER name','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_character_pronouns=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='characters' AND COLUMN_NAME='pronoun_set');
SET @sql=IF(@has_character_pronouns=0,'ALTER TABLE characters ADD COLUMN pronoun_set ENUM(''HE'',''SHE'',''IT'') NOT NULL DEFAULT ''IT'' AFTER name','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS platform_roles (
  platform_role_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  role_code VARCHAR(40) NOT NULL,
  label_text VARCHAR(100) NOT NULL,
  scope_type ENUM('PLATFORM','GAME') NOT NULL,
  description_text VARCHAR(500) NOT NULL,
  UNIQUE KEY uq_platform_role_code(role_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_platform_roles (
  user_id BIGINT UNSIGNED NOT NULL,
  platform_role_id BIGINT UNSIGNED NOT NULL,
  assigned_by_user_id BIGINT UNSIGNED NULL,
  assigned_at_utc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY(user_id,platform_role_id),
  CONSTRAINT fk_upr_user FOREIGN KEY(user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  CONSTRAINT fk_upr_role FOREIGN KEY(platform_role_id) REFERENCES platform_roles(platform_role_id) ON DELETE CASCADE,
  CONSTRAINT fk_upr_assigner FOREIGN KEY(assigned_by_user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO platform_roles(role_code,label_text,scope_type,description_text) VALUES
('ADMIN','Administrator','PLATFORM','Manages the platform, users, simulations, and every story.'),
('WRITER','Story Writer','PLATFORM','Creates and maintains story data, prose, validation, and optional artwork.'),
('GAME_HOST','Game Host','GAME','Creates a lobby, invites players, and starts one specific game.'),
('PLAYER','Player','GAME','Joins a game and inhabits one selected character.')
ON DUPLICATE KEY UPDATE label_text=VALUES(label_text),scope_type=VALUES(scope_type),description_text=VALUES(description_text);

INSERT IGNORE INTO user_platform_roles(user_id,platform_role_id,assigned_by_user_id)
SELECT u.user_id,r.platform_role_id,u.user_id FROM users u JOIN platform_roles r ON r.role_code='ADMIN' WHERE u.is_admin=1;
INSERT IGNORE INTO user_platform_roles(user_id,platform_role_id,assigned_by_user_id)
SELECT u.user_id,r.platform_role_id,u.user_id FROM users u JOIN platform_roles r ON r.role_code='WRITER' WHERE LOWER(u.email)='goldwynlau@gmail.com';
UPDATE plots SET author_user_id=(SELECT user_id FROM users WHERE LOWER(email)='goldwynlau@gmail.com' LIMIT 1)
WHERE code IN('EMPTY_THRONE','CONCEALED_FAILURE');

CREATE TABLE IF NOT EXISTS plot_media_assets (
  media_asset_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  plot_id BIGINT UNSIGNED NOT NULL,
  uploaded_by_user_id BIGINT UNSIGNED NOT NULL,
  asset_code VARCHAR(100) NOT NULL,
  placement ENUM('CHARACTER','OPENING','SCENE','TURNING_POINT','OUTCOME','ENDING','OTHER') NOT NULL,
  role_code VARCHAR(100) NULL,
  event_code VARCHAR(100) NULL,
  scene_second INT UNSIGNED NULL,
  condition_state_key VARCHAR(190) NULL,
  condition_operator ENUM('EQ','NEQ','IN','NOT_IN','EXISTS','NOT_EXISTS') NULL,
  condition_value VARCHAR(500) NULL,
  title_text VARCHAR(180) NULL,
  alt_text VARCHAR(500) NOT NULL,
  caption_text TEXT NULL,
  file_path VARCHAR(500) NOT NULL,
  mime_type VARCHAR(100) NOT NULL,
  file_size_bytes INT UNSIGNED NOT NULL,
  sort_order INT NOT NULL DEFAULT 100,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at_utc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at_utc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_plot_media_code(plot_id,asset_code),
  KEY idx_plot_media_render(plot_id,placement,role_code,event_code,scene_second,active),
  CONSTRAINT fk_plot_media_plot FOREIGN KEY(plot_id) REFERENCES plots(plot_id) ON DELETE CASCADE,
  CONSTRAINT fk_plot_media_uploader FOREIGN KEY(uploaded_by_user_id) REFERENCES users(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS platform_story_audit_rules (
  audit_rule_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  rule_code VARCHAR(100) NOT NULL,
  priority_no INT UNSIGNED NOT NULL,
  category_code VARCHAR(60) NOT NULL,
  severity ENUM('ERROR','WARNING','ADVISORY') NOT NULL,
  automated_check_code VARCHAR(100) NULL,
  guidance_text TEXT NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_story_audit_rule(rule_code),
  KEY idx_story_audit_priority(active,priority_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO platform_story_audit_rules(rule_code,priority_no,category_code,severity,automated_check_code,guidance_text) VALUES
('ENGINE_INTEGRITY',10,'ENGINE','ERROR','SIMULATION_INTEGRITY','Run deterministic and random simulations first. There must be no impossible state, dependency, transition, or integrity warning.'),
('BRANCH_COVERAGE',20,'STORY','ERROR','DECISION_COVERAGE','Exercise every option and major conditional branch, including no-action, and verify that later opportunities respect earlier choices.'),
('PLAYER_CONTINUITY',30,'IMMERSION','ERROR','PLAYER_CONTINUITY','From each playable character, confirm that the timeline clearly explains the scenario, actors, decisions, consequences, current location, and current goal without privileged knowledge.'),
('PLAYER_POV',40,'IMMERSION','WARNING','PRONOUN_COVERAGE','Use you and your for the player, names plus he, she, or it for everyone else, and name every helper or participant when known.'),
('PLAYER_PROSE',50,'IMMERSION','WARNING','MECHANICAL_LANGUAGE','Player prose must not expose factor totals, raw state keys, stat deltas, controller labels, or other engine vocabulary.'),
('EPILOGUE_QUALITY',60,'IMMERSION','WARNING','EPILOGUE_LENGTH','End with a vivid 300–500 word database-authored epilogue covering the immediate closing image, the player’s fate, named-character consequences, the wider aftermath, and a lingering hook.'),
('MOBILE_DECISIONS',70,'INTERFACE','ERROR','MANUAL_MOBILE','On a phone-sized screen, confirm that open decisions remain visible and usable without scrolling past the entire timeline.'),
('MEDIA_ACCESSIBILITY',80,'MEDIA','WARNING','MEDIA_METADATA','Every uploaded image needs useful alternative text. Missing images never block publication; only available, active images appear to players.'),
('AI_BEHAVIOR',90,'AI','WARNING','AI_DECISION_AUDIT','Audit AI-controlled characters for legal choices, character-specific knowledge, tradeoffs, varied confidence, and reactions to other characters.'),
('REPLAYABILITY',100,'STORY','ADVISORY','OUTCOME_DIVERSITY','Run batches across different control and focus modes. Confirm meaningful, coherent outcome variation without sacrificing causal integrity.')
ON DUPLICATE KEY UPDATE priority_no=VALUES(priority_no),category_code=VALUES(category_code),severity=VALUES(severity),automated_check_code=VALUES(automated_check_code),guidance_text=VALUES(guidance_text),active=1;

UPDATE plot_roles pr JOIN plots p ON p.plot_id=pr.plot_id SET pr.pronoun_set='HE'
WHERE p.code='EMPTY_THRONE' AND pr.role_code IN('ROWAN','ELIAN','TOMAS');
UPDATE plot_roles pr JOIN plots p ON p.plot_id=pr.plot_id SET pr.pronoun_set='SHE'
WHERE p.code='EMPTY_THRONE' AND pr.role_code IN('MARA','QUEEN');
UPDATE plot_roles pr JOIN plots p ON p.plot_id=pr.plot_id SET pr.pronoun_set='IT'
WHERE p.code='EMPTY_THRONE' AND pr.role_code='HAND';
UPDATE plot_roles pr JOIN plots p ON p.plot_id=pr.plot_id SET pr.pronoun_set='SHE'
WHERE p.code='CONCEALED_FAILURE' AND pr.role_code='FARAH';
UPDATE plot_roles pr JOIN plots p ON p.plot_id=pr.plot_id SET pr.pronoun_set='HE'
WHERE p.code='CONCEALED_FAILURE' AND pr.role_code IN('ALDEN','KELLAN','NIKO','WREN');

-- Longer story-specific closing movements. ResolutionService selects these generically by section.
SET @empty=(SELECT plot_id FROM plots WHERE code='EMPTY_THRONE' LIMIT 1);
INSERT INTO plot_resolution_fragments(plot_id,section_code,section_order,priority,fragment_text)
SELECT @empty,'AFTERMATH',80,100,'By dusk, the palace has learned a new silence. Servants carry trays through corridors where every doorway has become a listening post, and councillors speak in careful voices beneath portraits of monarchs who never had to explain how they reached the throne. The damaged succession has not vanished with the ceremony or its failure; it has entered the city as rumor, oath, grievance, and memory. Rowan, Elian, Mara, and Tomas must each live with the part they played, while clerks begin copying testimony that may outlast every person in the room. Beyond the palace gates, bells travel over rooftops and people pause to decide whether they have heard an ending or the first warning of another contest.' WHERE @empty IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_resolution_fragments WHERE plot_id=@empty AND section_code='AFTERMATH');
INSERT INTO plot_resolution_fragments(plot_id,section_code,section_order,priority,fragment_text)
SELECT @empty,'INVITATION',90,100,'Long after the court disperses, one unanswered question remains in the archive’s locked dark: which choice truly made the ruler—the bloodline, the evidence, the people who acted, or the people who waited? In another telling, a single hand might have guarded a different door, trusted a different witness, or spoken before silence hardened into law. The Empty Throne is occupied or abandoned for tonight, but its shadow is patient, and it remembers every road not taken.' WHERE @empty IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_resolution_fragments WHERE plot_id=@empty AND section_code='INVITATION');

SET @bell=(SELECT plot_id FROM plots WHERE code='CONCEALED_FAILURE' LIMIT 1);
INSERT INTO plot_resolution_fragments(plot_id,section_code,section_order,priority,fragment_text)
SELECT @bell,'AFTERMATH',80,100,'Morning reaches Bellweather House reluctantly. The storm has stripped branches across the drive and left brown water standing in the lobby, reflecting chandeliers that no longer look elegant. Blankets, broken glass, and muddy footprints preserve the shape of the night better than any official account will. Alden, Farah, Kellan, Niko, and Wren leave with different versions of what happened, but none of them leaves untouched. The injured guest’s condition, the surviving report, and the people who saw too much will determine whether Bellweather reopens as a monument to endurance or remains closed as a crime scene. By breakfast, telephones are ringing beyond the mountain, and the first outsiders are already asking who knew the building was unsafe before the walls began to move.' WHERE @bell IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_resolution_fragments WHERE plot_id=@bell AND section_code='AFTERMATH');
INSERT INTO plot_resolution_fragments(plot_id,section_code,section_order,priority,fragment_text)
SELECT @bell,'INVITATION',90,100,'Somewhere beneath the wet plaster, another sound answers the settling house—a slow metallic knock from a service passage no one inspected. It may be nothing. Bellweather has survived for years by teaching people to call dangerous things ordinary. Yet every person who stood in that lobby now knows how quickly a private compromise can become a public disaster, and how differently the night might have ended if one of them had chosen another door, another ally, or another truth. The mountain keeps the rest of its secrets for the next storm.' WHERE @bell IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_resolution_fragments WHERE plot_id=@bell AND section_code='INVITATION');

-- Name the second rescuer in endings instead of reducing participation to a count.
INSERT INTO plot_resolution_fragments(plot_id,section_code,section_order,priority,fragment_text)
SELECT @bell,'PEOPLE',10,1,'Kellan West becomes the second pair of hands at the stretcher. With Kellan and the first rescuer carrying the weight together, the injured guest reaches the west evacuation point before the corridor fails behind them.' WHERE @bell IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_resolution_fragments WHERE plot_id=@bell AND section_code='PEOPLE' AND priority=1);
SET @f=(SELECT resolution_fragment_id FROM plot_resolution_fragments WHERE plot_id=@bell AND section_code='PEOPLE' AND priority=1 LIMIT 1);
INSERT INTO plot_resolution_conditions(resolution_fragment_id,state_key,operator,compare_value,sort_order)
SELECT @f,'GUEST_STATUS','EQ','EVACUATED',1 WHERE @f IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_resolution_conditions WHERE resolution_fragment_id=@f);
INSERT INTO plot_resolution_conditions(resolution_fragment_id,state_key,operator,compare_value,sort_order)
SELECT @f,'GUEST_SECOND_HELPER','EQ','KELLAN',2 WHERE @f IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_resolution_conditions WHERE resolution_fragment_id=@f AND state_key='GUEST_SECOND_HELPER');

INSERT INTO plot_resolution_fragments(plot_id,section_code,section_order,priority,fragment_text)
SELECT @bell,'PEOPLE',10,2,'Wren Harker answers the call for another pair of hands. Wren and the first rescuer keep the stretcher level through the flooded passage, and the injured guest reaches the west evacuation point before the house can close the route.' WHERE @bell IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_resolution_fragments WHERE plot_id=@bell AND section_code='PEOPLE' AND priority=2);
SET @f=(SELECT resolution_fragment_id FROM plot_resolution_fragments WHERE plot_id=@bell AND section_code='PEOPLE' AND priority=2 LIMIT 1);
INSERT INTO plot_resolution_conditions(resolution_fragment_id,state_key,operator,compare_value,sort_order)
SELECT @f,'GUEST_STATUS','EQ','EVACUATED',1 WHERE @f IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_resolution_conditions WHERE resolution_fragment_id=@f);
INSERT INTO plot_resolution_conditions(resolution_fragment_id,state_key,operator,compare_value,sort_order)
SELECT @f,'GUEST_SECOND_HELPER','EQ','WREN',2 WHERE @f IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_resolution_conditions WHERE resolution_fragment_id=@f AND state_key='GUEST_SECOND_HELPER');

INSERT INTO plot_resolution_fragments(plot_id,section_code,section_order,priority,fragment_text)
SELECT @bell,'PEOPLE',10,3,'Niko Lang leaves the argument and takes the open side of the stretcher. Niko and the first rescuer move the injured guest through the west route together, turning a shouted plan into an evacuation that the room can no longer ignore.' WHERE @bell IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_resolution_fragments WHERE plot_id=@bell AND section_code='PEOPLE' AND priority=3);
SET @f=(SELECT resolution_fragment_id FROM plot_resolution_fragments WHERE plot_id=@bell AND section_code='PEOPLE' AND priority=3 LIMIT 1);
INSERT INTO plot_resolution_conditions(resolution_fragment_id,state_key,operator,compare_value,sort_order)
SELECT @f,'GUEST_STATUS','EQ','EVACUATED',1 WHERE @f IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_resolution_conditions WHERE resolution_fragment_id=@f);
INSERT INTO plot_resolution_conditions(resolution_fragment_id,state_key,operator,compare_value,sort_order)
SELECT @f,'GUEST_SECOND_HELPER','EQ','NIKO',2 WHERE @f IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_resolution_conditions WHERE resolution_fragment_id=@f AND state_key='GUEST_SECOND_HELPER');

INSERT IGNORE INTO schema_migrations(migration) VALUES('063_story_authorship_media_and_immersion.sql');
