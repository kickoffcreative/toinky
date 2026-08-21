SET @plot_id=(SELECT plot_id FROM plots WHERE code='CONCEALED_FAILURE' LIMIT 1);

-- Kellan rescue goal/fear.
SET @t=(SELECT template_id FROM plot_decision_templates WHERE plot_id=@plot_id AND event_code='WALL_SHIFT' AND role_code='KELLAN' LIMIT 1);
SET @o=(SELECT template_option_id FROM plot_decision_template_options WHERE template_id=@t AND label='Go toward the east stairwell and help the injured guest.' LIMIT 1);
DELETE FROM plot_option_effects WHERE template_option_id=@o AND effect_scope IN ('GOAL','FEAR');
INSERT INTO plot_option_effects(template_option_id,effect_scope,target_code,set_value,detail_text,sort_order) VALUES
(@o,'GOAL','SELF','Get the injured guest safely out of the east wing.','Kellan chooses immediate human safety over investigating Farah.',60),
(@o,'FEAR','SELF','The injured guest being trapped by another slope movement.','Entering the rescue makes the physical danger immediate and personal.',61);

-- Leila evacuation goal/fear.
SET @t=(SELECT template_id FROM plot_decision_templates WHERE plot_id=@plot_id AND event_code='PRESSURE_RISE' AND role_code='LEILA' LIMIT 1);
SET @o=(SELECT template_option_id FROM plot_decision_template_options WHERE template_id=@t AND label='Begin moving the injured guest if enough help is present.' LIMIT 1);
DELETE FROM plot_option_effects WHERE template_option_id=@o AND effect_scope IN ('GOAL','FEAR');
INSERT INTO plot_option_effects(template_option_id,effect_scope,target_code,set_value,detail_text,sort_order)
SELECT @o,'GOAL','SELF','Get the injured guest to the west evacuation point.','The rescue has moved from treatment into active evacuation.',60 FROM DUAL WHERE @o IS NOT NULL;
INSERT INTO plot_option_effects(template_option_id,effect_scope,target_code,set_value,detail_text,sort_order)
SELECT @o,'FEAR','SELF','The evacuation failing before the guest reaches safety.','Movement creates a new immediate risk.',61 FROM DUAL WHERE @o IS NOT NULL;

-- Alden externalized evidence goal/fear.
SET @t=(SELECT template_id FROM plot_decision_templates WHERE plot_id=@plot_id AND event_code='REAR_EXIT' AND role_code='ALDEN' LIMIT 1);
SET @o=(SELECT template_option_id FROM plot_decision_template_options WHERE template_id=@t AND label='Use the remaining connection to send the evidence summary outside Bellweather.' LIMIT 1);
DELETE FROM plot_option_effects WHERE template_option_id=@o AND effect_scope IN ('GOAL','FEAR');
INSERT INTO plot_option_effects(template_option_id,effect_scope,target_code,set_value,detail_text,sort_order)
SELECT @o,'GOAL','SELF','Make sure the surviving evidence leads to accountability.','The evidence is preserved outside Bellweather, shifting Alden from preservation to accountability.',60 FROM DUAL WHERE @o IS NOT NULL;
INSERT INTO plot_option_effects(template_option_id,effect_scope,target_code,set_value,detail_text,sort_order)
SELECT @o,'FEAR','SELF','Farah controlling the explanation despite the surviving evidence.','Destruction of the only proof is no longer the primary risk.',61 FROM DUAL WHERE @o IS NOT NULL;

-- Farah report-possession goal.
SET @t=(SELECT template_id FROM plot_decision_templates WHERE plot_id=@plot_id AND event_code='WALL_SHIFT' AND role_code='FARAH' LIMIT 1);
SET @o=(SELECT template_option_id FROM plot_decision_template_options WHERE template_id=@t AND label='Use the confusion to get to Alden’s office and secure the failed report.' LIMIT 1);
DELETE FROM plot_option_effects WHERE template_option_id=@o AND effect_scope='GOAL';
INSERT INTO plot_option_effects(template_option_id,effect_scope,target_code,set_value,detail_text,sort_order)
SELECT @o,'GOAL','SELF','Keep the failed report beyond the group’s control.','Farah now physically controls the evidence she feared others would secure.',60 FROM DUAL WHERE @o IS NOT NULL;

INSERT IGNORE INTO schema_migrations(migration) VALUES('017_repair_goal_fear_effects.sql');