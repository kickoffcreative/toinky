-- Generic simulation focus profiles and per-option weights.
-- Story-specific classifications live in data and are consumed by the reusable harness.
CREATE TABLE IF NOT EXISTS plot_focus_profiles (
  focus_profile_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  plot_id BIGINT UNSIGNED NOT NULL,
  focus_code VARCHAR(100) NOT NULL,
  label_text VARCHAR(180) NOT NULL,
  instruction_text TEXT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY(focus_profile_id),
  UNIQUE KEY uq_plot_focus_profile(plot_id,focus_code),
  CONSTRAINT fk_focus_profile_plot FOREIGN KEY(plot_id) REFERENCES plots(plot_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plot_focus_option_weights (
  focus_option_weight_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  focus_profile_id BIGINT UNSIGNED NOT NULL,
  event_code VARCHAR(120) NOT NULL,
  role_code VARCHAR(100) NOT NULL,
  option_code VARCHAR(100) NOT NULL,
  focus_weight SMALLINT UNSIGNED NOT NULL DEFAULT 50,
  PRIMARY KEY(focus_option_weight_id),
  UNIQUE KEY uq_focus_option(focus_profile_id,event_code,role_code,option_code),
  KEY idx_focus_option_lookup(focus_profile_id,event_code,role_code),
  CONSTRAINT fk_focus_option_profile FOREIGN KEY(focus_profile_id) REFERENCES plot_focus_profiles(focus_profile_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @plot=(SELECT plot_id FROM plots WHERE code='CONCEALED_FAILURE' LIMIT 1);
INSERT INTO plot_focus_profiles(plot_id,focus_code,label_text,instruction_text,active)
SELECT @plot,'DOCUMENTS','Documents and Evidence',
       'Stress-test choices that locate, preserve, expose, conceal, transfer, challenge, or destroy documentary evidence while respecting the character’s own goal, fear, knowledge, and legal options.',1
WHERE @plot IS NOT NULL
ON DUPLICATE KEY UPDATE label_text=VALUES(label_text),instruction_text=VALUES(instruction_text),active=1;
INSERT INTO plot_focus_profiles(plot_id,focus_code,label_text,instruction_text,active)
SELECT @plot,'RESCUE','Rescue and Evacuation',
       'Stress-test choices that protect people, assess physical danger, organize evacuation, stabilize casualties, or complete rescue while respecting the character’s own goal, fear, knowledge, and legal options.',1
WHERE @plot IS NOT NULL
ON DUPLICATE KEY UPDATE label_text=VALUES(label_text),instruction_text=VALUES(instruction_text),active=1;

SET @documents=(SELECT focus_profile_id FROM plot_focus_profiles WHERE plot_id=@plot AND focus_code='DOCUMENTS');
SET @rescue=(SELECT focus_profile_id FROM plot_focus_profiles WHERE plot_id=@plot AND focus_code='RESCUE');

INSERT INTO plot_focus_option_weights
(focus_profile_id,event_code,role_code,option_code,focus_weight)
SELECT @documents,t.event_code,t.role_code,o.option_code,
  CASE
    WHEN o.option_code IN (
      'FOLLOW_FARAH','TELL_ALDEN_ALARM','WATCH_FARAH_REPORT','SHARE_PUBLICLY','GUARD_EVIDENCE','BLOCK_WITH_INFO',
      'CHALLENGE_CERTIFICATE','BACK_FARAH','SIDE_WITH_EVIDENCE','KEEP_FARAH_IN_CHARGE','STOP_FARAH',
      'TELL_NIKO_RED_STAMP','CARRY_EVIDENCE_INFO',
      'SECURE_REPORT','CONFRONT_ALDEN','RESPOND_KELLAN','DISCREDIT_ALDEN','PRESSURE_NIKO','MOVE_EVIDENCE',
      'PRESSURE_ALDEN','PREPARE_EXIT','DESTROY_LEVERAGE','STAY_AND_DENY'
    ) THEN 100
    WHEN o.option_code IN (
      'HELP_LEILA_NOW','JOIN_RESCUE','FINAL_RESCUE_K',
      'EVACUATE_EAST','TAKE_EVAC_COMMAND','ORDER_FULL_EVAC','FINAL_EVAC_COMMAND',
      'HELP_LEILA','MOVE_GUEST','CHECK_ROUTE','EVAC_GUEST','VERIFY_HAZARD','FINISH_GUEST_EVAC','WARN_SECOND_FAILURE',
      'CONTROL_ROOM','RALLY_GUESTS','LET_FARAH_GO'
    ) THEN 10
    ELSE 45
  END
FROM plot_decision_templates t
JOIN plot_decision_template_options o ON o.template_id=t.template_id
WHERE t.plot_id=@plot AND t.role_code IN ('KELLAN','NIKO','WREN','FARAH')
ON DUPLICATE KEY UPDATE focus_weight=VALUES(focus_weight);

INSERT INTO plot_focus_option_weights
(focus_profile_id,event_code,role_code,option_code,focus_weight)
SELECT @rescue,t.event_code,t.role_code,o.option_code,
  CASE
    WHEN o.option_code IN (
      'HELP_LEILA_NOW','JOIN_RESCUE','FINAL_RESCUE_K',
      'EVACUATE_EAST','TAKE_EVAC_COMMAND','ORDER_FULL_EVAC','FINAL_EVAC_COMMAND','LET_FARAH_GO',
      'HELP_LEILA','MOVE_GUEST','CHECK_ROUTE','EVAC_GUEST','VERIFY_HAZARD','FINISH_GUEST_EVAC','WARN_SECOND_FAILURE',
      'CONTROL_ROOM','RALLY_GUESTS'
    ) THEN 100
    WHEN o.option_code IN (
      'FOLLOW_FARAH','TELL_ALDEN_ALARM','WATCH_FARAH_REPORT','SHARE_PUBLICLY','GUARD_EVIDENCE',
      'SIDE_WITH_EVIDENCE','DESTROY_LEVERAGE','MOVE_EVIDENCE','PREPARE_EXIT'
    ) THEN 10
    ELSE 45
  END
FROM plot_decision_templates t
JOIN plot_decision_template_options o ON o.template_id=t.template_id
WHERE t.plot_id=@plot AND t.role_code IN ('KELLAN','NIKO','WREN','FARAH')
ON DUPLICATE KEY UPDATE focus_weight=VALUES(focus_weight);

INSERT IGNORE INTO schema_migrations(migration)
VALUES('052_generic_simulation_focus_profiles.sql');
