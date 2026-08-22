-- Repair Bellweather report status values and configure reusable plot integrity rules.
SET @bell=(SELECT plot_id FROM plots WHERE code='CONCEALED_FAILURE' LIMIT 1);

UPDATE plot_option_effects poe
JOIN plot_decision_template_options o ON o.template_option_id=poe.template_option_id
JOIN plot_decision_templates t ON t.template_id=o.template_id
SET poe.set_value='SECURED'
WHERE t.plot_id=@bell
  AND poe.effect_scope='WORLD_STATE'
  AND poe.target_code='FAILED_REPORT_STATE'
  AND poe.set_value IN ('SECURED_BY_ALDEN','SECURED_BY_FARAH');

UPDATE plot_dynamic_option_effects e
JOIN plot_dynamic_opportunity_options o ON o.dynamic_option_id=e.dynamic_option_id
JOIN plot_dynamic_opportunities d ON d.dynamic_opportunity_id=o.dynamic_opportunity_id
SET e.set_value='SECURED'
WHERE d.plot_id=@bell
  AND e.effect_scope='WORLD_STATE'
  AND e.target_code='FAILED_REPORT_STATE'
  AND e.set_value IN ('SECURED_BY_ALDEN','SECURED_BY_FARAH');

UPDATE plot_world_event_effects
SET set_value='SECURED'
WHERE plot_id=@bell
  AND event_code='REPORT_MISSING'
  AND target_code='FAILED_REPORT_STATE';

CREATE TABLE IF NOT EXISTS plot_integrity_rules (
  integrity_rule_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  plot_id BIGINT UNSIGNED NOT NULL,
  rule_code VARCHAR(120) NOT NULL,
  message_text VARCHAR(500) NOT NULL,
  priority INT NOT NULL DEFAULT 100,
  active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY(integrity_rule_id),
  UNIQUE KEY uq_plot_integrity_rule(plot_id,rule_code),
  CONSTRAINT fk_plot_integrity_rule_plot FOREIGN KEY(plot_id) REFERENCES plots(plot_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plot_integrity_rule_conditions (
  integrity_rule_condition_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  integrity_rule_id BIGINT UNSIGNED NOT NULL,
  source_type ENUM('WORLD_STATE','FACTOR') NOT NULL,
  source_key VARCHAR(190) NOT NULL,
  operator ENUM('EQ','NEQ','IN','NOT_IN','EXISTS','NOT_EXISTS','LT','LTE','GT','GTE') NOT NULL DEFAULT 'EQ',
  compare_value VARCHAR(500) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  PRIMARY KEY(integrity_rule_condition_id),
  KEY idx_integrity_rule_condition(integrity_rule_id,sort_order),
  CONSTRAINT fk_integrity_rule_condition FOREIGN KEY(integrity_rule_id) REFERENCES plot_integrity_rules(integrity_rule_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO plot_integrity_rules(plot_id,rule_code,message_text,priority,active)
SELECT @bell,'REPORT_CUSTODY_WITHOUT_SECURED_STATE','Report custody exists while the failed report remains marked UNSECURED.',10,1 WHERE @bell IS NOT NULL
ON DUPLICATE KEY UPDATE message_text=VALUES(message_text),priority=VALUES(priority),active=1;
SET @rule=(SELECT integrity_rule_id FROM plot_integrity_rules WHERE plot_id=@bell AND rule_code='REPORT_CUSTODY_WITHOUT_SECURED_STATE');
DELETE FROM plot_integrity_rule_conditions WHERE integrity_rule_id=@rule;
INSERT INTO plot_integrity_rule_conditions(integrity_rule_id,source_type,source_key,operator,compare_value,sort_order) VALUES
(@rule,'WORLD_STATE','REPORT_HOLDER','NEQ','NONE',1),
(@rule,'WORLD_STATE','FAILED_REPORT_STATE','EQ','UNSECURED',2);

INSERT INTO plot_integrity_rules(plot_id,rule_code,message_text,priority,active)
SELECT @bell,'EVACUATED_WITHOUT_HELPERS','The guest is marked EVACUATED without the required two helpers.',20,1 WHERE @bell IS NOT NULL
ON DUPLICATE KEY UPDATE message_text=VALUES(message_text),priority=VALUES(priority),active=1;
SET @rule=(SELECT integrity_rule_id FROM plot_integrity_rules WHERE plot_id=@bell AND rule_code='EVACUATED_WITHOUT_HELPERS');
DELETE FROM plot_integrity_rule_conditions WHERE integrity_rule_id=@rule;
INSERT INTO plot_integrity_rule_conditions(integrity_rule_id,source_type,source_key,operator,compare_value,sort_order) VALUES
(@rule,'WORLD_STATE','GUEST_STATUS','EQ','EVACUATED',1),
(@rule,'WORLD_STATE','GUEST_HELPERS','LT','2',2);

SET @throne=(SELECT plot_id FROM plots WHERE code='EMPTY_THRONE' LIMIT 1);
INSERT INTO plot_integrity_rules(plot_id,rule_code,message_text,priority,active)
SELECT @throne,'DESTROYED_EVIDENCE_HAS_HOLDER','Destroyed succession evidence still has a current holder.',10,1 WHERE @throne IS NOT NULL
ON DUPLICATE KEY UPDATE message_text=VALUES(message_text),priority=VALUES(priority),active=1;
SET @rule=(SELECT integrity_rule_id FROM plot_integrity_rules WHERE plot_id=@throne AND rule_code='DESTROYED_EVIDENCE_HAS_HOLDER');
DELETE FROM plot_integrity_rule_conditions WHERE integrity_rule_id=@rule;
INSERT INTO plot_integrity_rule_conditions(integrity_rule_id,source_type,source_key,operator,compare_value,sort_order) VALUES
(@rule,'WORLD_STATE','LETTER_STATUS','EQ','DESTROYED',1),
(@rule,'WORLD_STATE','LETTER_HOLDER','NEQ','NONE',2);

INSERT INTO plot_integrity_rules(plot_id,rule_code,message_text,priority,active)
SELECT @throne,'ROWAN_CROWN_STATE_MISMATCH','Rowan holds the crown but his claim is not marked CROWNED.',20,1 WHERE @throne IS NOT NULL
ON DUPLICATE KEY UPDATE message_text=VALUES(message_text),priority=VALUES(priority),active=1;
SET @rule=(SELECT integrity_rule_id FROM plot_integrity_rules WHERE plot_id=@throne AND rule_code='ROWAN_CROWN_STATE_MISMATCH');
DELETE FROM plot_integrity_rule_conditions WHERE integrity_rule_id=@rule;
INSERT INTO plot_integrity_rule_conditions(integrity_rule_id,source_type,source_key,operator,compare_value,sort_order) VALUES
(@rule,'WORLD_STATE','CROWN_HOLDER','EQ','ROWAN',1),
(@rule,'WORLD_STATE','ROWAN_CLAIM','NEQ','CROWNED',2);

INSERT INTO plot_integrity_rules(plot_id,rule_code,message_text,priority,active)
SELECT @throne,'ELIAN_CROWN_STATE_MISMATCH','Elian holds the crown but his claim is not marked CROWNED.',21,1 WHERE @throne IS NOT NULL
ON DUPLICATE KEY UPDATE message_text=VALUES(message_text),priority=VALUES(priority),active=1;
SET @rule=(SELECT integrity_rule_id FROM plot_integrity_rules WHERE plot_id=@throne AND rule_code='ELIAN_CROWN_STATE_MISMATCH');
DELETE FROM plot_integrity_rule_conditions WHERE integrity_rule_id=@rule;
INSERT INTO plot_integrity_rule_conditions(integrity_rule_id,source_type,source_key,operator,compare_value,sort_order) VALUES
(@rule,'WORLD_STATE','CROWN_HOLDER','EQ','ELIAN',1),
(@rule,'WORLD_STATE','ELIAN_CLAIM','NEQ','CROWNED',2);

INSERT IGNORE INTO schema_migrations(migration)
VALUES('041_repair_report_status_and_configure_integrity_rules.sql');
