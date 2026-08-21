SET @plot_id=(SELECT plot_id FROM plots WHERE code='CONCEALED_FAILURE' LIMIT 1);

-- The copied failed report is one authoritative story object. Only one actor can secure it from NONE.
SET @t=(SELECT template_id FROM plot_decision_templates WHERE plot_id=@plot_id AND event_code='WALL_SHIFT' AND role_code='ALDEN' LIMIT 1);
SET @alden_secure=(SELECT template_option_id FROM plot_decision_template_options WHERE template_id=@t AND label='Go immediately to the office and secure the copied failed report.' LIMIT 1);
INSERT INTO plot_option_conditions(template_option_id,state_key,operator,compare_value,reason_text,sort_order)
SELECT @alden_secure,'REPORT_HOLDER','EQ','NONE','The report must still be unsecured.',1 FROM DUAL
WHERE @alden_secure IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_option_conditions WHERE template_option_id=@alden_secure AND state_key='REPORT_HOLDER');
INSERT INTO plot_option_effects(template_option_id,effect_scope,target_code,set_value,detail_text,sort_order)
SELECT @alden_secure,'WORLD_STATE','REPORT_HOLDER','ALDEN','Alden secures the copied failed report.',20 FROM DUAL
WHERE @alden_secure IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_option_effects WHERE template_option_id=@alden_secure AND effect_scope='WORLD_STATE' AND target_code='REPORT_HOLDER');
INSERT INTO plot_option_effects(template_option_id,effect_scope,target_code,set_value,detail_text,sort_order)
SELECT @alden_secure,'WORLD_STATE','FAILED_REPORT_STATE','SECURED_BY_ALDEN','The copied failed report is now physically controlled by Alden.',21 FROM DUAL
WHERE @alden_secure IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_option_effects WHERE template_option_id=@alden_secure AND effect_scope='WORLD_STATE' AND target_code='FAILED_REPORT_STATE');

SET @t=(SELECT template_id FROM plot_decision_templates WHERE plot_id=@plot_id AND event_code='WALL_SHIFT' AND role_code='FARAH' LIMIT 1);
SET @farah_secure=(SELECT template_option_id FROM plot_decision_template_options WHERE template_id=@t AND label='Use the confusion to get to Alden’s office and secure the failed report.' LIMIT 1);
INSERT INTO plot_option_conditions(template_option_id,state_key,operator,compare_value,reason_text,sort_order)
SELECT @farah_secure,'REPORT_HOLDER','EQ','NONE','The report must still be unsecured.',1 FROM DUAL
WHERE @farah_secure IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_option_conditions WHERE template_option_id=@farah_secure AND state_key='REPORT_HOLDER');
INSERT INTO plot_option_effects(template_option_id,effect_scope,target_code,set_value,detail_text,sort_order)
SELECT @farah_secure,'WORLD_STATE','REPORT_HOLDER','FARAH','Farah secures the copied failed report.',20 FROM DUAL
WHERE @farah_secure IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_option_effects WHERE template_option_id=@farah_secure AND effect_scope='WORLD_STATE' AND target_code='REPORT_HOLDER');
INSERT INTO plot_option_effects(template_option_id,effect_scope,target_code,set_value,detail_text,sort_order)
SELECT @farah_secure,'WORLD_STATE','FAILED_REPORT_STATE','SECURED_BY_FARAH','The copied failed report is now physically controlled by Farah.',21 FROM DUAL
WHERE @farah_secure IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_option_effects WHERE template_option_id=@farah_secure AND effect_scope='WORLD_STATE' AND target_code='FAILED_REPORT_STATE');

-- Alden can only show or hide the physical report when he actually controls it.
SET @t=(SELECT template_id FROM plot_decision_templates WHERE plot_id=@plot_id AND event_code='REPORT_MISSING' AND role_code='ALDEN' LIMIT 1);
SET @o=(SELECT template_option_id FROM plot_decision_template_options WHERE template_id=@t AND label='Show Niko the report or explain exactly what the failed copy said.' LIMIT 1);
INSERT INTO plot_option_conditions(template_option_id,state_key,operator,compare_value,reason_text,sort_order)
SELECT @o,'REPORT_HOLDER','EQ','ALDEN','Alden must control the report to physically show it.',10 FROM DUAL
WHERE @o IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_option_conditions WHERE template_option_id=@o AND state_key='REPORT_HOLDER');
SET @o=(SELECT template_option_id FROM plot_decision_template_options WHERE template_id=@t AND label='Keep the report hidden until authorities can receive it.' LIMIT 1);
INSERT INTO plot_option_conditions(template_option_id,state_key,operator,compare_value,reason_text,sort_order)
SELECT @o,'REPORT_HOLDER','EQ','ALDEN','Alden cannot hide a report he does not control.',10 FROM DUAL
WHERE @o IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_option_conditions WHERE template_option_id=@o AND state_key='REPORT_HOLDER');

-- Creating another copy requires Alden to possess the current physical report.
SET @t=(SELECT template_id FROM plot_decision_templates WHERE plot_id=@plot_id AND event_code='PRESSURE_RISE' AND role_code='ALDEN' LIMIT 1);
SET @o=(SELECT template_option_id FROM plot_decision_template_options WHERE template_id=@t AND label='Create or separate another copy of the evidence.' LIMIT 1);
INSERT INTO plot_option_conditions(template_option_id,state_key,operator,compare_value,reason_text,sort_order)
SELECT @o,'REPORT_HOLDER','EQ','ALDEN','Alden needs the surviving report in hand to create another physical copy.',10 FROM DUAL
WHERE @o IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_option_conditions WHERE template_option_id=@o AND state_key='REPORT_HOLDER');

-- Route checks produce a concrete world-state result rather than descriptive prose only.
SET @t=(SELECT template_id FROM plot_decision_templates WHERE plot_id=@plot_id AND event_code='WALL_SHIFT' AND role_code='LEILA' LIMIT 1);
SET @o=(SELECT template_option_id FROM plot_decision_template_options WHERE template_id=@t AND label='Check whether the west route is still usable before attempting movement.' LIMIT 1);
INSERT INTO plot_option_effects(template_option_id,effect_scope,target_code,set_value,detail_text,sort_order)
SELECT @o,'WORLD_STATE','WEST_ROUTE','USABLE','Leila confirms the west evacuation route remains usable.',20 FROM DUAL
WHERE @o IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_option_effects WHERE template_option_id=@o AND effect_scope='WORLD_STATE' AND target_code='WEST_ROUTE');

SET @t=(SELECT template_id FROM plot_decision_templates WHERE plot_id=@plot_id AND event_code='REPORT_MISSING' AND role_code='LEILA' LIMIT 1);
SET @o=(SELECT template_option_id FROM plot_decision_template_options WHERE template_id=@t AND label='Briefly check whether the west route is still usable.' LIMIT 1);
INSERT INTO plot_option_effects(template_option_id,effect_scope,target_code,set_value,detail_text,sort_order)
SELECT @o,'WORLD_STATE','WEST_ROUTE','USABLE','Leila confirms the west evacuation route remains usable.',20 FROM DUAL
WHERE @o IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_option_effects WHERE template_option_id=@o AND effect_scope='WORLD_STATE' AND target_code='WEST_ROUTE');

-- Farah must have spent an earlier beat positioning herself before she can attempt an exit.
SET @t=(SELECT template_id FROM plot_decision_templates WHERE plot_id=@plot_id AND event_code='PRESSURE_RISE' AND role_code='FARAH' LIMIT 1);
SET @farah_position=(SELECT template_option_id FROM plot_decision_template_options WHERE template_id=@t AND label='Move toward the rear exit while keeping the records case close.' LIMIT 1);
INSERT INTO plot_option_effects(template_option_id,effect_scope,target_code,set_value,detail_text,sort_order)
SELECT @farah_position,'WORLD_STATE','FARAH_EXIT_STATUS','POSITIONED','Farah positions herself near the rear exit.',20 FROM DUAL
WHERE @farah_position IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_option_effects WHERE template_option_id=@farah_position AND effect_scope='WORLD_STATE' AND target_code='FARAH_EXIT_STATUS');

SET @t=(SELECT template_id FROM plot_decision_templates WHERE plot_id=@plot_id AND event_code='REAR_EXIT' AND role_code='FARAH' LIMIT 1);
SET @leave=(SELECT template_option_id FROM plot_decision_template_options WHERE template_id=@t AND label='Try to leave through the rear exit with whatever records you control.' LIMIT 1);
DELETE FROM plot_option_conditions WHERE template_option_id=@leave AND state_key='FARAH_EXIT_STATUS';
INSERT INTO plot_option_conditions(template_option_id,state_key,operator,compare_value,reason_text,sort_order)
SELECT @leave,'FARAH_EXIT_STATUS','EQ','POSITIONED','Farah must already be positioned near the rear exit before she can make an exit attempt.',1 FROM DUAL
WHERE @leave IS NOT NULL;

-- If Farah reaches the climax without positioning, her remaining/denial and evidence options stay available, but not instant escape.

INSERT IGNORE INTO schema_migrations(migration) VALUES('007_authoritative_objects_and_world_events.sql');
