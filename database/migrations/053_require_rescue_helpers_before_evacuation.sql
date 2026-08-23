-- Prevent any Bellweather option from completing evacuation before the configured helper requirement is met.
-- The engine remains story-neutral: CausalService already evaluates plot_option_conditions
-- when options are shown and immediately before their effects are applied.

SET @plot=(SELECT plot_id FROM plots WHERE code='CONCEALED_FAILURE' LIMIT 1);

INSERT INTO plot_option_conditions
(template_option_id,state_key,operator,compare_value,sort_order)
SELECT DISTINCT o.template_option_id,'GUEST_HELPERS','GTE','2',90
FROM plot_decision_templates t
JOIN plot_decision_template_options o ON o.template_id=t.template_id
JOIN plot_option_effects e ON e.template_option_id=o.template_option_id
WHERE t.plot_id=@plot
  AND e.effect_scope='WORLD_STATE'
  AND e.target_code IN ('GUEST_STATUS','GUEST_EVACUATION_STATUS')
  AND e.set_value='EVACUATED'
  AND NOT EXISTS(
    SELECT 1
    FROM plot_option_conditions c
    WHERE c.template_option_id=o.template_option_id
      AND c.state_key='GUEST_HELPERS'
      AND c.operator='GTE'
      AND c.compare_value='2'
  );

INSERT IGNORE INTO schema_migrations(migration)
VALUES('053_require_rescue_helpers_before_evacuation.sql');
