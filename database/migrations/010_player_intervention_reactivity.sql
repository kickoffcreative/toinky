SET @plot_id=(SELECT plot_id FROM plots WHERE code='CONCEALED_FAILURE' LIMIT 1);

-- Kellan's first investigative option must remain truthful even when Farah stays in the lobby.
SET @t=(SELECT template_id FROM plot_decision_templates WHERE plot_id=@plot_id AND event_code='WALL_SHIFT' AND role_code='KELLAN' LIMIT 1);
SET @o=(SELECT template_option_id FROM plot_decision_template_options WHERE template_id=@t AND option_code='FOLLOW_FARAH' LIMIT 1);
UPDATE plot_decision_template_options
SET label='Go toward the office corridor to investigate Farah’s reaction.',
    result_text='You move toward the office corridor to investigate why Farah reacted to it.'
WHERE template_option_id=@o;
INSERT INTO plot_option_effects(template_option_id,effect_scope,target_code,set_value,detail_text,sort_order)
SELECT @o,'WORLD_STATE','KELLAN_INVESTIGATING','YES','Kellan is actively investigating Farah’s office-corridor reaction.',20 FROM DUAL
WHERE @o IS NOT NULL AND NOT EXISTS(
  SELECT 1 FROM plot_option_effects WHERE template_option_id=@o AND effect_scope='WORLD_STATE' AND target_code='KELLAN_INVESTIGATING'
);

-- When Kellan is investigating Farah, give Farah an explicit reactive option in the next cycle.
SET @t=(SELECT template_id FROM plot_decision_templates WHERE plot_id=@plot_id AND event_code='REPORT_MISSING' AND role_code='FARAH' LIMIT 1);
INSERT IGNORE INTO plot_decision_template_options(template_id,option_code,label,npc_weight,rationale_text,result_text,sort_order)
VALUES(@t,'RESPOND_KELLAN','Confront Kellan about why he followed the office reaction and push him back toward the emergency.',60,'Kellan’s scrutiny creates a direct threat to Farah’s ability to control the explanation.','Farah turns her attention to Kellan, challenges his motives and tries to push him away from the office dispute.',0);
SET @o=(SELECT template_option_id FROM plot_decision_template_options WHERE template_id=@t AND option_code='RESPOND_KELLAN' LIMIT 1);
INSERT INTO plot_option_conditions(template_option_id,state_key,operator,compare_value,reason_text,sort_order)
SELECT @o,'KELLAN_INVESTIGATING','EQ','YES','Kellan is not actively investigating Farah’s office reaction.',1 FROM DUAL
WHERE @o IS NOT NULL AND NOT EXISTS(
  SELECT 1 FROM plot_option_conditions WHERE template_option_id=@o AND state_key='KELLAN_INVESTIGATING'
);
INSERT INTO plot_option_effects(template_option_id,effect_scope,target_code,delta_value,detail_text,sort_order)
SELECT @o,'FACTOR','SUSPICION',6,'Farah’s defensive reaction gives the room another reason to question her.',1 FROM DUAL
WHERE @o IS NOT NULL AND NOT EXISTS(
  SELECT 1 FROM plot_option_effects WHERE template_option_id=@o AND effect_scope='FACTOR' AND target_code='SUSPICION'
);
INSERT INTO plot_option_effects(template_option_id,effect_scope,target_code,delta_value,detail_text,sort_order)
SELECT @o,'FACTOR','CONTROL',-2,'Being forced to react directly to Kellan costs Farah some narrative control.',2 FROM DUAL
WHERE @o IS NOT NULL AND NOT EXISTS(
  SELECT 1 FROM plot_option_effects WHERE template_option_id=@o AND effect_scope='FACTOR' AND target_code='CONTROL'
);

-- If Kellan or Wren has already become the second helper, Wren's first inaction must not pretend Leila is alone.
INSERT INTO plot_no_action_conditions(no_action_effect_id,state_key,operator,compare_value,reason_text,sort_order)
SELECT e.no_action_effect_id,'GUEST_HELPERS','NEQ','2','A second helper is already present, so Wren’s inaction cannot be treated as leaving Leila alone.',1
FROM plot_no_action_effects e
WHERE e.plot_id=@plot_id AND e.event_code='WALL_SHIFT' AND e.role_code='WREN' AND e.target_code='SAFETY'
  AND NOT EXISTS(SELECT 1 FROM plot_no_action_conditions c WHERE c.no_action_effect_id=e.no_action_effect_id AND c.state_key='GUEST_HELPERS');

-- Leila must stop recruiting once a second helper already exists.
SET @t=(SELECT template_id FROM plot_decision_templates WHERE plot_id=@plot_id AND event_code='REPORT_MISSING' AND role_code='LEILA' LIMIT 1);
SET @o=(SELECT template_option_id FROM plot_decision_template_options WHERE template_id=@t AND option_code='RECRUIT_HELPER' LIMIT 1);
INSERT INTO plot_option_conditions(template_option_id,state_key,operator,compare_value,reason_text,sort_order)
SELECT @o,'GUEST_HELPERS','NEQ','2','A second helper is already present.',1 FROM DUAL
WHERE @o IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_option_conditions WHERE template_option_id=@o AND state_key='GUEST_HELPERS');

-- When a helper is ready and the route is still unknown, Leila gets a high-priority route-check reaction.
INSERT IGNORE INTO plot_decision_template_options(template_id,option_code,label,npc_weight,rationale_text,result_text,sort_order)
VALUES(@t,'CHECK_ROUTE_WITH_HELPER','Use the available second helper to verify the west route and prepare evacuation.',70,'With enough hands available, the next constraint is whether the evacuation route is safe.','Leila uses the extra help to verify the west route and prepare the injured guest for movement.',0);
SET @o=(SELECT template_option_id FROM plot_decision_template_options WHERE template_id=@t AND option_code='CHECK_ROUTE_WITH_HELPER' LIMIT 1);
INSERT INTO plot_option_conditions(template_option_id,state_key,operator,compare_value,reason_text,sort_order)
SELECT @o,'GUEST_HELPERS','EQ','2','A second helper must already be present.',1 FROM DUAL
WHERE @o IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_option_conditions WHERE template_option_id=@o AND state_key='GUEST_HELPERS');
INSERT INTO plot_option_conditions(template_option_id,state_key,operator,compare_value,reason_text,sort_order)
SELECT @o,'WEST_ROUTE','EQ','UNKNOWN','The west route has already been resolved.',2 FROM DUAL
WHERE @o IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_option_conditions WHERE template_option_id=@o AND state_key='WEST_ROUTE');
INSERT INTO plot_option_effects(template_option_id,effect_scope,target_code,set_value,detail_text,sort_order)
SELECT @o,'WORLD_STATE','WEST_ROUTE','USABLE','Leila confirms the west evacuation route is usable with a second helper ready.',1 FROM DUAL
WHERE @o IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_option_effects WHERE template_option_id=@o AND effect_scope='WORLD_STATE' AND target_code='WEST_ROUTE');

-- At rising action, do not keep demanding a helper that already exists.
SET @t=(SELECT template_id FROM plot_decision_templates WHERE plot_id=@plot_id AND event_code='PRESSURE_RISE' AND role_code='LEILA' LIMIT 1);
SET @o=(SELECT template_option_id FROM plot_decision_template_options WHERE template_id=@t AND label='Stop the room and explicitly demand another helper.' LIMIT 1);
INSERT INTO plot_option_conditions(template_option_id,state_key,operator,compare_value,reason_text,sort_order)
SELECT @o,'GUEST_HELPERS','NEQ','2','A second helper is already present.',1 FROM DUAL
WHERE @o IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_option_conditions WHERE template_option_id=@o AND state_key='GUEST_HELPERS');

-- Alden may only head to the rear exit to stop Farah when Farah has actually positioned for or begun an exit.
SET @t=(SELECT template_id FROM plot_decision_templates WHERE plot_id=@plot_id AND event_code='REAR_EXIT' AND role_code='ALDEN' LIMIT 1);
SET @o=(SELECT template_option_id FROM plot_decision_template_options WHERE template_id=@t AND label='Move to the rear exit and confront Farah directly.' LIMIT 1);
INSERT INTO plot_option_conditions(template_option_id,state_key,operator,compare_value,reason_text,sort_order)
SELECT @o,'FARAH_EXIT_STATUS','IN','POSITIONED,ATTEMPTING','Farah is not near the exit or attempting to leave.',1 FROM DUAL
WHERE @o IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_option_conditions WHERE template_option_id=@o AND state_key='FARAH_EXIT_STATUS');

-- Kellan's climax perception must not claim Farah is physically at the exit when she is not.
UPDATE plot_event_perceptions
SET title='Observation Becomes A Choice',
    perception_text='The confrontation is reaching its final minutes. What you know about Farah and the service-door alarm may matter only if you use it, especially if she tries to separate herself from the group.'
WHERE plot_id=@plot_id AND event_code='REAR_EXIT' AND role_code='KELLAN';

INSERT IGNORE INTO schema_migrations(migration) VALUES('010_player_intervention_reactivity.sql');
