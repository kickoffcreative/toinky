SET @plot_id=(SELECT plot_id FROM plots WHERE code='CONCEALED_FAILURE' LIMIT 1);

-- Track which player actually became the second rescue helper.
SET @o=(SELECT o.template_option_id FROM plot_decision_template_options o JOIN plot_decision_templates t ON t.template_id=o.template_id WHERE t.plot_id=@plot_id AND t.event_code='WALL_SHIFT' AND t.role_code='KELLAN' AND o.option_code='HELP_GUESTS' LIMIT 1);
INSERT INTO plot_option_effects(template_option_id,effect_scope,target_code,set_value,detail_text,sort_order)
SELECT @o,'WORLD_STATE','GUEST_SECOND_HELPER','KELLAN','Kellan is the second person committed to the injured guest evacuation.',10 FROM DUAL
WHERE @o IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_option_effects WHERE template_option_id=@o AND effect_scope='WORLD_STATE' AND target_code='GUEST_SECOND_HELPER');

SET @o=(SELECT o.template_option_id FROM plot_decision_template_options o JOIN plot_decision_templates t ON t.template_id=o.template_id WHERE t.plot_id=@plot_id AND t.event_code='WALL_SHIFT' AND t.role_code='WREN' AND o.option_code='HELP_LEILA' LIMIT 1);
INSERT INTO plot_option_effects(template_option_id,effect_scope,target_code,set_value,detail_text,sort_order)
SELECT @o,'WORLD_STATE','GUEST_SECOND_HELPER','WREN','Wren is the second person committed to the injured guest evacuation.',10 FROM DUAL
WHERE @o IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_option_effects WHERE template_option_id=@o AND effect_scope='WORLD_STATE' AND target_code='GUEST_SECOND_HELPER');

-- Leila physically arrives at the west evacuation point when the guest does.
SET @t=(SELECT template_id FROM plot_decision_templates WHERE plot_id=@plot_id AND event_code='REAR_EXIT' AND role_code='LEILA' LIMIT 1);
SET @o=(SELECT template_option_id FROM plot_decision_template_options WHERE template_id=@t AND label='Finish moving the injured guest toward the west point with whoever is available.' LIMIT 1);
INSERT INTO plot_option_effects(template_option_id,effect_scope,target_code,set_value,detail_text,sort_order)
SELECT @o,'LOCATION','SELF','West Evacuation Point','Leila finishes the evacuation with the injured guest at the west evacuation point.',20 FROM DUAL
WHERE @o IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_option_effects WHERE template_option_id=@o AND effect_scope='LOCATION' AND set_value='West Evacuation Point');

INSERT IGNORE INTO schema_migrations(migration) VALUES('012_initial_vs_final_state_and_rescue_location.sql');