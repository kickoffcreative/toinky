-- Empty Throne succession-state cleanup after Elian's lawful coronation.
-- Story-specific transitions and effects remain database-defined.

SET @plot=(SELECT plot_id FROM plots WHERE code='EMPTY_THRONE' LIMIT 1);

SET @rowan_property=(SELECT p.property_id
  FROM plot_state_aliases a
  JOIN plot_entities e ON e.plot_id=a.plot_id AND e.entity_code=a.entity_code
  JOIN plot_entity_properties p ON p.plot_entity_id=e.plot_entity_id AND p.property_code=a.property_code
  WHERE a.plot_id=@plot AND a.state_key='ROWAN_CLAIM' LIMIT 1);

INSERT IGNORE INTO plot_property_transitions(property_id,from_value,to_value,active)
SELECT @rowan_property,'PRESUMED_HEIR','DISQUALIFIED',1 WHERE @rowan_property IS NOT NULL;
INSERT IGNORE INTO plot_property_transitions(property_id,from_value,to_value,active)
SELECT @rowan_property,'DISPUTED','DISQUALIFIED',1 WHERE @rowan_property IS NOT NULL;

SET @dynamic_option=(SELECT pdoo.dynamic_option_id
  FROM plot_dynamic_opportunity_options pdoo
  JOIN plot_dynamic_opportunities pdo ON pdo.dynamic_opportunity_id=pdoo.dynamic_opportunity_id
  WHERE pdo.plot_id=@plot
    AND pdo.opportunity_code='ELIAN_BLOCKED_CORONATION_CLIMAX'
    AND pdoo.option_code='REQUEST_LAWFUL_CORONATION'
  LIMIT 1);

INSERT INTO plot_dynamic_option_effects
(dynamic_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order)
SELECT @dynamic_option,'WORLD_STATE','ROWAN_CLAIM',NULL,'DISQUALIFIED',
       'Elian’s lawful coronation ends Rowan’s status as presumed heir.',5
WHERE @dynamic_option IS NOT NULL
  AND NOT EXISTS(
    SELECT 1 FROM plot_dynamic_option_effects
    WHERE dynamic_option_id=@dynamic_option
      AND effect_scope='WORLD_STATE'
      AND target_code='ROWAN_CLAIM'
  );

SET @template_option=(SELECT pdto.template_option_id
  FROM plot_decision_template_options pdto
  JOIN plot_decision_templates pdt ON pdt.template_id=pdto.template_id
  WHERE pdt.plot_id=@plot
    AND pdt.event_code='ELIAN_BLOCKED_CORONATION_CLIMAX'
    AND pdt.role_code='ELIAN'
    AND pdto.option_code='REQUEST_LAWFUL_CORONATION'
  LIMIT 1);

INSERT INTO plot_option_effects
(template_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order)
SELECT @template_option,'WORLD_STATE','ROWAN_CLAIM',NULL,'DISQUALIFIED',
       'Elian’s lawful coronation ends Rowan’s status as presumed heir.',5
WHERE @template_option IS NOT NULL
  AND NOT EXISTS(
    SELECT 1 FROM plot_option_effects
    WHERE template_option_id=@template_option
      AND effect_scope='WORLD_STATE'
      AND target_code='ROWAN_CLAIM'
  );

INSERT IGNORE INTO schema_migrations(migration)
VALUES('037_finalize_rival_claim_after_elian_coronation.sql');
