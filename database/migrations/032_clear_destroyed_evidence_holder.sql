-- Empty Throne story-data repair: destroyed evidence has no current holder.
-- Generic PHP continues to evaluate database-defined state transitions and effects.

SET @plot=(SELECT plot_id FROM plots WHERE code='EMPTY_THRONE' LIMIT 1);
SET @property=(SELECT p.property_id
  FROM plot_state_aliases a
  JOIN plot_entities e ON e.plot_id=a.plot_id AND e.entity_code=a.entity_code
  JOIN plot_entity_properties p ON p.plot_entity_id=e.plot_entity_id AND p.property_code=a.property_code
  WHERE a.plot_id=@plot AND a.state_key='LETTER_HOLDER'
  LIMIT 1);

UPDATE plot_entity_properties
SET allowed_values=CASE
  WHEN allowed_values IS NULL OR TRIM(allowed_values)='' THEN 'NONE'
  WHEN FIND_IN_SET('NONE',REPLACE(allowed_values,' ',''))>0 THEN allowed_values
  ELSE CONCAT(allowed_values,',NONE')
END
WHERE property_id=@property;

INSERT IGNORE INTO plot_property_transitions(property_id,from_value,to_value,active)
VALUES(@property,'ROWAN','NONE',1);

SET @dynamic_option=(SELECT pdoo.dynamic_option_id
  FROM plot_dynamic_opportunity_options pdoo
  JOIN plot_dynamic_opportunities pdo ON pdo.dynamic_opportunity_id=pdoo.dynamic_opportunity_id
  WHERE pdo.plot_id=@plot
    AND pdo.opportunity_code='ROWAN_USE_LETTER_CONTROL'
    AND pdoo.option_code='DESTROY_LETTER'
  LIMIT 1);

INSERT INTO plot_dynamic_option_effects
(dynamic_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order)
SELECT @dynamic_option,'WORLD_STATE','LETTER_HOLDER',NULL,'NONE',
       'Destroyed evidence is no longer held by any character or institution.',5
WHERE @dynamic_option IS NOT NULL
  AND NOT EXISTS(
    SELECT 1 FROM plot_dynamic_option_effects
    WHERE dynamic_option_id=@dynamic_option
      AND effect_scope='WORLD_STATE'
      AND target_code='LETTER_HOLDER'
  );

SET @template_option=(SELECT pdto.template_option_id
  FROM plot_decision_template_options pdto
  JOIN plot_decision_templates pdt ON pdt.template_id=pdto.template_id
  WHERE pdt.plot_id=@plot
    AND pdt.event_code='ROWAN_USE_LETTER_CONTROL'
    AND pdt.role_code='ROWAN'
    AND pdto.option_code='DESTROY_LETTER'
  LIMIT 1);

INSERT INTO plot_option_effects
(template_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order)
SELECT @template_option,'WORLD_STATE','LETTER_HOLDER',NULL,'NONE',
       'Destroyed evidence is no longer held by any character or institution.',5
WHERE @template_option IS NOT NULL
  AND NOT EXISTS(
    SELECT 1 FROM plot_option_effects
    WHERE template_option_id=@template_option
      AND effect_scope='WORLD_STATE'
      AND target_code='LETTER_HOLDER'
  );

INSERT IGNORE INTO schema_migrations(migration)
VALUES('032_clear_destroyed_evidence_holder.sql');
