-- Complete the lifecycle of Empty Throne's temporary coronation block when
-- Elian wins a lawful council coronation through the blocked-coronation climax.
-- Generic PHP continues to apply database-authored transitions and effects.

SET @plot=(SELECT plot_id FROM plots WHERE code='EMPTY_THRONE' LIMIT 1);

SET @blocked_property=(SELECT p.property_id
  FROM plot_state_aliases a
  JOIN plot_entities e ON e.plot_id=a.plot_id AND e.entity_code=a.entity_code
  JOIN plot_entity_properties p ON p.plot_entity_id=e.plot_entity_id AND p.property_code=a.property_code
  WHERE a.plot_id=@plot AND a.state_key='CORONATION_BLOCKED'
  LIMIT 1);
INSERT IGNORE INTO plot_property_transitions(property_id,from_value,to_value,active)
SELECT @blocked_property,'YES','NO',1 WHERE @blocked_property IS NOT NULL;

SET @review_property=(SELECT p.property_id
  FROM plot_state_aliases a
  JOIN plot_entities e ON e.plot_id=a.plot_id AND e.entity_code=a.entity_code
  JOIN plot_entity_properties p ON p.plot_entity_id=e.plot_entity_id AND p.property_code=a.property_code
  WHERE a.plot_id=@plot AND a.state_key='REVIEW_STATUS'
  LIMIT 1);
INSERT IGNORE INTO plot_property_transitions(property_id,from_value,to_value,active)
SELECT @review_property,'BLOCKED','RESOLVED',1 WHERE @review_property IS NOT NULL;

SET @dynamic=(SELECT d.dynamic_opportunity_id
  FROM plot_dynamic_opportunities d
  WHERE d.plot_id=@plot AND d.opportunity_code='ELIAN_BLOCKED_CORONATION_CLIMAX'
  LIMIT 1);
SET @dynamic_option=(SELECT o.dynamic_option_id
  FROM plot_dynamic_opportunity_options o
  WHERE o.dynamic_opportunity_id=@dynamic AND o.option_code='REQUEST_LAWFUL_CORONATION'
  LIMIT 1);

INSERT INTO plot_dynamic_option_effects
(dynamic_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order)
SELECT @dynamic_option,'WORLD_STATE','CORONATION_BLOCKED',NULL,'NO',
       'The temporary block ends when the council completes a lawful coronation.',5
WHERE @dynamic_option IS NOT NULL
  AND NOT EXISTS(
    SELECT 1 FROM plot_dynamic_option_effects
    WHERE dynamic_option_id=@dynamic_option
      AND effect_scope='WORLD_STATE' AND target_code='CORONATION_BLOCKED'
  );

INSERT INTO plot_dynamic_option_effects
(dynamic_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order)
SELECT @dynamic_option,'WORLD_STATE','REVIEW_STATUS',NULL,'RESOLVED',
       'The council closes the temporary succession review by recognizing a lawful sovereign.',6
WHERE @dynamic_option IS NOT NULL
  AND NOT EXISTS(
    SELECT 1 FROM plot_dynamic_option_effects
    WHERE dynamic_option_id=@dynamic_option
      AND effect_scope='WORLD_STATE' AND target_code='REVIEW_STATUS'
  );

INSERT INTO plot_dynamic_option_effects
(dynamic_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order)
SELECT @dynamic_option,'WORLD_STATE','REVIEW_RESOLUTION',NULL,'ELIAN_VALIDATED',
       'The council process validates Elian as the lawful resolution of the succession crisis.',7
WHERE @dynamic_option IS NOT NULL
  AND NOT EXISTS(
    SELECT 1 FROM plot_dynamic_option_effects
    WHERE dynamic_option_id=@dynamic_option
      AND effect_scope='WORLD_STATE' AND target_code='REVIEW_RESOLUTION'
  );

-- Dynamic opportunities already opened in earlier simulations have persistent behavior templates,
-- so repair the corresponding template option as well as the reusable dynamic definition.
SET @template_option=(SELECT o.template_option_id
  FROM plot_decision_template_options o
  JOIN plot_decision_templates t ON t.template_id=o.template_id
  WHERE t.plot_id=@plot
    AND t.event_code='ELIAN_BLOCKED_CORONATION_CLIMAX'
    AND t.role_code='ELIAN'
    AND o.option_code='REQUEST_LAWFUL_CORONATION'
  LIMIT 1);

INSERT INTO plot_option_effects
(template_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order)
SELECT @template_option,'WORLD_STATE','CORONATION_BLOCKED',NULL,'NO',
       'The temporary block ends when the council completes a lawful coronation.',5
WHERE @template_option IS NOT NULL
  AND NOT EXISTS(
    SELECT 1 FROM plot_option_effects
    WHERE template_option_id=@template_option
      AND effect_scope='WORLD_STATE' AND target_code='CORONATION_BLOCKED'
  );

INSERT INTO plot_option_effects
(template_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order)
SELECT @template_option,'WORLD_STATE','REVIEW_STATUS',NULL,'RESOLVED',
       'The council closes the temporary succession review by recognizing a lawful sovereign.',6
WHERE @template_option IS NOT NULL
  AND NOT EXISTS(
    SELECT 1 FROM plot_option_effects
    WHERE template_option_id=@template_option
      AND effect_scope='WORLD_STATE' AND target_code='REVIEW_STATUS'
  );

INSERT INTO plot_option_effects
(template_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order)
SELECT @template_option,'WORLD_STATE','REVIEW_RESOLUTION',NULL,'ELIAN_VALIDATED',
       'The council process validates Elian as the lawful resolution of the succession crisis.',7
WHERE @template_option IS NOT NULL
  AND NOT EXISTS(
    SELECT 1 FROM plot_option_effects
    WHERE template_option_id=@template_option
      AND effect_scope='WORLD_STATE' AND target_code='REVIEW_RESOLUTION'
  );

INSERT IGNORE INTO schema_migrations(migration)
VALUES('056_resolve_lawful_coronation_block.sql');
