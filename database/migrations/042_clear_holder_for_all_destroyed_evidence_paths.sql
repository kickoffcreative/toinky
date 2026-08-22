-- Ensure every Empty Throne destruction option clears physical custody.
-- Generic PHP applies these database-defined effects without plot-specific logic.
SET @plot=(SELECT plot_id FROM plots WHERE code='EMPTY_THRONE' LIMIT 1);

INSERT INTO plot_dynamic_option_effects
(dynamic_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order)
SELECT status_effect.dynamic_option_id,'WORLD_STATE','LETTER_HOLDER',NULL,'NONE',
       'Destroyed evidence is no longer held by any character or institution.',
       COALESCE(MAX(existing.sort_order),0)+1
FROM plot_dynamic_option_effects status_effect
JOIN plot_dynamic_opportunity_options option_row
  ON option_row.dynamic_option_id=status_effect.dynamic_option_id
JOIN plot_dynamic_opportunities opportunity
  ON opportunity.dynamic_opportunity_id=option_row.dynamic_opportunity_id
LEFT JOIN plot_dynamic_option_effects existing
  ON existing.dynamic_option_id=status_effect.dynamic_option_id
WHERE opportunity.plot_id=@plot
  AND status_effect.effect_scope='WORLD_STATE'
  AND status_effect.target_code='LETTER_STATUS'
  AND status_effect.set_value='DESTROYED'
  AND NOT EXISTS(
    SELECT 1
    FROM plot_dynamic_option_effects holder_effect
    WHERE holder_effect.dynamic_option_id=status_effect.dynamic_option_id
      AND holder_effect.effect_scope='WORLD_STATE'
      AND holder_effect.target_code='LETTER_HOLDER'
  )
GROUP BY status_effect.dynamic_option_id;

INSERT INTO plot_option_effects
(template_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order)
SELECT status_effect.template_option_id,'WORLD_STATE','LETTER_HOLDER',NULL,'NONE',
       'Destroyed evidence is no longer held by any character or institution.',
       COALESCE(MAX(existing.sort_order),0)+1
FROM plot_option_effects status_effect
JOIN plot_decision_template_options option_row
  ON option_row.template_option_id=status_effect.template_option_id
JOIN plot_decision_templates template
  ON template.template_id=option_row.template_id
LEFT JOIN plot_option_effects existing
  ON existing.template_option_id=status_effect.template_option_id
WHERE template.plot_id=@plot
  AND status_effect.effect_scope='WORLD_STATE'
  AND status_effect.target_code='LETTER_STATUS'
  AND status_effect.set_value='DESTROYED'
  AND NOT EXISTS(
    SELECT 1
    FROM plot_option_effects holder_effect
    WHERE holder_effect.template_option_id=status_effect.template_option_id
      AND holder_effect.effect_scope='WORLD_STATE'
      AND holder_effect.target_code='LETTER_HOLDER'
  )
GROUP BY status_effect.template_option_id;

INSERT IGNORE INTO schema_migrations(migration)
VALUES('042_clear_holder_for_all_destroyed_evidence_paths.sql');
