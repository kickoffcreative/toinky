-- Expand generic option prerequisites to support numeric comparisons.
-- Earlier versions accepted only categorical operators, causing GTE to be stored as an empty ENUM value.

ALTER TABLE plot_option_conditions
  MODIFY operator ENUM('EQ','NEQ','IN','NOT_IN','EXISTS','NOT_EXISTS','LT','LTE','GT','GTE')
  NOT NULL DEFAULT 'EQ';

SET @plot=(SELECT plot_id FROM plots WHERE code='CONCEALED_FAILURE' LIMIT 1);

-- Repair the Bellweather evacuation prerequisites written by migration 053.
UPDATE plot_option_conditions c
JOIN plot_decision_template_options o ON o.template_option_id=c.template_option_id
JOIN plot_decision_templates t ON t.template_id=o.template_id
JOIN plot_option_effects e ON e.template_option_id=o.template_option_id
SET c.operator='GTE',
    c.reason_text=COALESCE(NULLIF(c.reason_text,''),
      'At least two committed helpers are required before evacuation can be completed.')
WHERE t.plot_id=@plot
  AND c.state_key='GUEST_HELPERS'
  AND c.compare_value='2'
  AND c.operator=''
  AND e.effect_scope='WORLD_STATE'
  AND e.target_code IN ('GUEST_STATUS','GUEST_EVACUATION_STATUS')
  AND e.set_value='EVACUATED';

INSERT IGNORE INTO schema_migrations(migration)
VALUES('055_enable_numeric_option_prerequisites.sql');
