-- Repair Bellweather outcome semantics without adding story-specific PHP.
-- Medical condition and evacuation progress are separate database-defined states.

SET @plot=(SELECT plot_id FROM plots WHERE code='CONCEALED_FAILURE' LIMIT 1);

-- A guest can remain injured while also being prepared, moved, or evacuated.
SET @guest=(SELECT plot_entity_id FROM plot_entities WHERE plot_id=@plot AND entity_code='GUEST' LIMIT 1);
INSERT IGNORE INTO plot_entity_properties
(plot_entity_id,property_code,label,value_type,initial_value,allowed_values)
SELECT @guest,'condition','Guest Condition','TEXT','INJURED','INJURED,STABILIZED,CRITICAL'
WHERE @guest IS NOT NULL;
SET @condition_property=(SELECT property_id FROM plot_entity_properties WHERE plot_entity_id=@guest AND property_code='condition' LIMIT 1);
INSERT IGNORE INTO plot_property_transitions(property_id,from_value,to_value) VALUES
(@condition_property,'INJURED','STABILIZED'),
(@condition_property,'INJURED','CRITICAL'),
(@condition_property,'STABILIZED','CRITICAL');

INSERT IGNORE INTO plot_entity_properties
(plot_entity_id,property_code,label,value_type,initial_value,allowed_values)
SELECT @guest,'evacuation_status','Guest Evacuation Status','TEXT','INSIDE','INSIDE,PREPARING,MOVING,EVACUATED,AT_RISK'
WHERE @guest IS NOT NULL;
SET @evac_property=(SELECT property_id FROM plot_entity_properties WHERE plot_entity_id=@guest AND property_code='evacuation_status' LIMIT 1);
INSERT IGNORE INTO plot_property_transitions(property_id,from_value,to_value) VALUES
(@evac_property,'INSIDE','PREPARING'),
(@evac_property,'INSIDE','MOVING'),
(@evac_property,'INSIDE','EVACUATED'),
(@evac_property,'INSIDE','AT_RISK'),
(@evac_property,'PREPARING','MOVING'),
(@evac_property,'PREPARING','EVACUATED'),
(@evac_property,'PREPARING','AT_RISK'),
(@evac_property,'MOVING','EVACUATED'),
(@evac_property,'MOVING','AT_RISK'),
(@evac_property,'AT_RISK','PREPARING'),
(@evac_property,'AT_RISK','MOVING'),
(@evac_property,'AT_RISK','EVACUATED');

INSERT IGNORE INTO plot_state_aliases(plot_id,state_key,entity_code,property_code) VALUES
(@plot,'GUEST_CONDITION','GUEST','condition'),
(@plot,'GUEST_EVACUATION_STATUS','GUEST','evacuation_status');

-- Keep the existing lifecycle state for backward compatibility, while reporting the new states.
UPDATE plot_outcome_dimensions
SET dimension_code='REPORT_STATUS',label_text='REPORT STATUS',source_key='FAILED_REPORT_STATE',sort_order=10
WHERE plot_id=@plot AND dimension_code='EVIDENCE_STATE';
UPDATE plot_outcome_dimensions
SET dimension_code='GUEST_CONDITION',label_text='GUEST CONDITION',source_key='GUEST_CONDITION',sort_order=30
WHERE plot_id=@plot AND dimension_code='GUESTS';
INSERT INTO plot_outcome_dimensions
(plot_id,dimension_code,label_text,source_type,source_key,sort_order,active)
SELECT @plot,'GUEST_EVACUATION','GUEST EVACUATION','WORLD_STATE','GUEST_EVACUATION_STATUS',40,1
WHERE @plot IS NOT NULL
ON DUPLICATE KEY UPDATE label_text=VALUES(label_text),source_type=VALUES(source_type),source_key=VALUES(source_key),sort_order=VALUES(sort_order),active=1;
UPDATE plot_outcome_dimensions SET sort_order=50 WHERE plot_id=@plot AND dimension_code='FARAH_EXIT';
UPDATE plot_outcome_dimensions SET sort_order=60 WHERE plot_id=@plot AND dimension_code='SAFETY';
UPDATE plot_outcome_dimensions SET sort_order=70 WHERE plot_id=@plot AND dimension_code='SUSPICION';

-- Farah reaching the office first does not mean she already possesses Alden's copied report.
UPDATE plot_decision_template_options o
JOIN plot_decision_templates t ON t.template_id=o.template_id
SET o.label='Use the dust to reach Alden’s office before anyone else.',
    o.result_text='You disappear into the corridor as another alarm begins. Alden’s office is unlocked, the copied report is gone, and the remaining inspection records are within reach.'
WHERE t.plot_id=@plot AND t.event_code='WALL_SHIFT' AND t.role_code='FARAH' AND o.option_code='SECURE_REPORT';
UPDATE plot_option_effects e
JOIN plot_decision_template_options o ON o.template_option_id=e.template_option_id
JOIN plot_decision_templates t ON t.template_id=o.template_id
SET e.detail_text='Reaching the remaining inspection records first increases Farah’s control.'
WHERE t.plot_id=@plot AND t.event_code='WALL_SHIFT' AND t.role_code='FARAH' AND o.option_code='SECURE_REPORT'
  AND e.effect_scope='FACTOR' AND e.target_code='CONTROL';

-- Destroying the report must change both its physical state and custody.
SET @destroy=(SELECT o.template_option_id FROM plot_decision_template_options o
  JOIN plot_decision_templates t ON t.template_id=o.template_id
  WHERE t.plot_id=@plot AND t.event_code='REAR_EXIT' AND t.role_code='FARAH' AND o.option_code='DESTROY_LEVERAGE' LIMIT 1);
INSERT INTO plot_option_effects
(template_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order)
SELECT @destroy,'WORLD_STATE','FAILED_REPORT_STATE',NULL,'DESTROYED','Farah destroys the surviving physical report.',10
WHERE @destroy IS NOT NULL AND NOT EXISTS(
  SELECT 1 FROM plot_option_effects WHERE template_option_id=@destroy AND effect_scope='WORLD_STATE' AND target_code='FAILED_REPORT_STATE');
INSERT INTO plot_option_effects
(template_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order)
SELECT @destroy,'WORLD_STATE','REPORT_HOLDER',NULL,'NONE','Destroyed pages no longer have a current holder.',11
WHERE @destroy IS NOT NULL AND NOT EXISTS(
  SELECT 1 FROM plot_option_effects WHERE template_option_id=@destroy AND effect_scope='WORLD_STATE' AND target_code='REPORT_HOLDER');

-- Record medical treatment independently from physical evacuation progress.
SET @stabilize=(SELECT o.template_option_id FROM plot_decision_template_options o
  JOIN plot_decision_templates t ON t.template_id=o.template_id
  WHERE t.plot_id=@plot AND t.event_code='WALL_SHIFT' AND t.role_code='LEILA' AND o.option_code='STABILIZE_GUEST' LIMIT 1);
INSERT INTO plot_option_effects(template_option_id,effect_scope,target_code,set_value,detail_text,sort_order)
SELECT @stabilize,'WORLD_STATE','GUEST_CONDITION','STABILIZED','Leila stabilizes the injured guest.',10
WHERE @stabilize IS NOT NULL AND NOT EXISTS(
  SELECT 1 FROM plot_option_effects WHERE template_option_id=@stabilize AND effect_scope='WORLD_STATE' AND target_code='GUEST_CONDITION');

DROP TEMPORARY TABLE IF EXISTS tmp_cf_evacuation_effects;
CREATE TEMPORARY TABLE tmp_cf_evacuation_effects(
  event_code VARCHAR(120),role_code VARCHAR(100),option_code VARCHAR(100),set_value VARCHAR(40),detail_text TEXT,
  PRIMARY KEY(event_code,role_code,option_code)
);
INSERT INTO tmp_cf_evacuation_effects VALUES
('REPORT_MISSING','WREN','MOVE_GUEST','PREPARING','The injured guest is prepared for movement.'),
('PRESSURE_RISE','WREN','EVAC_GUEST','MOVING','The injured guest begins moving toward the west side.'),
('PRESSURE_RISE','LEILA','MOVE_WITH_HELP','MOVING','Leila begins moving the guest toward the west side.'),
('REAR_EXIT','KELLAN','FINAL_RESCUE_K','EVACUATED','The injured guest reaches the west evacuation point.'),
('REAR_EXIT','WREN','FINISH_GUEST_EVAC','EVACUATED','The injured guest reaches the west evacuation point.'),
('REAR_EXIT','LEILA','COMPLETE_EVAC_NPC','EVACUATED','The injured guest reaches the west evacuation point.');
INSERT INTO plot_option_effects
(template_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order)
SELECT o.template_option_id,'WORLD_STATE','GUEST_EVACUATION_STATUS',NULL,x.set_value,x.detail_text,12
FROM tmp_cf_evacuation_effects x
JOIN plot_decision_templates t ON t.plot_id=@plot AND t.event_code=x.event_code AND t.role_code=x.role_code
JOIN plot_decision_template_options o ON o.template_id=t.template_id AND o.option_code=x.option_code
WHERE NOT EXISTS(
  SELECT 1 FROM plot_option_effects e
  WHERE e.template_option_id=o.template_option_id AND e.effect_scope='WORLD_STATE' AND e.target_code='GUEST_EVACUATION_STATUS');
DROP TEMPORARY TABLE tmp_cf_evacuation_effects;

INSERT INTO plot_no_action_effects
(plot_id,event_code,role_code,effect_scope,target_code,delta_value,set_value,detail_text,sort_order)
SELECT @plot,'REAR_EXIT','WREN','WORLD_STATE','GUEST_EVACUATION_STATUS',NULL,'AT_RISK','The rescue remains unresolved as the climax closes.',10
WHERE @plot IS NOT NULL AND NOT EXISTS(
  SELECT 1 FROM plot_no_action_effects WHERE plot_id=@plot AND event_code='REAR_EXIT' AND role_code='WREN'
    AND effect_scope='WORLD_STATE' AND target_code='GUEST_EVACUATION_STATUS');

-- Direct authoritative decisions can stop or release Farah even if she did not first move to the door herself.
SET @farah=(SELECT plot_entity_id FROM plot_entities WHERE plot_id=@plot AND entity_code='FARAH' LIMIT 1);
SET @exit_property=(SELECT property_id FROM plot_entity_properties WHERE plot_entity_id=@farah AND property_code='exit_status' LIMIT 1);
INSERT IGNORE INTO plot_property_transitions(property_id,from_value,to_value) VALUES
(@exit_property,'INSIDE','LEFT'),
(@exit_property,'INSIDE','BLOCKED'),
(@exit_property,'POSITIONED','LEFT'),
(@exit_property,'POSITIONED','BLOCKED');

-- Integrity rules make contradictory endings visible in every generic audit.
INSERT INTO plot_integrity_rules(plot_id,rule_code,message_text,priority,active)
SELECT @plot,'DESTROYED_REPORT_HAS_HOLDER','The failed report is destroyed but still has a current holder.',11,1
WHERE @plot IS NOT NULL
ON DUPLICATE KEY UPDATE message_text=VALUES(message_text),priority=VALUES(priority),active=1;
SET @rule=(SELECT integrity_rule_id FROM plot_integrity_rules WHERE plot_id=@plot AND rule_code='DESTROYED_REPORT_HAS_HOLDER');
DELETE FROM plot_integrity_rule_conditions WHERE integrity_rule_id=@rule;
INSERT INTO plot_integrity_rule_conditions
(integrity_rule_id,source_type,source_key,operator,compare_value,sort_order) VALUES
(@rule,'WORLD_STATE','FAILED_REPORT_STATE','EQ','DESTROYED',1),
(@rule,'WORLD_STATE','REPORT_HOLDER','NEQ','NONE',2);

SET @rule=(SELECT integrity_rule_id FROM plot_integrity_rules WHERE plot_id=@plot AND rule_code='EVACUATED_WITHOUT_HELPERS');
UPDATE plot_integrity_rule_conditions
SET source_key='GUEST_EVACUATION_STATUS'
WHERE integrity_rule_id=@rule AND source_key='GUEST_STATUS';

INSERT IGNORE INTO schema_migrations(migration)
VALUES('045_repair_concealed_failure_outcome_states.sql');
