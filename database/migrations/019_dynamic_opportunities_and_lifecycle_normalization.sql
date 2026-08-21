-- Dynamic opportunities are state-driven player decision windows that can appear between fixed story beats.
-- Plot data defines eligibility, text, options and effects. Engine code remains story-agnostic.

CREATE TABLE IF NOT EXISTS plot_dynamic_opportunities (
  dynamic_opportunity_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  plot_id BIGINT UNSIGNED NOT NULL,
  opportunity_code VARCHAR(120) NOT NULL,
  role_code VARCHAR(100) NOT NULL,
  prompt_text TEXT NOT NULL,
  window_seconds INT UNSIGNED NOT NULL DEFAULT 35,
  once_only TINYINT(1) NOT NULL DEFAULT 1,
  priority INT NOT NULL DEFAULT 100,
  active TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_dynamic_opportunity(plot_id,opportunity_code),
  KEY idx_dynamic_opportunity(plot_id,role_code,priority),
  CONSTRAINT fk_pdo_plot FOREIGN KEY(plot_id) REFERENCES plots(plot_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plot_dynamic_opportunity_conditions (
  dynamic_condition_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  dynamic_opportunity_id BIGINT UNSIGNED NOT NULL,
  source_type ENUM('WORLD_STATE','LOCATION','GOAL','FEAR','KNOWLEDGE','PHASE','ACTION_COUNT') NOT NULL DEFAULT 'WORLD_STATE',
  source_key VARCHAR(190) NULL,
  operator ENUM('EQ','NEQ','IN','NOT_IN','EXISTS','NOT_EXISTS','LT','LTE','GT','GTE') NOT NULL DEFAULT 'EQ',
  compare_value VARCHAR(500) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_pdoc_opportunity FOREIGN KEY(dynamic_opportunity_id) REFERENCES plot_dynamic_opportunities(dynamic_opportunity_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plot_dynamic_opportunity_options (
  dynamic_option_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  dynamic_opportunity_id BIGINT UNSIGNED NOT NULL,
  option_code VARCHAR(100) NOT NULL,
  label VARCHAR(500) NOT NULL,
  result_text TEXT NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  UNIQUE KEY uq_dynamic_option(dynamic_opportunity_id,option_code),
  CONSTRAINT fk_pdoo_opportunity FOREIGN KEY(dynamic_opportunity_id) REFERENCES plot_dynamic_opportunities(dynamic_opportunity_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plot_dynamic_option_effects (
  dynamic_effect_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  dynamic_option_id BIGINT UNSIGNED NOT NULL,
  effect_scope ENUM('FACTOR','STAT','LOCATION','KNOWLEDGE','RELATIONSHIP','WORLD_STATE','GOAL','FEAR') NOT NULL,
  target_code VARCHAR(190) NOT NULL,
  delta_value INT NULL,
  set_value VARCHAR(500) NULL,
  detail_text TEXT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_pdoe_option FOREIGN KEY(dynamic_option_id) REFERENCES plot_dynamic_opportunity_options(dynamic_option_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS simulation_dynamic_opportunities (
  simulation_dynamic_opportunity_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  simulation_id BIGINT UNSIGNED NOT NULL,
  dynamic_opportunity_id BIGINT UNSIGNED NOT NULL,
  character_id BIGINT UNSIGNED NOT NULL,
  decision_id BIGINT UNSIGNED NULL,
  created_at_second INT UNSIGNED NOT NULL,
  status ENUM('OPENED','CHOSEN','EXPIRED','CLOSED') NOT NULL DEFAULT 'OPENED',
  UNIQUE KEY uq_sim_dynamic_once(simulation_id,dynamic_opportunity_id,character_id),
  KEY idx_sim_dynamic(simulation_id,character_id,status),
  CONSTRAINT fk_sdo_sim FOREIGN KEY(simulation_id) REFERENCES simulations(simulation_id) ON DELETE CASCADE,
  CONSTRAINT fk_sdo_rule FOREIGN KEY(dynamic_opportunity_id) REFERENCES plot_dynamic_opportunities(dynamic_opportunity_id) ON DELETE CASCADE,
  CONSTRAINT fk_sdo_char FOREIGN KEY(character_id) REFERENCES characters(character_id) ON DELETE CASCADE,
  CONSTRAINT fk_sdo_decision FOREIGN KEY(decision_id) REFERENCES decisions(decision_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @plot_id=(SELECT plot_id FROM plots WHERE code='CONCEALED_FAILURE' LIMIT 1);

-- Alden's externalization should be represented by the generic motive lifecycle, not direct GOAL/FEAR effects.
SET @t=(SELECT template_id FROM plot_decision_templates WHERE plot_id=@plot_id AND event_code='REAR_EXIT' AND role_code='ALDEN' LIMIT 1);
SET @o=(SELECT template_option_id FROM plot_decision_template_options WHERE template_id=@t AND label='Use the remaining connection to send the evidence summary outside Bellweather.' LIMIT 1);
DELETE FROM plot_option_effects WHERE template_option_id=@o AND effect_scope IN ('GOAL','FEAR');

-- Bellweather example 1: unresolved rescue can recruit Wren dynamically after an earlier missed opportunity.
INSERT INTO plot_dynamic_opportunities(plot_id,opportunity_code,role_code,prompt_text,window_seconds,once_only,priority,active)
VALUES(@plot_id,'WREN_RESCUE_GAP','WREN','The injured guest still lacks enough committed help. You are already near the east stairwell. Do you step into the rescue now, inspect the hazard, or keep watching?',35,1,20,1)
ON DUPLICATE KEY UPDATE prompt_text=VALUES(prompt_text),window_seconds=VALUES(window_seconds),priority=VALUES(priority),active=1;
SET @d=(SELECT dynamic_opportunity_id FROM plot_dynamic_opportunities WHERE plot_id=@plot_id AND opportunity_code='WREN_RESCUE_GAP');
DELETE FROM plot_dynamic_opportunity_conditions WHERE dynamic_opportunity_id=@d;
INSERT INTO plot_dynamic_opportunity_conditions(dynamic_opportunity_id,source_type,source_key,operator,compare_value,sort_order) VALUES
(@d,'WORLD_STATE','GUEST_HELPERS','EQ','1',1),
(@d,'WORLD_STATE','GUEST_STATUS','IN','INJURED,STABILIZED,AT_RISK',2),
(@d,'LOCATION',NULL,'EQ','East Stairwell',3),
(@d,'PHASE',NULL,'IN','INCITING_INCIDENT,RISING_ACTION',4),
(@d,'ACTION_COUNT',NULL,'LTE','0',5);
DELETE FROM plot_dynamic_opportunity_options WHERE dynamic_opportunity_id=@d;
INSERT INTO plot_dynamic_opportunity_options(dynamic_opportunity_id,option_code,label,result_text,sort_order) VALUES
(@d,'JOIN_RESCUE','Commit to become the second helper for the injured guest.','You stop observing and commit yourself to the rescue effort.',1),
(@d,'CHECK_HAZARD','Inspect the east-side hazard before committing to movement.','You use your emergency-response experience to assess whether the east side is becoming more dangerous.',2),
(@d,'KEEP_WATCH','Keep watching and preserve your attention for the larger crisis.','You remain alert but do not join the rescue yet.',3);
SET @op=(SELECT dynamic_option_id FROM plot_dynamic_opportunity_options WHERE dynamic_opportunity_id=@d AND option_code='JOIN_RESCUE');
INSERT INTO plot_dynamic_option_effects(dynamic_option_id,effect_scope,target_code,set_value,detail_text,sort_order) VALUES
(@op,'WORLD_STATE','GUEST_HELPERS','2','Wren becomes the second committed helper for the injured guest.',1),
(@op,'WORLD_STATE','GUEST_SECOND_HELPER','WREN','Wren is now the identified second helper.',2),
(@op,'GOAL','SELF','Help get the injured guest safely out of the east wing.','Wren chooses the rescue as his immediate priority.',3);
SET @op=(SELECT dynamic_option_id FROM plot_dynamic_opportunity_options WHERE dynamic_opportunity_id=@d AND option_code='CHECK_HAZARD');
INSERT INTO plot_dynamic_option_effects(dynamic_option_id,effect_scope,target_code,set_value,detail_text,sort_order) VALUES
(@op,'KNOWLEDGE','east_hazard_assessed','The east side is becoming progressively less stable.','Wren confirms that delay on the east side carries increasing physical risk.',1),
(@op,'FACTOR','SAFETY',NULL,'Wren’s hazard assessment gives the group a clearer picture of the physical risk.',2);
UPDATE plot_dynamic_option_effects SET delta_value=2 WHERE dynamic_option_id=@op AND effect_scope='FACTOR' AND target_code='SAFETY';

-- Bellweather example 2: Niko gets another leadership opportunity when the group is fragmenting.
INSERT INTO plot_dynamic_opportunities(plot_id,opportunity_code,role_code,prompt_text,window_seconds,once_only,priority,active)
VALUES(@plot_id,'NIKO_LEADERSHIP_GAP','NIKO','The group is fragmenting and people still look to you for operational authority. Do you organize the evacuation, challenge Farah’s version, or remain neutral?',35,1,30,1)
ON DUPLICATE KEY UPDATE prompt_text=VALUES(prompt_text),window_seconds=VALUES(window_seconds),priority=VALUES(priority),active=1;
SET @d=(SELECT dynamic_opportunity_id FROM plot_dynamic_opportunities WHERE plot_id=@plot_id AND opportunity_code='NIKO_LEADERSHIP_GAP');
DELETE FROM plot_dynamic_opportunity_conditions WHERE dynamic_opportunity_id=@d;
INSERT INTO plot_dynamic_opportunity_conditions(dynamic_opportunity_id,source_type,source_key,operator,compare_value,sort_order) VALUES
(@d,'PHASE',NULL,'IN','INCITING_INCIDENT,RISING_ACTION',1),
(@d,'ACTION_COUNT',NULL,'LTE','0',2);
DELETE FROM plot_dynamic_opportunity_options WHERE dynamic_opportunity_id=@d;
INSERT INTO plot_dynamic_opportunity_options(dynamic_opportunity_id,option_code,label,result_text,sort_order) VALUES
(@d,'TAKE_COMMAND','Take command of the evacuation and assign people to immediate safety tasks.','You use your operational authority to give the group a concrete direction.',1),
(@d,'CHALLENGE_FARAH','Tell Farah the certificate conflict must be explained before you keep backing her.','You publicly separate operational leadership from personal loyalty to Farah.',2),
(@d,'STAY_NEUTRAL','Stay neutral and avoid choosing between the competing priorities.','You keep your authority in reserve rather than committing it.',3);
SET @op=(SELECT dynamic_option_id FROM plot_dynamic_opportunity_options WHERE dynamic_opportunity_id=@d AND option_code='TAKE_COMMAND');
INSERT INTO plot_dynamic_option_effects(dynamic_option_id,effect_scope,target_code,delta_value,detail_text,sort_order) VALUES
(@op,'FACTOR','TRUST',8,'Clear operational leadership improves coordination.',1),
(@op,'FACTOR','SAFETY',4,'The group receives clearer evacuation direction.',2);
SET @op=(SELECT dynamic_option_id FROM plot_dynamic_opportunity_options WHERE dynamic_opportunity_id=@d AND option_code='CHALLENGE_FARAH');
INSERT INTO plot_dynamic_option_effects(dynamic_option_id,effect_scope,target_code,delta_value,detail_text,sort_order) VALUES
(@op,'FACTOR','SUSPICION',8,'Niko’s public doubt makes the certificate conflict harder to dismiss.',1),
(@op,'FACTOR','CONTROL',-6,'Farah loses some authority when Niko stops automatically backing her.',2);

INSERT IGNORE INTO schema_migrations(migration) VALUES('019_dynamic_opportunities_and_lifecycle_normalization.sql');