-- Generic character motive lifecycle. Plot data defines when a goal/fear changes state;
-- engine code only evaluates conditions and applies the configured lifecycle result.

CREATE TABLE IF NOT EXISTS character_motive_state (
  character_motive_state_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  simulation_id BIGINT UNSIGNED NOT NULL,
  character_id BIGINT UNSIGNED NOT NULL,
  motive_type ENUM('GOAL','FEAR') NOT NULL,
  motive_text TEXT NOT NULL,
  lifecycle_status ENUM('ACTIVE','ACHIEVED','FAILED','ABANDONED','INTENSIFIED','REDUCED','RESOLVED','REALIZED','REPLACED') NOT NULL DEFAULT 'ACTIVE',
  started_at_second INT UNSIGNED NOT NULL DEFAULT 0,
  ended_at_second INT UNSIGNED NULL,
  UNIQUE KEY uq_current_motive(character_id,motive_type),
  KEY idx_motive_sim(simulation_id,character_id,motive_type),
  CONSTRAINT fk_cms_sim FOREIGN KEY(simulation_id) REFERENCES simulations(simulation_id) ON DELETE CASCADE,
  CONSTRAINT fk_cms_char FOREIGN KEY(character_id) REFERENCES characters(character_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS character_motive_history (
  character_motive_history_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  simulation_id BIGINT UNSIGNED NOT NULL,
  character_id BIGINT UNSIGNED NOT NULL,
  motive_type ENUM('GOAL','FEAR') NOT NULL,
  motive_text TEXT NOT NULL,
  lifecycle_status ENUM('ACTIVE','ACHIEVED','FAILED','ABANDONED','INTENSIFIED','REDUCED','RESOLVED','REALIZED','REPLACED') NOT NULL,
  event_second INT UNSIGNED NOT NULL,
  reason_text TEXT NULL,
  created_at_utc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_motive_history(character_id,event_second,character_motive_history_id),
  CONSTRAINT fk_cmh_sim FOREIGN KEY(simulation_id) REFERENCES simulations(simulation_id) ON DELETE CASCADE,
  CONSTRAINT fk_cmh_char FOREIGN KEY(character_id) REFERENCES characters(character_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plot_motive_lifecycle_rules (
  motive_rule_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  plot_id BIGINT UNSIGNED NOT NULL,
  role_code VARCHAR(100) NOT NULL,
  motive_type ENUM('GOAL','FEAR') NOT NULL,
  match_text VARCHAR(500) NULL,
  terminal_status ENUM('ACHIEVED','FAILED','ABANDONED','INTENSIFIED','REDUCED','RESOLVED','REALIZED') NOT NULL,
  replacement_text VARCHAR(500) NULL,
  reason_text TEXT NULL,
  priority INT NOT NULL DEFAULT 100,
  active TINYINT(1) NOT NULL DEFAULT 1,
  KEY idx_motive_rules(plot_id,role_code,motive_type,priority),
  CONSTRAINT fk_pmlr_plot FOREIGN KEY(plot_id) REFERENCES plots(plot_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plot_motive_lifecycle_conditions (
  motive_condition_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  motive_rule_id BIGINT UNSIGNED NOT NULL,
  state_key VARCHAR(190) NOT NULL,
  operator ENUM('EQ','NEQ','IN','NOT_IN','EXISTS','NOT_EXISTS') NOT NULL DEFAULT 'EQ',
  compare_value VARCHAR(500) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_pmlc_rule FOREIGN KEY(motive_rule_id) REFERENCES plot_motive_lifecycle_rules(motive_rule_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @plot_id=(SELECT plot_id FROM plots WHERE code='CONCEALED_FAILURE' LIMIT 1);

-- Kellan's rescue goal is achieved when the guest is evacuated; a broader protection goal replaces it.
INSERT INTO plot_motive_lifecycle_rules(plot_id,role_code,motive_type,match_text,terminal_status,replacement_text,reason_text,priority,active)
SELECT @plot_id,'KELLAN','GOAL','Get the injured guest safely out of the east wing.','ACHIEVED','Help everyone clear the remaining danger safely.','The injured guest has reached the west evacuation point.',20,1
WHERE NOT EXISTS(SELECT 1 FROM plot_motive_lifecycle_rules WHERE plot_id=@plot_id AND role_code='KELLAN' AND motive_type='GOAL' AND match_text='Get the injured guest safely out of the east wing.');
SET @r=(SELECT motive_rule_id FROM plot_motive_lifecycle_rules WHERE plot_id=@plot_id AND role_code='KELLAN' AND motive_type='GOAL' AND match_text='Get the injured guest safely out of the east wing.' LIMIT 1);
INSERT INTO plot_motive_lifecycle_conditions(motive_rule_id,state_key,operator,compare_value,sort_order)
SELECT @r,'GUEST_STATUS','EQ','EVACUATED',1 FROM DUAL WHERE NOT EXISTS(SELECT 1 FROM plot_motive_lifecycle_conditions WHERE motive_rule_id=@r);

INSERT INTO plot_motive_lifecycle_rules(plot_id,role_code,motive_type,match_text,terminal_status,replacement_text,reason_text,priority,active)
SELECT @plot_id,'KELLAN','FEAR','The injured guest being trapped by another slope movement.','RESOLVED','Another structural failure before everyone is clear.','The specific danger of the injured guest being trapped has ended, but the resort remains unstable.',20,1
WHERE NOT EXISTS(SELECT 1 FROM plot_motive_lifecycle_rules WHERE plot_id=@plot_id AND role_code='KELLAN' AND motive_type='FEAR' AND match_text='The injured guest being trapped by another slope movement.');
SET @r=(SELECT motive_rule_id FROM plot_motive_lifecycle_rules WHERE plot_id=@plot_id AND role_code='KELLAN' AND motive_type='FEAR' AND match_text='The injured guest being trapped by another slope movement.' LIMIT 1);
INSERT INTO plot_motive_lifecycle_conditions(motive_rule_id,state_key,operator,compare_value,sort_order)
SELECT @r,'GUEST_STATUS','EQ','EVACUATED',1 FROM DUAL WHERE NOT EXISTS(SELECT 1 FROM plot_motive_lifecycle_conditions WHERE motive_rule_id=@r);

-- Leila's evacuation objective closes at the same world-state milestone.
INSERT INTO plot_motive_lifecycle_rules(plot_id,role_code,motive_type,match_text,terminal_status,replacement_text,reason_text,priority,active)
SELECT @plot_id,'LEILA','GOAL','Get the injured guest to the west evacuation point.','ACHIEVED','Keep the evacuated guest stable and help others clear the remaining danger.','Leila successfully completes the evacuation objective.',20,1
WHERE NOT EXISTS(SELECT 1 FROM plot_motive_lifecycle_rules WHERE plot_id=@plot_id AND role_code='LEILA' AND motive_type='GOAL' AND match_text='Get the injured guest to the west evacuation point.');
SET @r=(SELECT motive_rule_id FROM plot_motive_lifecycle_rules WHERE plot_id=@plot_id AND role_code='LEILA' AND motive_type='GOAL' AND match_text='Get the injured guest to the west evacuation point.' LIMIT 1);
INSERT INTO plot_motive_lifecycle_conditions(motive_rule_id,state_key,operator,compare_value,sort_order)
SELECT @r,'GUEST_STATUS','EQ','EVACUATED',1 FROM DUAL WHERE NOT EXISTS(SELECT 1 FROM plot_motive_lifecycle_conditions WHERE motive_rule_id=@r);

INSERT INTO plot_motive_lifecycle_rules(plot_id,role_code,motive_type,match_text,terminal_status,replacement_text,reason_text,priority,active)
SELECT @plot_id,'LEILA','FEAR','The evacuation failing before the guest reaches safety.','RESOLVED','Another structural failure harming people who remain inside.','The evacuation succeeded, resolving Leila’s immediate fear about the injured guest.',20,1
WHERE NOT EXISTS(SELECT 1 FROM plot_motive_lifecycle_rules WHERE plot_id=@plot_id AND role_code='LEILA' AND motive_type='FEAR' AND match_text='The evacuation failing before the guest reaches safety.');
SET @r=(SELECT motive_rule_id FROM plot_motive_lifecycle_rules WHERE plot_id=@plot_id AND role_code='LEILA' AND motive_type='FEAR' AND match_text='The evacuation failing before the guest reaches safety.' LIMIT 1);
INSERT INTO plot_motive_lifecycle_conditions(motive_rule_id,state_key,operator,compare_value,sort_order)
SELECT @r,'GUEST_STATUS','EQ','EVACUATED',1 FROM DUAL WHERE NOT EXISTS(SELECT 1 FROM plot_motive_lifecycle_conditions WHERE motive_rule_id=@r);

-- Alden's preservation goal closes once proof is externalized; his replacement goal was already used by the behavior layer.
INSERT INTO plot_motive_lifecycle_rules(plot_id,role_code,motive_type,match_text,terminal_status,replacement_text,reason_text,priority,active)
SELECT @plot_id,'ALDEN','GOAL','Preserve enough evidence to expose what happened.','ACHIEVED','Make sure the surviving evidence leads to accountability.','Evidence now survives outside Bellweather.',20,1
WHERE NOT EXISTS(SELECT 1 FROM plot_motive_lifecycle_rules WHERE plot_id=@plot_id AND role_code='ALDEN' AND motive_type='GOAL' AND match_text='Preserve enough evidence to expose what happened.');
SET @r=(SELECT motive_rule_id FROM plot_motive_lifecycle_rules WHERE plot_id=@plot_id AND role_code='ALDEN' AND motive_type='GOAL' AND match_text='Preserve enough evidence to expose what happened.' LIMIT 1);
INSERT INTO plot_motive_lifecycle_conditions(motive_rule_id,state_key,operator,compare_value,sort_order)
SELECT @r,'EVIDENCE_EXTERNALIZED','EQ','YES',1 FROM DUAL WHERE NOT EXISTS(SELECT 1 FROM plot_motive_lifecycle_conditions WHERE motive_rule_id=@r);

INSERT INTO plot_motive_lifecycle_rules(plot_id,role_code,motive_type,match_text,terminal_status,replacement_text,reason_text,priority,active)
SELECT @plot_id,'ALDEN','FEAR','Farah destroying the remaining proof.','RESOLVED','Farah controlling the explanation despite the surviving evidence.','Farah can no longer erase the only surviving evidence path.',20,1
WHERE NOT EXISTS(SELECT 1 FROM plot_motive_lifecycle_rules WHERE plot_id=@plot_id AND role_code='ALDEN' AND motive_type='FEAR' AND match_text='Farah destroying the remaining proof.');
SET @r=(SELECT motive_rule_id FROM plot_motive_lifecycle_rules WHERE plot_id=@plot_id AND role_code='ALDEN' AND motive_type='FEAR' AND match_text='Farah destroying the remaining proof.' LIMIT 1);
INSERT INTO plot_motive_lifecycle_conditions(motive_rule_id,state_key,operator,compare_value,sort_order)
SELECT @r,'EVIDENCE_EXTERNALIZED','EQ','YES',1 FROM DUAL WHERE NOT EXISTS(SELECT 1 FROM plot_motive_lifecycle_conditions WHERE motive_rule_id=@r);

INSERT IGNORE INTO schema_migrations(migration) VALUES('018_generic_motive_lifecycle.sql');