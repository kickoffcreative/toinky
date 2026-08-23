-- Resolve Bellweather's unknown-route rescue climax without weakening its causal rules.
-- This is plot data only. The generic opportunity engine evaluates the same condition/effect
-- vocabulary for every story.

SET @plot=(SELECT plot_id FROM plots WHERE code='CONCEALED_FAILURE' LIMIT 1);

INSERT INTO plot_dynamic_opportunities
(plot_id,opportunity_code,role_code,prompt_text,window_seconds,once_only,priority,active)
SELECT @plot,'WREN_UNKNOWN_ROUTE_CLIMAX','WREN',
       'The stretcher is moving, but no one ever confirmed the west corridor. At the fire door you must choose: probe the dark route while carrying the guest, shelter here with Leila, or leave them both to drive the rest of Bellweather away from the failing east wing.',
       25,1,3,1
WHERE @plot IS NOT NULL
ON DUPLICATE KEY UPDATE
  prompt_text=VALUES(prompt_text),
  window_seconds=VALUES(window_seconds),
  once_only=1,
  priority=VALUES(priority),
  active=1;

SET @opportunity=(SELECT dynamic_opportunity_id
  FROM plot_dynamic_opportunities
  WHERE plot_id=@plot AND opportunity_code='WREN_UNKNOWN_ROUTE_CLIMAX'
  LIMIT 1);

DELETE FROM plot_dynamic_opportunity_conditions
WHERE dynamic_opportunity_id=@opportunity;
INSERT INTO plot_dynamic_opportunity_conditions
(dynamic_opportunity_id,source_type,source_key,operator,compare_value,sort_order)
SELECT @opportunity,'PHASE',NULL,'EQ','CLIMAX',1 WHERE @opportunity IS NOT NULL
UNION ALL
SELECT @opportunity,'WORLD_STATE','GUEST_EVACUATION_STATUS','IN','PREPARING,MOVING',2 WHERE @opportunity IS NOT NULL
UNION ALL
SELECT @opportunity,'WORLD_STATE','GUEST_HELPERS','GTE','2',3 WHERE @opportunity IS NOT NULL
UNION ALL
SELECT @opportunity,'WORLD_STATE','WEST_ROUTE','EQ','UNKNOWN',4 WHERE @opportunity IS NOT NULL
UNION ALL
SELECT @opportunity,'ACTION_COUNT',NULL,'GTE','4',5 WHERE @opportunity IS NOT NULL;

DELETE FROM plot_dynamic_opportunity_options
WHERE dynamic_opportunity_id=@opportunity;
INSERT INTO plot_dynamic_opportunity_options
(dynamic_opportunity_id,option_code,label,result_text,sort_order)
SELECT @opportunity,'PROBE_AND_COMPLETE_CARRY',
       'Probe the unverified corridor while you and Leila finish the carry.',
       'You test each dark section with your weight before bringing the stretcher through. Water reaches your ankles and plaster falls behind you, but the west doors finally open around the injured guest.',1
WHERE @opportunity IS NOT NULL
UNION ALL
SELECT @opportunity,'SHELTER_WITH_GUEST',
       'Stop beneath the reinforced fire door and shelter with Leila and the guest.',
       'You lower the stretcher beneath the fire door and brace beside Leila. The guest remains inside Bellweather, protected from falling plaster but still separated from the evacuation point by an unverified corridor.',2
WHERE @opportunity IS NOT NULL
UNION ALL
SELECT @opportunity,'LEAVE_FOR_MASS_WARNING',
       'Leave Leila with the stretcher and force everyone else farther west.',
       'You release your side of the stretcher and run toward the lobby, shouting until the remaining crowd moves. Leila stays beneath the fire door with the injured guest and no safe carry.',3
WHERE @opportunity IS NOT NULL;

UPDATE plot_dynamic_opportunity_options
SET rationale_text=CASE option_code
  WHEN 'PROBE_AND_COMPLETE_CARRY'
    THEN 'Testing the route during the carry can complete the individual rescue, but rejects the safer shelter and exposes both helpers and the guest to an unverified corridor.'
  WHEN 'SHELTER_WITH_GUEST'
    THEN 'Shelter keeps Wren with the injured guest and avoids the unknown corridor, but forfeits immediate evacuation and leaves the guest inside Bellweather.'
  WHEN 'LEAVE_FOR_MASS_WARNING'
    THEN 'Warning the wider building may protect more people, but forfeits Wren’s place as the second helper and leaves Leila unable to finish the carry.'
  ELSE rationale_text END
WHERE dynamic_opportunity_id=@opportunity;

SET @probe=(SELECT dynamic_option_id FROM plot_dynamic_opportunity_options
  WHERE dynamic_opportunity_id=@opportunity AND option_code='PROBE_AND_COMPLETE_CARRY' LIMIT 1);
INSERT INTO plot_dynamic_option_effects
(dynamic_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order)
SELECT @probe,'WORLD_STATE','WEST_ROUTE',NULL,'USABLE',
       'Wren verifies the west corridor one dangerous section at a time during the carry.',1
WHERE @probe IS NOT NULL
UNION ALL
SELECT @probe,'WORLD_STATE','GUEST_EVACUATION_STATUS',NULL,'EVACUATED',
       'Two helpers carry the injured guest through the newly verified west route.',2
WHERE @probe IS NOT NULL
UNION ALL
SELECT @probe,'LOCATION','SELF',NULL,'West Evacuation Point',
       'Wren reaches the evacuation point with Leila and the injured guest.',3
WHERE @probe IS NOT NULL
UNION ALL
SELECT @probe,'FACTOR','SAFETY',-3,NULL,
       'Probing an unknown route while carrying an injured person exposes the rescue team to avoidable danger.',4
WHERE @probe IS NOT NULL;

SET @shelter=(SELECT dynamic_option_id FROM plot_dynamic_opportunity_options
  WHERE dynamic_opportunity_id=@opportunity AND option_code='SHELTER_WITH_GUEST' LIMIT 1);
INSERT INTO plot_dynamic_option_effects
(dynamic_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order)
SELECT @shelter,'WORLD_STATE','GUEST_EVACUATION_STATUS',NULL,'AT_RISK',
       'The guest receives temporary shelter but remains inside the threatened building.',1
WHERE @shelter IS NOT NULL
UNION ALL
SELECT @shelter,'LOCATION','SELF',NULL,'West Corridor',
       'Wren remains beneath the fire door with Leila and the guest.',2
WHERE @shelter IS NOT NULL
UNION ALL
SELECT @shelter,'FACTOR','SAFETY',1,NULL,
       'The reinforced doorway reduces immediate exposure without resolving the evacuation.',3
WHERE @shelter IS NOT NULL;

SET @warn=(SELECT dynamic_option_id FROM plot_dynamic_opportunity_options
  WHERE dynamic_opportunity_id=@opportunity AND option_code='LEAVE_FOR_MASS_WARNING' LIMIT 1);
INSERT INTO plot_dynamic_option_effects
(dynamic_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order)
SELECT @warn,'WORLD_STATE','GUEST_EVACUATION_STATUS',NULL,'AT_RISK',
       'Losing the second helper stops the injured guest short of evacuation.',1
WHERE @warn IS NOT NULL
UNION ALL
SELECT @warn,'LOCATION','SELF',NULL,'Lobby',
       'Wren leaves the guest to warn the remaining crowd.',2
WHERE @warn IS NOT NULL
UNION ALL
SELECT @warn,'FACTOR','SAFETY',5,NULL,
       'The wider warning moves more people clear, at the cost of the unfinished individual rescue.',3
WHERE @warn IS NOT NULL;

SET @documents=(SELECT focus_profile_id FROM plot_focus_profiles
  WHERE plot_id=@plot AND focus_code='DOCUMENTS' LIMIT 1);
SET @rescue=(SELECT focus_profile_id FROM plot_focus_profiles
  WHERE plot_id=@plot AND focus_code='RESCUE' LIMIT 1);
INSERT INTO plot_focus_option_weights
(focus_profile_id,event_code,role_code,option_code,focus_weight)
SELECT @documents,'WREN_UNKNOWN_ROUTE_CLIMAX','WREN','PROBE_AND_COMPLETE_CARRY',10 WHERE @documents IS NOT NULL
UNION ALL SELECT @documents,'WREN_UNKNOWN_ROUTE_CLIMAX','WREN','SHELTER_WITH_GUEST',10 WHERE @documents IS NOT NULL
UNION ALL SELECT @documents,'WREN_UNKNOWN_ROUTE_CLIMAX','WREN','LEAVE_FOR_MASS_WARNING',35 WHERE @documents IS NOT NULL
ON DUPLICATE KEY UPDATE focus_weight=VALUES(focus_weight);
INSERT INTO plot_focus_option_weights
(focus_profile_id,event_code,role_code,option_code,focus_weight)
SELECT @rescue,'WREN_UNKNOWN_ROUTE_CLIMAX','WREN','PROBE_AND_COMPLETE_CARRY',100 WHERE @rescue IS NOT NULL
UNION ALL SELECT @rescue,'WREN_UNKNOWN_ROUTE_CLIMAX','WREN','SHELTER_WITH_GUEST',80 WHERE @rescue IS NOT NULL
UNION ALL SELECT @rescue,'WREN_UNKNOWN_ROUTE_CLIMAX','WREN','LEAVE_FOR_MASS_WARNING',55 WHERE @rescue IS NOT NULL
ON DUPLICATE KEY UPDATE focus_weight=VALUES(focus_weight);

INSERT IGNORE INTO schema_migrations(migration)
VALUES('058_resolve_unknown_route_rescue_climax.sql');
