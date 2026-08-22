-- Repair continuity exposed by human-controlled Bellweather and Empty Throne playthroughs.
-- Story-specific rules remain database data; generic PHP only evaluates configured effects and conditions.

SET @bell=(SELECT plot_id FROM plots WHERE code='CONCEALED_FAILURE' LIMIT 1);

-- Reaching Alden's office gives Farah access to her remaining records, not possession of Alden's copied report.
SET @farah_first=(SELECT o.template_option_id
  FROM plot_decision_template_options o
  JOIN plot_decision_templates t ON t.template_id=o.template_id
  WHERE t.plot_id=@bell AND t.event_code='WALL_SHIFT' AND t.role_code='FARAH'
    AND o.option_code='SECURE_REPORT'
  LIMIT 1);
DELETE FROM plot_option_effects
WHERE template_option_id=@farah_first
  AND effect_scope='WORLD_STATE'
  AND target_code IN ('REPORT_HOLDER','FAILED_REPORT_STATE');
UPDATE plot_option_effects
SET set_value='Keep the failed inspection records and payment trail beyond the group’s control.',
    detail_text='Farah now controls some inspection records, but Alden’s copied failed report remains outside her custody.'
WHERE template_option_id=@farah_first AND effect_scope='GOAL';

-- Niko's custody choice must transfer the copied report into authoritative custody.
SET @report=(SELECT plot_entity_id FROM plot_entities WHERE plot_id=@bell AND entity_code='FAILED_REPORT' LIMIT 1);
SET @holder_property=(SELECT property_id FROM plot_entity_properties
  WHERE plot_entity_id=@report AND property_code='holder' LIMIT 1);
UPDATE plot_entity_properties
SET allowed_values=CASE
  WHEN allowed_values IS NULL OR TRIM(allowed_values)='' THEN 'NONE,ALDEN,FARAH,NIKO'
  WHEN FIND_IN_SET('NIKO',REPLACE(allowed_values,' ',''))>0 THEN allowed_values
  ELSE CONCAT(allowed_values,',NIKO')
END
WHERE property_id=@holder_property;
INSERT IGNORE INTO plot_property_transitions(property_id,from_value,to_value,active) VALUES
(@holder_property,'NONE','NIKO',1),
(@holder_property,'ALDEN','NIKO',1),
(@holder_property,'FARAH','NIKO',1);

SET @niko_custody=(SELECT o.template_option_id
  FROM plot_decision_template_options o
  JOIN plot_decision_templates t ON t.template_id=o.template_id
  WHERE t.plot_id=@bell AND t.event_code='PRESSURE_RISE' AND t.role_code='NIKO'
    AND o.option_code='SIDE_WITH_EVIDENCE'
  LIMIT 1);
INSERT INTO plot_option_effects
(template_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order)
SELECT @niko_custody,'WORLD_STATE','REPORT_HOLDER',NULL,'NIKO',
       'Niko takes official custody of Alden’s copied failed report.',20
WHERE @niko_custody IS NOT NULL
ON DUPLICATE KEY UPDATE set_value=VALUES(set_value),detail_text=VALUES(detail_text);
INSERT INTO plot_option_effects
(template_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order)
SELECT @niko_custody,'WORLD_STATE','FAILED_REPORT_STATE',NULL,'SECURED',
       'The copied failed report remains identified and recoverable under Niko’s custody.',21
WHERE @niko_custody IS NOT NULL
  AND NOT EXISTS(
    SELECT 1 FROM plot_option_effects
    WHERE template_option_id=@niko_custody
      AND effect_scope='WORLD_STATE' AND target_code='FAILED_REPORT_STATE'
  );

-- Resolution prose must use evacuation progress, not the older combined medical state.
UPDATE plot_resolution_conditions c
JOIN plot_resolution_fragments f ON f.resolution_fragment_id=c.resolution_fragment_id
SET c.state_key='GUEST_EVACUATION_STATUS'
WHERE f.plot_id=@bell AND f.section_code='PEOPLE' AND c.state_key='GUEST_STATUS';

-- Add authoritative truth endings for destruction and Niko custody.
INSERT INTO plot_resolution_fragments
(plot_id,section_code,section_order,priority,fragment_text,active)
SELECT @bell,'TRUTH',20,5,
       'Farah destroys the surviving pages in full view of the room. The physical report is gone, but the act of destroying it becomes evidence of its own.',1
WHERE @bell IS NOT NULL
  AND NOT EXISTS(
    SELECT 1 FROM plot_resolution_fragments
    WHERE plot_id=@bell AND section_code='TRUTH' AND priority=5
  );
SET @fragment=(SELECT resolution_fragment_id FROM plot_resolution_fragments
  WHERE plot_id=@bell AND section_code='TRUTH' AND priority=5 LIMIT 1);
DELETE FROM plot_resolution_conditions WHERE resolution_fragment_id=@fragment;
INSERT INTO plot_resolution_conditions
(resolution_fragment_id,state_key,operator,compare_value,sort_order)
VALUES(@fragment,'FAILED_REPORT_STATE','EQ','DESTROYED',1);

INSERT INTO plot_resolution_fragments
(plot_id,section_code,section_order,priority,fragment_text,active)
SELECT @bell,'TRUTH',20,35,
       'Niko keeps the failed report beneath his radio hand and records its custody for the authorities. Farah can challenge its meaning, but she no longer controls whether it survives.',1
WHERE @bell IS NOT NULL
  AND NOT EXISTS(
    SELECT 1 FROM plot_resolution_fragments
    WHERE plot_id=@bell AND section_code='TRUTH' AND priority=35
  );
SET @fragment=(SELECT resolution_fragment_id FROM plot_resolution_fragments
  WHERE plot_id=@bell AND section_code='TRUTH' AND priority=35 LIMIT 1);
DELETE FROM plot_resolution_conditions WHERE resolution_fragment_id=@fragment;
INSERT INTO plot_resolution_conditions
(resolution_fragment_id,state_key,operator,compare_value,sort_order)
VALUES(@fragment,'REPORT_HOLDER','EQ','NIKO',1);

-- Make the key contradictions visible to every generic integrity audit.
INSERT INTO plot_integrity_rules(plot_id,rule_code,message_text,priority,active)
SELECT @bell,'EVACUATED_GUEST_STILL_DESCRIBED_BY_LEGACY_STATE',
       'Guest evacuation is complete, but the legacy guest lifecycle has not advanced beyond the initial injured state.',30,1
WHERE @bell IS NOT NULL
ON DUPLICATE KEY UPDATE message_text=VALUES(message_text),priority=VALUES(priority),active=1;
SET @rule=(SELECT integrity_rule_id FROM plot_integrity_rules
  WHERE plot_id=@bell AND rule_code='EVACUATED_GUEST_STILL_DESCRIBED_BY_LEGACY_STATE');
DELETE FROM plot_integrity_rule_conditions WHERE integrity_rule_id=@rule;
INSERT INTO plot_integrity_rule_conditions
(integrity_rule_id,source_type,source_key,operator,compare_value,sort_order) VALUES
(@rule,'WORLD_STATE','GUEST_EVACUATION_STATUS','EQ','EVACUATED',1),
(@rule,'WORLD_STATE','GUEST_STATUS','IN','INJURED,AT_RISK',2);

SET @throne=(SELECT plot_id FROM plots WHERE code='EMPTY_THRONE' LIMIT 1);
INSERT INTO plot_integrity_rules(plot_id,rule_code,message_text,priority,active)
SELECT @throne,'BLOCKED_CORONATION_HAS_CROWN_HOLDER',
       'A claimant holds the crown even though the authoritative state says coronation is blocked.',30,1
WHERE @throne IS NOT NULL
ON DUPLICATE KEY UPDATE message_text=VALUES(message_text),priority=VALUES(priority),active=1;
SET @rule=(SELECT integrity_rule_id FROM plot_integrity_rules
  WHERE plot_id=@throne AND rule_code='BLOCKED_CORONATION_HAS_CROWN_HOLDER');
DELETE FROM plot_integrity_rule_conditions WHERE integrity_rule_id=@rule;
INSERT INTO plot_integrity_rule_conditions
(integrity_rule_id,source_type,source_key,operator,compare_value,sort_order) VALUES
(@rule,'WORLD_STATE','CORONATION_BLOCKED','EQ','YES',1),
(@rule,'WORLD_STATE','CROWN_HOLDER','NEQ','NONE',2);

INSERT IGNORE INTO schema_migrations(migration)
VALUES('046_repair_playthrough_continuity.sql');
