SET @plot_id=(SELECT plot_id FROM plots WHERE code='CONCEALED_FAILURE' LIMIT 1);

-- Helping one injured guest improves the situation, but should not make the whole unstable resort nearly safe by itself.
SET @o=(SELECT o.template_option_id FROM plot_decision_template_options o JOIN plot_decision_templates t ON t.template_id=o.template_id WHERE t.plot_id=@plot_id AND t.event_code='WALL_SHIFT' AND t.role_code='KELLAN' AND o.option_code='HELP_GUESTS' LIMIT 1);
UPDATE plot_option_effects SET delta_value=3 WHERE template_option_id=@o AND effect_scope='FACTOR' AND target_code='SAFETY';

SET @o=(SELECT o.template_option_id FROM plot_decision_template_options o JOIN plot_decision_templates t ON t.template_id=o.template_id WHERE t.plot_id=@plot_id AND t.event_code='WALL_SHIFT' AND t.role_code='WREN' AND o.option_code='HELP_LEILA' LIMIT 1);
UPDATE plot_option_effects SET delta_value=3 WHERE template_option_id=@o AND effect_scope='FACTOR' AND target_code='SAFETY';

SET @o=(SELECT o.template_option_id FROM plot_decision_template_options o JOIN plot_decision_templates t ON t.template_id=o.template_id WHERE t.plot_id=@plot_id AND t.event_code='PRESSURE_RISE' AND t.role_code='LEILA' AND o.label='Begin moving the injured guest if enough help is present.' LIMIT 1);
UPDATE plot_option_effects SET delta_value=2 WHERE template_option_id=@o AND effect_scope='FACTOR' AND target_code='SAFETY';

SET @o=(SELECT o.template_option_id FROM plot_decision_template_options o JOIN plot_decision_templates t ON t.template_id=o.template_id WHERE t.plot_id=@plot_id AND t.event_code='REAR_EXIT' AND t.role_code='LEILA' AND o.label='Finish moving the injured guest toward the west point with whoever is available.' LIMIT 1);
UPDATE plot_option_effects SET delta_value=3 WHERE template_option_id=@o AND effect_scope='FACTOR' AND target_code='SAFETY';

INSERT IGNORE INTO schema_migrations(migration) VALUES('013_balance_global_safety_effects.sql');