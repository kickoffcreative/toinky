-- Preserve report custody when Farah leaves with her separate records case.
SET @plot=(SELECT plot_id FROM plots WHERE code='CONCEALED_FAILURE' LIMIT 1);

SET @let_farah_go=(SELECT o.template_option_id
  FROM plot_decision_template_options o
  JOIN plot_decision_templates t ON t.template_id=o.template_id
  WHERE t.plot_id=@plot AND t.event_code='REAR_EXIT' AND t.role_code='NIKO'
    AND o.option_code='LET_FARAH_GO'
  LIMIT 1);
DELETE FROM plot_option_effects
WHERE template_option_id=@let_farah_go
  AND effect_scope='WORLD_STATE'
  AND target_code='REPORT_HOLDER';

INSERT INTO plot_integrity_rules(plot_id,rule_code,message_text,priority,active)
SELECT @plot,'SECURED_REPORT_WITHOUT_HOLDER',
       'The failed report is secured but has no authoritative holder.',12,1
WHERE @plot IS NOT NULL
ON DUPLICATE KEY UPDATE message_text=VALUES(message_text),priority=VALUES(priority),active=1;
SET @rule=(SELECT integrity_rule_id FROM plot_integrity_rules
  WHERE plot_id=@plot AND rule_code='SECURED_REPORT_WITHOUT_HOLDER');
DELETE FROM plot_integrity_rule_conditions WHERE integrity_rule_id=@rule;
INSERT INTO plot_integrity_rule_conditions
(integrity_rule_id,source_type,source_key,operator,compare_value,sort_order) VALUES
(@rule,'WORLD_STATE','FAILED_REPORT_STATE','EQ','SECURED',1),
(@rule,'WORLD_STATE','REPORT_HOLDER','EQ','NONE',2);

INSERT IGNORE INTO schema_migrations(migration)
VALUES('049_preserve_report_custody_when_farah_leaves.sql');
