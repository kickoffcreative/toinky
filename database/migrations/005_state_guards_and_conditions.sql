CREATE TABLE IF NOT EXISTS plot_option_conditions (
  option_condition_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  template_option_id BIGINT UNSIGNED NOT NULL,
  state_key VARCHAR(190) NOT NULL,
  operator ENUM('EQ','NEQ','IN','NOT_IN','EXISTS','NOT_EXISTS') NOT NULL DEFAULT 'EQ',
  compare_value VARCHAR(500) NULL,
  reason_text VARCHAR(500) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  KEY idx_option_conditions(template_option_id,sort_order),
  CONSTRAINT fk_poc_option FOREIGN KEY(template_option_id) REFERENCES plot_decision_template_options(template_option_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plot_no_action_conditions (
  no_action_condition_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  no_action_effect_id BIGINT UNSIGNED NOT NULL,
  state_key VARCHAR(190) NOT NULL,
  operator ENUM('EQ','NEQ','IN','NOT_IN','EXISTS','NOT_EXISTS') NOT NULL DEFAULT 'EQ',
  compare_value VARCHAR(500) NULL,
  reason_text VARCHAR(500) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  KEY idx_no_action_conditions(no_action_effect_id,sort_order),
  CONSTRAINT fk_pnac_effect FOREIGN KEY(no_action_effect_id) REFERENCES plot_no_action_effects(no_action_effect_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS simulation_factor_history (
  factor_history_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  simulation_id BIGINT UNSIGNED NOT NULL,
  factor_code VARCHAR(100) NOT NULL,
  old_value INT NOT NULL,
  new_value INT NOT NULL,
  event_second INT UNSIGNED NOT NULL,
  source_type VARCHAR(80) NOT NULL,
  source_id BIGINT UNSIGNED NULL,
  detail_text VARCHAR(500) NULL,
  created_at_utc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_factor_history(simulation_id,event_second,factor_history_id),
  CONSTRAINT fk_sfh_sim FOREIGN KEY(simulation_id) REFERENCES simulations(simulation_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @plot_id=(SELECT plot_id FROM plots WHERE code='CONCEALED_FAILURE' LIMIT 1);

-- Once the guest is evacuated, climax rescue options that assume the guest is still exposed are no longer valid.
INSERT INTO plot_option_conditions(template_option_id,state_key,operator,compare_value,reason_text,sort_order)
SELECT o.template_option_id,'GUEST_STATUS','NEQ','EVACUATED','The injured guest is already at the west evacuation point.',1
FROM plot_decision_template_options o
JOIN plot_decision_templates t ON t.template_id=o.template_id
WHERE t.plot_id=@plot_id AND t.event_code='REAR_EXIT' AND t.role_code IN ('WREN','LEILA')
  AND NOT EXISTS(SELECT 1 FROM plot_option_conditions c WHERE c.template_option_id=o.template_option_id AND c.state_key='GUEST_STATUS');

-- Wren's climax inaction may worsen the guest only while the guest has not already been evacuated.
INSERT INTO plot_no_action_conditions(no_action_effect_id,state_key,operator,compare_value,reason_text,sort_order)
SELECT e.no_action_effect_id,'GUEST_STATUS','NEQ','EVACUATED','The guest has already been evacuated, so Wren cannot make that rescue outcome unsafe again.',1
FROM plot_no_action_effects e
WHERE e.plot_id=@plot_id AND e.event_code='REAR_EXIT' AND e.role_code='WREN' AND (e.target_code='GUEST_STATUS' OR e.target_code='SAFETY')
  AND NOT EXISTS(SELECT 1 FROM plot_no_action_conditions c WHERE c.no_action_effect_id=e.no_action_effect_id AND c.state_key='GUEST_STATUS');

-- Farah cannot perform exit actions after she is already conclusively blocked or has left.
INSERT INTO plot_option_conditions(template_option_id,state_key,operator,compare_value,reason_text,sort_order)
SELECT o.template_option_id,'FARAH_EXIT_STATUS','NOT_IN','BLOCKED,LEFT','Farah\'s exit status is already final.',1
FROM plot_decision_template_options o
JOIN plot_decision_templates t ON t.template_id=o.template_id
WHERE t.plot_id=@plot_id AND t.event_code='REAR_EXIT' AND t.role_code='FARAH'
  AND NOT EXISTS(SELECT 1 FROM plot_option_conditions c WHERE c.template_option_id=o.template_option_id AND c.state_key='FARAH_EXIT_STATUS');

-- Niko's climax inaction can only let Farah leave when she is actually attempting to leave.
INSERT INTO plot_no_action_conditions(no_action_effect_id,state_key,operator,compare_value,reason_text,sort_order)
SELECT e.no_action_effect_id,'FARAH_EXIT_STATUS','EQ','ATTEMPTING','Farah is not currently attempting to leave.',1
FROM plot_no_action_effects e
WHERE e.plot_id=@plot_id AND e.event_code='REAR_EXIT' AND e.role_code='NIKO' AND e.target_code='FARAH_EXIT_STATUS'
  AND NOT EXISTS(SELECT 1 FROM plot_no_action_conditions c WHERE c.no_action_effect_id=e.no_action_effect_id AND c.state_key='FARAH_EXIT_STATUS');

-- Kellan's climax inaction only grants Farah extra room if an exit attempt is actually underway.
INSERT INTO plot_no_action_conditions(no_action_effect_id,state_key,operator,compare_value,reason_text,sort_order)
SELECT e.no_action_effect_id,'FARAH_EXIT_STATUS','EQ','ATTEMPTING','There is no active exit attempt for Kellan to leave unchallenged.',1
FROM plot_no_action_effects e
WHERE e.plot_id=@plot_id AND e.event_code='REAR_EXIT' AND e.role_code='KELLAN' AND e.target_code='CONTROL'
  AND NOT EXISTS(SELECT 1 FROM plot_no_action_conditions c WHERE c.no_action_effect_id=e.no_action_effect_id AND c.state_key='FARAH_EXIT_STATUS');

INSERT IGNORE INTO schema_migrations(migration) VALUES('005_state_guards_and_conditions.sql');
