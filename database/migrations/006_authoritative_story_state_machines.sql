SET @plot_id=(SELECT plot_id FROM plots WHERE code='CONCEALED_FAILURE' LIMIT 1);

-- First-cycle player rescue choices create an actual second helper.
SET @o=(SELECT o.template_option_id FROM plot_decision_template_options o JOIN plot_decision_templates t ON t.template_id=o.template_id WHERE t.plot_id=@plot_id AND t.event_code='WALL_SHIFT' AND t.role_code='WREN' AND o.option_code='HELP_LEILA' LIMIT 1);
INSERT INTO plot_option_effects(template_option_id,effect_scope,target_code,set_value,detail_text,sort_order)
SELECT @o,'WORLD_STATE','GUEST_HELPERS','2','Wren becomes the second helper at the injured guest.',9 FROM DUAL
WHERE @o IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_option_effects WHERE template_option_id=@o AND effect_scope='WORLD_STATE' AND target_code='GUEST_HELPERS');
SET @o=(SELECT o.template_option_id FROM plot_decision_template_options o JOIN plot_decision_templates t ON t.template_id=o.template_id WHERE t.plot_id=@plot_id AND t.event_code='WALL_SHIFT' AND t.role_code='KELLAN' AND o.option_code='HELP_GUESTS' LIMIT 1);
INSERT INTO plot_option_effects(template_option_id,effect_scope,target_code,set_value,detail_text,sort_order)
SELECT @o,'WORLD_STATE','GUEST_HELPERS','2','Kellan becomes the second helper at the injured guest.',9 FROM DUAL
WHERE @o IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_option_effects WHERE template_option_id=@o AND effect_scope='WORLD_STATE' AND target_code='GUEST_HELPERS');

-- Continuing treatment can stabilize a guest who was not stabilized during the first cycle.
SET @o=(SELECT o.template_option_id FROM plot_decision_template_options o JOIN plot_decision_templates t ON t.template_id=o.template_id WHERE t.plot_id=@plot_id AND t.event_code='REPORT_MISSING' AND t.role_code='LEILA' AND o.label='Remain with the guest and continue treatment while waiting for help.' LIMIT 1);
INSERT INTO plot_option_effects(template_option_id,effect_scope,target_code,set_value,detail_text,sort_order)
SELECT @o,'WORLD_STATE','GUEST_STATUS','STABILIZED','Continued treatment stabilizes the injured guest.',9 FROM DUAL
WHERE @o IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_option_effects WHERE template_option_id=@o AND effect_scope='WORLD_STATE' AND target_code='GUEST_STATUS');

-- Route checks create authoritative route state.
SET @o=(SELECT o.template_option_id FROM plot_decision_template_options o JOIN plot_decision_templates t ON t.template_id=o.template_id WHERE t.plot_id=@plot_id AND t.event_code='REPORT_MISSING' AND t.role_code='LEILA' AND o.label='Briefly check whether the west route is still usable.' LIMIT 1);
INSERT INTO plot_option_effects(template_option_id,effect_scope,target_code,set_value,detail_text,sort_order)
SELECT @o,'WORLD_STATE','WEST_ROUTE','USABLE','Leila confirms the west route is usable.',9 FROM DUAL
WHERE @o IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_option_effects WHERE template_option_id=@o AND effect_scope='WORLD_STATE' AND target_code='WEST_ROUTE');

-- Guest movement is only possible when stabilized, two helpers exist, and the west route is confirmed usable.
SET @t=(SELECT template_id FROM plot_decision_templates WHERE plot_id=@plot_id AND event_code='PRESSURE_RISE' AND role_code='LEILA' LIMIT 1);
SET @o=(SELECT template_option_id FROM plot_decision_template_options WHERE template_id=@t AND label='Begin moving the injured guest if enough help is present.' LIMIT 1);
INSERT INTO plot_option_conditions(template_option_id,state_key,operator,compare_value,reason_text,sort_order)
SELECT @o,'GUEST_STATUS','EQ','STABILIZED','The guest must be stabilized before movement begins.',1 FROM DUAL
WHERE @o IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_option_conditions WHERE template_option_id=@o AND state_key='GUEST_STATUS');
INSERT INTO plot_option_conditions(template_option_id,state_key,operator,compare_value,reason_text,sort_order)
SELECT @o,'GUEST_HELPERS','EQ','2','Two helpers are required to move the injured guest safely.',2 FROM DUAL
WHERE @o IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_option_conditions WHERE template_option_id=@o AND state_key='GUEST_HELPERS');
INSERT INTO plot_option_conditions(template_option_id,state_key,operator,compare_value,reason_text,sort_order)
SELECT @o,'WEST_ROUTE','EQ','USABLE','The west evacuation route must be confirmed usable.',3 FROM DUAL
WHERE @o IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_option_conditions WHERE template_option_id=@o AND state_key='WEST_ROUTE');
INSERT INTO plot_option_effects(template_option_id,effect_scope,target_code,set_value,detail_text,sort_order)
SELECT @o,'WORLD_STATE','GUEST_STATUS','EVACUATING','The injured guest is now being moved toward the west evacuation point.',9 FROM DUAL
WHERE @o IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_option_effects WHERE template_option_id=@o AND effect_scope='WORLD_STATE' AND target_code='GUEST_STATUS');

-- Finishing the evacuation requires an evacuation already in progress, two helpers, and a usable route.
SET @t=(SELECT template_id FROM plot_decision_templates WHERE plot_id=@plot_id AND event_code='REAR_EXIT' AND role_code='LEILA' LIMIT 1);
SET @o=(SELECT template_option_id FROM plot_decision_template_options WHERE template_id=@t AND label='Finish moving the injured guest toward the west point with whoever is available.' LIMIT 1);
INSERT INTO plot_option_conditions(template_option_id,state_key,operator,compare_value,reason_text,sort_order)
SELECT @o,'GUEST_STATUS','EQ','EVACUATING','The guest must already be moving before the evacuation can be completed.',2 FROM DUAL
WHERE @o IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_option_conditions WHERE template_option_id=@o AND state_key='GUEST_STATUS' AND compare_value='EVACUATING');
INSERT INTO plot_option_conditions(template_option_id,state_key,operator,compare_value,reason_text,sort_order)
SELECT @o,'GUEST_HELPERS','EQ','2','Two helpers are required to finish the evacuation safely.',3 FROM DUAL
WHERE @o IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_option_conditions WHERE template_option_id=@o AND state_key='GUEST_HELPERS');
INSERT INTO plot_option_conditions(template_option_id,state_key,operator,compare_value,reason_text,sort_order)
SELECT @o,'WEST_ROUTE','EQ','USABLE','The west route must remain usable.',4 FROM DUAL
WHERE @o IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_option_conditions WHERE template_option_id=@o AND state_key='WEST_ROUTE');
INSERT INTO plot_option_effects(template_option_id,effect_scope,target_code,set_value,detail_text,sort_order)
SELECT @o,'WORLD_STATE','GUEST_STATUS','EVACUATED','The injured guest reaches the west evacuation point.',9 FROM DUAL
WHERE @o IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_option_effects WHERE template_option_id=@o AND effect_scope='WORLD_STATE' AND target_code='GUEST_STATUS' AND set_value='EVACUATED');

-- Other climax rescue options remain unavailable after evacuation.
INSERT INTO plot_option_conditions(template_option_id,state_key,operator,compare_value,reason_text,sort_order)
SELECT o.template_option_id,'GUEST_STATUS','NEQ','EVACUATED','The injured guest is already at the west evacuation point.',1
FROM plot_decision_template_options o JOIN plot_decision_templates t ON t.template_id=o.template_id
WHERE t.plot_id=@plot_id AND t.event_code='REAR_EXIT' AND t.role_code IN ('WREN','LEILA')
  AND NOT EXISTS(SELECT 1 FROM plot_option_conditions c WHERE c.template_option_id=o.template_option_id AND c.state_key='GUEST_STATUS');

-- Farah positioning and exit attempts are explicit world-state transitions.
SET @o=(SELECT o.template_option_id FROM plot_decision_template_options o JOIN plot_decision_templates t ON t.template_id=o.template_id WHERE t.plot_id=@plot_id AND t.event_code='PRESSURE_RISE' AND t.role_code='FARAH' AND o.label='Move toward the rear exit while keeping the records case close.' LIMIT 1);
INSERT INTO plot_option_effects(template_option_id,effect_scope,target_code,set_value,detail_text,sort_order)
SELECT @o,'WORLD_STATE','FARAH_EXIT_STATUS','POSITIONED','Farah positions herself near the rear exit.',9 FROM DUAL
WHERE @o IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_option_effects WHERE template_option_id=@o AND effect_scope='WORLD_STATE' AND target_code='FARAH_EXIT_STATUS');

SET @t=(SELECT template_id FROM plot_decision_templates WHERE plot_id=@plot_id AND event_code='REAR_EXIT' AND role_code='FARAH' LIMIT 1);
SET @o=(SELECT template_option_id FROM plot_decision_template_options WHERE template_id=@t AND label='Try to leave through the rear exit with whatever records you control.' LIMIT 1);
INSERT INTO plot_option_conditions(template_option_id,state_key,operator,compare_value,reason_text,sort_order)
SELECT @o,'FARAH_EXIT_STATUS','IN','INSIDE,POSITIONED','Farah can only start an exit attempt while still inside Bellweather.',2 FROM DUAL
WHERE @o IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_option_conditions WHERE template_option_id=@o AND state_key='FARAH_EXIT_STATUS' AND operator='IN');
INSERT INTO plot_option_effects(template_option_id,effect_scope,target_code,set_value,detail_text,sort_order)
SELECT @o,'WORLD_STATE','FARAH_EXIT_STATUS','ATTEMPTING','Farah is actively attempting to leave Bellweather.',9 FROM DUAL
WHERE @o IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_option_effects WHERE template_option_id=@o AND effect_scope='WORLD_STATE' AND target_code='FARAH_EXIT_STATUS');

-- Niko and Kellan cannot narratively create an escape unless Farah actually attempted one.
INSERT INTO plot_no_action_conditions(no_action_effect_id,state_key,operator,compare_value,reason_text,sort_order)
SELECT e.no_action_effect_id,'FARAH_EXIT_STATUS','EQ','ATTEMPTING','Niko cannot allow an escape that Farah never attempted.',2
FROM plot_no_action_effects e
WHERE e.plot_id=@plot_id AND e.event_code='REAR_EXIT' AND e.role_code='NIKO'
  AND NOT EXISTS(SELECT 1 FROM plot_no_action_conditions c WHERE c.no_action_effect_id=e.no_action_effect_id AND c.state_key='FARAH_EXIT_STATUS');
INSERT INTO plot_no_action_conditions(no_action_effect_id,state_key,operator,compare_value,reason_text,sort_order)
SELECT e.no_action_effect_id,'FARAH_EXIT_STATUS','EQ','ATTEMPTING','Kellan cannot make an exit easier when Farah chose not to leave.',2
FROM plot_no_action_effects e
WHERE e.plot_id=@plot_id AND e.event_code='REAR_EXIT' AND e.role_code='KELLAN'
  AND NOT EXISTS(SELECT 1 FROM plot_no_action_conditions c WHERE c.no_action_effect_id=e.no_action_effect_id AND c.state_key='FARAH_EXIT_STATUS');

-- Wren cannot worsen the guest after evacuation is complete.
INSERT INTO plot_no_action_conditions(no_action_effect_id,state_key,operator,compare_value,reason_text,sort_order)
SELECT e.no_action_effect_id,'GUEST_STATUS','NEQ','EVACUATED','The guest has already been evacuated, so Wren cannot make that rescue outcome unsafe again.',1
FROM plot_no_action_effects e
WHERE e.plot_id=@plot_id AND e.event_code='REAR_EXIT' AND e.role_code='WREN' AND (e.target_code='GUEST_STATUS' OR e.target_code='SAFETY')
  AND NOT EXISTS(SELECT 1 FROM plot_no_action_conditions c WHERE c.no_action_effect_id=e.no_action_effect_id AND c.state_key='GUEST_STATUS');

INSERT IGNORE INTO schema_migrations(migration) VALUES('006_authoritative_story_state_machines.sql');
