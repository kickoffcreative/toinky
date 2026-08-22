-- Empty Throne alternative climax when Rowan's coronation is blocked.
-- Story resolution remains database-authored and uses the generic opportunity/effect engine.

SET @plot=(SELECT plot_id FROM plots WHERE code='EMPTY_THRONE' LIMIT 1);

-- Explicit transitions become authoritative when present, so preserve the legal
-- paths used by this climax.
SET @crown_property=(SELECT p.property_id
  FROM plot_state_aliases a
  JOIN plot_entities e ON e.plot_id=a.plot_id AND e.entity_code=a.entity_code
  JOIN plot_entity_properties p ON p.plot_entity_id=e.plot_entity_id AND p.property_code=a.property_code
  WHERE a.plot_id=@plot AND a.state_key='CROWN_HOLDER' LIMIT 1);
INSERT IGNORE INTO plot_property_transitions(property_id,from_value,to_value,active)
SELECT @crown_property,'NONE','ELIAN',1 WHERE @crown_property IS NOT NULL;

SET @elian_property=(SELECT p.property_id
  FROM plot_state_aliases a
  JOIN plot_entities e ON e.plot_id=a.plot_id AND e.entity_code=a.entity_code
  JOIN plot_entity_properties p ON p.plot_entity_id=e.plot_entity_id AND p.property_code=a.property_code
  WHERE a.plot_id=@plot AND a.state_key='ELIAN_CLAIM' LIMIT 1);
INSERT IGNORE INTO plot_property_transitions(property_id,from_value,to_value,active)
SELECT @elian_property,'ACTIVE','CROWNED',1 WHERE @elian_property IS NOT NULL;
INSERT IGNORE INTO plot_property_transitions(property_id,from_value,to_value,active)
SELECT @elian_property,'SUPPORTED','CROWNED',1 WHERE @elian_property IS NOT NULL;

INSERT INTO plot_dynamic_opportunities
(plot_id,opportunity_code,role_code,prompt_text,window_seconds,once_only,priority,active,reconsideration_key,opportunity_kind)
VALUES
(@plot,'ELIAN_BLOCKED_CORONATION_CLIMAX','ELIAN',
 'Rowan’s coronation has been blocked. Your legal coalition, the guard’s investigation, and the council’s mediation have created an opening, but the letter itself is gone. At the decisive hour, do you request lawful coronation, accept a temporary council regency, or withdraw your immediate claim to prevent conflict?',
 25,1,6,1,NULL,'GOAL_PURSUIT')
ON DUPLICATE KEY UPDATE prompt_text=VALUES(prompt_text),window_seconds=VALUES(window_seconds),
priority=VALUES(priority),active=1,opportunity_kind='GOAL_PURSUIT';

SET @d=(SELECT dynamic_opportunity_id FROM plot_dynamic_opportunities
  WHERE plot_id=@plot AND opportunity_code='ELIAN_BLOCKED_CORONATION_CLIMAX' LIMIT 1);
DELETE FROM plot_dynamic_opportunity_conditions WHERE dynamic_opportunity_id=@d;
INSERT INTO plot_dynamic_opportunity_conditions
(dynamic_opportunity_id,source_type,source_key,operator,compare_value,sort_order) VALUES
(@d,'PHASE',NULL,'EQ','CLIMAX',1),
(@d,'WORLD_STATE','CROWN_HOLDER','EQ','NONE',2),
(@d,'WORLD_STATE','CORONATION_BLOCKED','EQ','YES',3),
(@d,'WORLD_STATE','ELIAN_CLAIM','IN','ACTIVE,SUPPORTED',4),
(@d,'FACTOR','LEGITIMACY','GTE','60',5),
(@d,'ACTION_COUNT',NULL,'GTE','2',6);

DELETE FROM plot_dynamic_opportunity_options WHERE dynamic_opportunity_id=@d;
INSERT INTO plot_dynamic_opportunity_options
(dynamic_opportunity_id,option_code,label,result_text,npc_weight,rationale_text,sort_order) VALUES
(@d,'REQUEST_LAWFUL_CORONATION',
 'Ask the council to recognize the blocked coronation, institutional investigation, and your active claim as grounds for lawful coronation.',
 'Elian converts the coalition against an unsupported coronation into a lawful claim to the crown.',
 45,'Ambition and accumulated legitimacy favor acting, while the council process limits the risk of civil war.',1),
(@d,'ACCEPT_COUNCIL_REGENCY',
 'Accept a temporary council regency until the investigation produces a final legal judgment.',
 'Elian keeps his claim active but leaves the crown vacant under temporary council government.',
 35,'Restraint favors preserving peace when the original evidence can no longer be examined.',2),
(@d,'WITHDRAW_IMMEDIATE_CLAIM',
 'Withdraw from the immediate contest and refuse to risk civil war over missing evidence.',
 'Elian abandons the present coronation opportunity to protect the kingdom from armed succession conflict.',
 20,'Fear of civil war can outweigh ambition when legitimacy remains contested.',3);

SET @o=(SELECT dynamic_option_id FROM plot_dynamic_opportunity_options
  WHERE dynamic_opportunity_id=@d AND option_code='REQUEST_LAWFUL_CORONATION');
INSERT INTO plot_dynamic_option_effects
(dynamic_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order) VALUES
(@o,'WORLD_STATE','CROWN_HOLDER',NULL,'ELIAN','The council recognizes Elian as sovereign after Rowan’s coronation is blocked.',1),
(@o,'WORLD_STATE','ELIAN_CLAIM',NULL,'CROWNED','Elian’s active claim becomes a coronation recognized through council process.',2),
(@o,'FACTOR','LEGITIMACY',7,NULL,'A council-backed coronation converts the institutional coalition into political authority.',3),
(@o,'FACTOR','UNREST',3,NULL,'Rowan’s remaining supporters resist the transfer of the crown.',4);

SET @o=(SELECT dynamic_option_id FROM plot_dynamic_opportunity_options
  WHERE dynamic_opportunity_id=@d AND option_code='ACCEPT_COUNCIL_REGENCY');
INSERT INTO plot_dynamic_option_effects
(dynamic_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order) VALUES
(@o,'FACTOR','STABILITY',6,NULL,'Temporary council government prevents an immediate fight over the vacant crown.',1),
(@o,'FACTOR','COURT_TRUST',4,NULL,'Elian’s acceptance of process strengthens confidence in lawful resolution.',2),
(@o,'GOAL','SELF',NULL,'Preserve your lawful claim while the council governs temporarily and completes its investigation.','Elian postpones coronation without abandoning legitimacy.',3);

SET @o=(SELECT dynamic_option_id FROM plot_dynamic_opportunity_options
  WHERE dynamic_opportunity_id=@d AND option_code='WITHDRAW_IMMEDIATE_CLAIM');
INSERT INTO plot_dynamic_option_effects
(dynamic_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order) VALUES
(@o,'FACTOR','STABILITY',7,NULL,'Elian’s withdrawal sharply reduces the immediate risk of dynastic violence.',1),
(@o,'FACTOR','LEGITIMACY',-5,NULL,'Declining the decisive opening weakens Elian’s practical claim.',2),
(@o,'GOAL','SELF',NULL,'Protect the kingdom from civil war after withdrawing from the immediate succession contest.','Elian gives up the present crown contest to preserve peace.',3);

INSERT IGNORE INTO schema_migrations(migration)
VALUES('036_add_blocked_coronation_climax.sql');
