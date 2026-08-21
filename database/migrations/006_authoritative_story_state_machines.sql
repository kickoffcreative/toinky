SET @plot_id=(SELECT plot_id FROM plots WHERE code='CONCEALED_FAILURE' LIMIT 1);

-- Guest movement is only possible when the guest is stabilized, two helpers exist, and the west route is confirmed usable.
SET @t=(SELECT template_id FROM plot_decision_templates WHERE plot_id=@plot_id AND event_code='PRESSURE_RISE' AND role_code='LEILA' LIMIT 1);
SET @o=(SELECT template_option_id FROM plot_decision_template_options WHERE template_id=@t AND option_code='MOVE_GUEST_NPC' LIMIT 1);
INSERT INTO plot_option_conditions(template_option_id,state_key,operator,compare_value,reason_text,sort_order)
SELECT @o,'GUEST_STATUS','EQ','STABILIZED','The guest must be stabilized before movement begins.',1 FROM DUAL
WHERE @o IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_option_conditions WHERE template_option_id=@o AND state_key='GUEST_STATUS');
INSERT INTO plot_option_conditions(template_option_id,state_key,operator,compare_value,reason_text,sort_order)
SELECT @o,'GUEST_HELPERS','EQ','2','Two helpers are required to move the injured guest safely.',2 FROM DUAL
WHERE @o IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_option_conditions WHERE template_option_id=@o AND state_key='GUEST_HELPERS');
INSERT INTO plot_option_conditions(template_option_id,state_key,operator,compare_value,reason_text,sort_order)
SELECT @o,'WEST_ROUTE','EQ','USABLE','The west evacuation route must be confirmed usable.',3 FROM DUAL
WHERE @o IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_option_conditions WHERE template_option_id=@o AND state_key='WEST_ROUTE');

-- Support either historical option code if the current seed used BEGIN_MOVE rather than MOVE_GUEST_NPC.
SET @o2=(SELECT template_option_id FROM plot_decision_template_options WHERE template_id=@t AND option_code='BEGIN_MOVE' LIMIT 1);
INSERT INTO plot_option_conditions(template_option_id,state_key,operator,compare_value,reason_text,sort_order)
SELECT @o2,'GUEST_STATUS','EQ','STABILIZED','The guest must be stabilized before movement begins.',1 FROM DUAL
WHERE @o2 IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_option_conditions WHERE template_option_id=@o2 AND state_key='GUEST_STATUS');
INSERT INTO plot_option_conditions(template_option_id,state_key,operator,compare_value,reason_text,sort_order)
SELECT @o2,'GUEST_HELPERS','EQ','2','Two helpers are required to move the injured guest safely.',2 FROM DUAL
WHERE @o2 IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_option_conditions WHERE template_option_id=@o2 AND state_key='GUEST_HELPERS');
INSERT INTO plot_option_conditions(template_option_id,state_key,operator,compare_value,reason_text,sort_order)
SELECT @o2,'WEST_ROUTE','EQ','USABLE','The west evacuation route must be confirmed usable.',3 FROM DUAL
WHERE @o2 IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_option_conditions WHERE template_option_id=@o2 AND state_key='WEST_ROUTE');

-- Finishing the evacuation requires an evacuation already in progress, two helpers, and a usable route.
SET @t=(SELECT template_id FROM plot_decision_templates WHERE plot_id=@plot_id AND event_code='REAR_EXIT' AND role_code='LEILA' LIMIT 1);
SET @o=(SELECT template_option_id FROM plot_decision_template_options WHERE template_id=@t AND option_code='FINISH_EVAC' LIMIT 1);
INSERT INTO plot_option_conditions(template_option_id,state_key,operator,compare_value,reason_text,sort_order)
SELECT @o,'GUEST_STATUS','EQ','EVACUATING','The guest must already be moving before the evacuation can be completed.',2 FROM DUAL
WHERE @o IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_option_conditions WHERE template_option_id=@o AND state_key='GUEST_STATUS' AND compare_value='EVACUATING');
INSERT INTO plot_option_conditions(template_option_id,state_key,operator,compare_value,reason_text,sort_order)
SELECT @o,'GUEST_HELPERS','EQ','2','Two helpers are required to finish the evacuation safely.',3 FROM DUAL
WHERE @o IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_option_conditions WHERE template_option_id=@o AND state_key='GUEST_HELPERS');
INSERT INTO plot_option_conditions(template_option_id,state_key,operator,compare_value,reason_text,sort_order)
SELECT @o,'WEST_ROUTE','EQ','USABLE','The west route must remain usable.',4 FROM DUAL
WHERE @o IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_option_conditions WHERE template_option_id=@o AND state_key='WEST_ROUTE');

-- Farah may only attempt to leave from a non-final inside/positioned state.
SET @t=(SELECT template_id FROM plot_decision_templates WHERE plot_id=@plot_id AND event_code='REAR_EXIT' AND role_code='FARAH' LIMIT 1);
SET @o=(SELECT template_option_id FROM plot_decision_template_options WHERE template_id=@t AND option_code='LEAVE_NOW' LIMIT 1);
INSERT INTO plot_option_conditions(template_option_id,state_key,operator,compare_value,reason_text,sort_order)
SELECT @o,'FARAH_EXIT_STATUS','IN','INSIDE,POSITIONED','Farah can only start an exit attempt while still inside Bellweather.',2 FROM DUAL
WHERE @o IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_option_conditions WHERE template_option_id=@o AND state_key='FARAH_EXIT_STATUS' AND operator='IN');

-- Niko's climax inaction effects only apply while Farah is actually attempting to leave.
INSERT INTO plot_no_action_conditions(no_action_effect_id,state_key,operator,compare_value,reason_text,sort_order)
SELECT e.no_action_effect_id,'FARAH_EXIT_STATUS','EQ','ATTEMPTING','Niko cannot allow an escape that Farah never attempted.',2
FROM plot_no_action_effects e
WHERE e.plot_id=@plot_id AND e.event_code='REAR_EXIT' AND e.role_code='NIKO'
  AND NOT EXISTS(SELECT 1 FROM plot_no_action_conditions c WHERE c.no_action_effect_id=e.no_action_effect_id AND c.state_key='FARAH_EXIT_STATUS');

-- Kellan's climax inaction can only help an active exit attempt.
INSERT INTO plot_no_action_conditions(no_action_effect_id,state_key,operator,compare_value,reason_text,sort_order)
SELECT e.no_action_effect_id,'FARAH_EXIT_STATUS','EQ','ATTEMPTING','Kellan cannot make an exit easier when Farah chose not to leave.',2
FROM plot_no_action_effects e
WHERE e.plot_id=@plot_id AND e.event_code='REAR_EXIT' AND e.role_code='KELLAN'
  AND NOT EXISTS(SELECT 1 FROM plot_no_action_conditions c WHERE c.no_action_effect_id=e.no_action_effect_id AND c.state_key='FARAH_EXIT_STATUS');

INSERT IGNORE INTO schema_migrations(migration) VALUES('006_authoritative_story_state_machines.sql');
