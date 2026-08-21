SET @plot_id=(SELECT plot_id FROM plots WHERE code='CONCEALED_FAILURE' LIMIT 1);

-- Wren's report-cycle inaction cannot claim Leila still needs a helper once a second helper exists.
INSERT INTO plot_no_action_conditions(no_action_effect_id,state_key,operator,compare_value,reason_text,sort_order)
SELECT e.no_action_effect_id,'GUEST_HELPERS','NEQ','2','A second helper is already present.',20
FROM plot_no_action_effects e
WHERE e.plot_id=@plot_id AND e.event_code='REPORT_MISSING' AND e.role_code='WREN'
  AND NOT EXISTS(
    SELECT 1 FROM plot_no_action_conditions c
    WHERE c.no_action_effect_id=e.no_action_effect_id AND c.state_key='GUEST_HELPERS'
  );

-- Once evacuation is underway, Wren's later inaction cannot narrate the rescue as if nobody committed.
INSERT INTO plot_no_action_conditions(no_action_effect_id,state_key,operator,compare_value,reason_text,sort_order)
SELECT e.no_action_effect_id,'GUEST_STATUS','NOT_IN','EVACUATING,EVACUATED','The injured guest is already being evacuated or is safe.',20
FROM plot_no_action_effects e
WHERE e.plot_id=@plot_id AND e.event_code IN ('PRESSURE_RISE','REAR_EXIT') AND e.role_code='WREN'
  AND NOT EXISTS(
    SELECT 1 FROM plot_no_action_conditions c
    WHERE c.no_action_effect_id=e.no_action_effect_id AND c.state_key='GUEST_STATUS' AND c.operator='NOT_IN'
  );

-- Leila cannot recruit or demand another helper once two helpers exist.
SET @t=(SELECT template_id FROM plot_decision_templates WHERE plot_id=@plot_id AND event_code='REPORT_MISSING' AND role_code='LEILA' LIMIT 1);
SET @o=(SELECT template_option_id FROM plot_decision_template_options WHERE template_id=@t AND option_code='RECRUIT_HELPER' LIMIT 1);
INSERT INTO plot_option_conditions(template_option_id,state_key,operator,compare_value,reason_text,sort_order)
SELECT @o,'GUEST_HELPERS','NEQ','2','A second helper is already present.',20 FROM DUAL
WHERE @o IS NOT NULL AND NOT EXISTS(
  SELECT 1 FROM plot_option_conditions WHERE template_option_id=@o AND state_key='GUEST_HELPERS'
);

-- Leila should not leave the guest to inspect the route unless a second helper is already available.
SET @o=(SELECT template_option_id FROM plot_decision_template_options WHERE template_id=@t AND option_code='CHECK_WEST_ROUTE_NPC' LIMIT 1);
INSERT INTO plot_option_conditions(template_option_id,state_key,operator,compare_value,reason_text,sort_order)
SELECT @o,'GUEST_HELPERS','EQ','2','Someone must remain with the injured guest while Leila checks the route.',20 FROM DUAL
WHERE @o IS NOT NULL AND NOT EXISTS(
  SELECT 1 FROM plot_option_conditions WHERE template_option_id=@o AND state_key='GUEST_HELPERS'
);

-- If Leila delayed the route check in the previous beat, preserve a causal way forward during rising action.
SET @t=(SELECT template_id FROM plot_decision_templates WHERE plot_id=@plot_id AND event_code='PRESSURE_RISE' AND role_code='LEILA' LIMIT 1);
INSERT IGNORE INTO plot_decision_template_options(template_id,option_code,label,npc_weight,rationale_text,result_text,sort_order)
VALUES(@t,'VERIFY_ROUTE_WITH_HELPER','Use the available helper to confirm the west route before moving the injured guest.',60,'With enough people present, route certainty is the remaining constraint on evacuation.','Leila confirms the west route while the second helper remains with the injured guest.',0);
SET @o=(SELECT template_option_id FROM plot_decision_template_options WHERE template_id=@t AND option_code='VERIFY_ROUTE_WITH_HELPER' LIMIT 1);
INSERT INTO plot_option_conditions(template_option_id,state_key,operator,compare_value,reason_text,sort_order)
SELECT @o,'GUEST_HELPERS','EQ','2','Two helpers are required so one person can remain with the guest.',1 FROM DUAL
WHERE @o IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_option_conditions WHERE template_option_id=@o AND state_key='GUEST_HELPERS');
INSERT INTO plot_option_conditions(template_option_id,state_key,operator,compare_value,reason_text,sort_order)
SELECT @o,'WEST_ROUTE','EQ','UNKNOWN','The west route has already been resolved.',2 FROM DUAL
WHERE @o IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_option_conditions WHERE template_option_id=@o AND state_key='WEST_ROUTE');
INSERT INTO plot_option_conditions(template_option_id,state_key,operator,compare_value,reason_text,sort_order)
SELECT @o,'GUEST_STATUS','EQ','STABILIZED','The guest must be stabilized before evacuation planning advances.',3 FROM DUAL
WHERE @o IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_option_conditions WHERE template_option_id=@o AND state_key='GUEST_STATUS');
INSERT INTO plot_option_effects(template_option_id,effect_scope,target_code,set_value,detail_text,sort_order)
SELECT @o,'WORLD_STATE','WEST_ROUTE','USABLE','Leila confirms the west route is usable while the second helper remains with the guest.',1 FROM DUAL
WHERE @o IS NOT NULL AND NOT EXISTS(
  SELECT 1 FROM plot_option_effects WHERE template_option_id=@o AND effect_scope='WORLD_STATE' AND target_code='WEST_ROUTE'
);

-- "Keep the guest until the route is certain" is no longer valid once the route is known to be usable.
SET @o=(SELECT template_option_id FROM plot_decision_template_options WHERE template_id=@t AND label='Keep the guest where they are until the route is certain.' LIMIT 1);
INSERT INTO plot_option_conditions(template_option_id,state_key,operator,compare_value,reason_text,sort_order)
SELECT @o,'WEST_ROUTE','NEQ','USABLE','The west route has already been confirmed usable.',20 FROM DUAL
WHERE @o IS NOT NULL AND NOT EXISTS(
  SELECT 1 FROM plot_option_conditions WHERE template_option_id=@o AND state_key='WEST_ROUTE'
);

-- Rising-action helper demands are invalid after the second helper exists.
SET @o=(SELECT template_option_id FROM plot_decision_template_options WHERE template_id=@t AND label='Stop the room and explicitly demand another helper.' LIMIT 1);
INSERT INTO plot_option_conditions(template_option_id,state_key,operator,compare_value,reason_text,sort_order)
SELECT @o,'GUEST_HELPERS','NEQ','2','A second helper is already present.',20 FROM DUAL
WHERE @o IS NOT NULL AND NOT EXISTS(
  SELECT 1 FROM plot_option_conditions WHERE template_option_id=@o AND state_key='GUEST_HELPERS'
);

-- Climax helper-demand and shelter options cannot contradict an evacuation already underway.
SET @t=(SELECT template_id FROM plot_decision_templates WHERE plot_id=@plot_id AND event_code='REAR_EXIT' AND role_code='LEILA' LIMIT 1);
SET @o=(SELECT template_option_id FROM plot_decision_template_options WHERE template_id=@t AND label='Refuse to move unsafely and call again for another helper.' LIMIT 1);
INSERT INTO plot_option_conditions(template_option_id,state_key,operator,compare_value,reason_text,sort_order)
SELECT @o,'GUEST_HELPERS','NEQ','2','A second helper is already present.',20 FROM DUAL
WHERE @o IS NOT NULL AND NOT EXISTS(
  SELECT 1 FROM plot_option_conditions WHERE template_option_id=@o AND state_key='GUEST_HELPERS'
);
INSERT INTO plot_option_conditions(template_option_id,state_key,operator,compare_value,reason_text,sort_order)
SELECT @o,'GUEST_STATUS','NOT_IN','EVACUATING,EVACUATED','The guest is already being moved or has been evacuated.',21 FROM DUAL
WHERE @o IS NOT NULL AND NOT EXISTS(
  SELECT 1 FROM plot_option_conditions WHERE template_option_id=@o AND state_key='GUEST_STATUS' AND operator='NOT_IN'
);

SET @o=(SELECT template_option_id FROM plot_decision_template_options WHERE template_id=@t AND label='Keep the guest sheltered where they are through the immediate movement.' LIMIT 1);
INSERT INTO plot_option_conditions(template_option_id,state_key,operator,compare_value,reason_text,sort_order)
SELECT @o,'GUEST_STATUS','NOT_IN','EVACUATING,EVACUATED','The guest is already being moved or has been evacuated.',20 FROM DUAL
WHERE @o IS NOT NULL AND NOT EXISTS(
  SELECT 1 FROM plot_option_conditions WHERE template_option_id=@o AND state_key='GUEST_STATUS' AND operator='NOT_IN'
);

-- Kellan's investigative action no longer claims he physically followed Farah.
SET @t=(SELECT template_id FROM plot_decision_templates WHERE plot_id=@plot_id AND event_code='WALL_SHIFT' AND role_code='KELLAN' LIMIT 1);
SET @o=(SELECT template_option_id FROM plot_decision_template_options WHERE template_id=@t AND option_code='FOLLOW_FARAH' LIMIT 1);
UPDATE plot_option_effects
SET detail_text='Kellan moves toward the office corridor to investigate Farah’s reaction.'
WHERE template_option_id=@o AND effect_scope='LOCATION' AND target_code='SELF';
UPDATE plot_option_effects
SET detail_text='Investigating the uncertain office corridor increases Kellan’s demonstrated risk tolerance.'
WHERE template_option_id=@o AND effect_scope='STAT' AND target_code='RISK';

-- Alden physically moves when he chooses to confront Farah at the rear exit.
SET @t=(SELECT template_id FROM plot_decision_templates WHERE plot_id=@plot_id AND event_code='REAR_EXIT' AND role_code='ALDEN' LIMIT 1);
SET @o=(SELECT template_option_id FROM plot_decision_template_options WHERE template_id=@t AND label='Move to the rear exit and confront Farah directly.' LIMIT 1);
INSERT INTO plot_option_effects(template_option_id,effect_scope,target_code,set_value,detail_text,sort_order)
SELECT @o,'LOCATION','SELF','Rear Exit','Alden moves to the rear exit to confront Farah.',20 FROM DUAL
WHERE @o IS NOT NULL AND NOT EXISTS(
  SELECT 1 FROM plot_option_effects WHERE template_option_id=@o AND effect_scope='LOCATION' AND target_code='SELF'
);

INSERT IGNORE INTO schema_migrations(migration) VALUES('011_rescue_state_cleanup.sql');
