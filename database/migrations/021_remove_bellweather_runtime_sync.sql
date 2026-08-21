SET @plot_id=(SELECT plot_id FROM plots WHERE code='CONCEALED_FAILURE' LIMIT 1);

-- Leila location is already handled by RESCUE_COMPLETE_AT_CLIMAX. The second helper's final
-- location is now also data-driven, eliminating the last Bellweather-specific location sync in PHP.
INSERT INTO plot_triggered_transitions(plot_id,transition_code,label,once_only,priority,active)
VALUES(@plot_id,'EVACUATED_MOVE_KELLAN_HELPER','Move Kellan with the completed evacuation',1,220,1)
ON DUPLICATE KEY UPDATE active=1,priority=220;
SET @tr=(SELECT transition_id FROM plot_triggered_transitions WHERE plot_id=@plot_id AND transition_code='EVACUATED_MOVE_KELLAN_HELPER');
DELETE FROM plot_triggered_transition_conditions WHERE transition_id=@tr;
INSERT INTO plot_triggered_transition_conditions(transition_id,state_key,operator,compare_value,sort_order) VALUES
(@tr,'GUEST_STATUS','EQ','EVACUATED',1),(@tr,'GUEST_SECOND_HELPER','EQ','KELLAN',2);
DELETE FROM plot_triggered_transition_effects WHERE transition_id=@tr;
INSERT INTO plot_triggered_transition_effects(transition_id,effect_scope,target_role_code,target_code,set_value,detail_text,sort_order) VALUES
(@tr,'LOCATION','KELLAN','SELF','West Evacuation Point','Kellan completes the evacuation as the second helper.',1);

INSERT INTO plot_triggered_transitions(plot_id,transition_code,label,once_only,priority,active)
VALUES(@plot_id,'EVACUATED_MOVE_WREN_HELPER','Move Wren with the completed evacuation',1,220,1)
ON DUPLICATE KEY UPDATE active=1,priority=220;
SET @tr=(SELECT transition_id FROM plot_triggered_transitions WHERE plot_id=@plot_id AND transition_code='EVACUATED_MOVE_WREN_HELPER');
DELETE FROM plot_triggered_transition_conditions WHERE transition_id=@tr;
INSERT INTO plot_triggered_transition_conditions(transition_id,state_key,operator,compare_value,sort_order) VALUES
(@tr,'GUEST_STATUS','EQ','EVACUATED',1),(@tr,'GUEST_SECOND_HELPER','EQ','WREN',2);
DELETE FROM plot_triggered_transition_effects WHERE transition_id=@tr;
INSERT INTO plot_triggered_transition_effects(transition_id,effect_scope,target_role_code,target_code,set_value,detail_text,sort_order) VALUES
(@tr,'LOCATION','WREN','SELF','West Evacuation Point','Wren completes the evacuation as the second helper.',1);

INSERT IGNORE INTO schema_migrations(migration) VALUES('021_remove_bellweather_runtime_sync.sql');