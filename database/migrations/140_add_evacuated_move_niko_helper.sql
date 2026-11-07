-- Niko can now be the second helper (Bellweather Rev 12 added PRESSURE_RISE option
-- HELP_WITH_STRETCHER, which sets GUEST_SECOND_HELPER to NIKO), but the completion
-- transitions seeded by migration 021 only move Kellan and Wren to the West
-- Evacuation Point. Mirror them for Niko so the three-helper symmetry holds.
-- Triggered transitions have no studio editor (writer.php has no such tab/form and
-- StoryAuthoringService has no save function for plot_triggered_transitions), so
-- this row is seeded here like its Kellan/Wren counterparts.
SET @plot_id=(SELECT plot_id FROM plots WHERE code='CONCEALED_FAILURE' LIMIT 1);

INSERT INTO plot_triggered_transitions(plot_id,transition_code,label,once_only,priority,active)
VALUES(@plot_id,'EVACUATED_MOVE_NIKO_HELPER','Move Niko with the completed evacuation',1,220,1)
ON DUPLICATE KEY UPDATE active=1,priority=220;
SET @tr=(SELECT transition_id FROM plot_triggered_transitions WHERE plot_id=@plot_id AND transition_code='EVACUATED_MOVE_NIKO_HELPER');
DELETE FROM plot_triggered_transition_conditions WHERE transition_id=@tr;
INSERT INTO plot_triggered_transition_conditions(transition_id,state_key,operator,compare_value,sort_order) VALUES
(@tr,'GUEST_STATUS','EQ','EVACUATED',1),(@tr,'GUEST_SECOND_HELPER','EQ','NIKO',2);
DELETE FROM plot_triggered_transition_effects WHERE transition_id=@tr;
INSERT INTO plot_triggered_transition_effects(transition_id,effect_scope,target_role_code,target_code,set_value,detail_text,sort_order) VALUES
(@tr,'LOCATION','NIKO','SELF','West Evacuation Point','Niko completes the evacuation as the second helper.',1);

INSERT IGNORE INTO schema_migrations(migration) VALUES('140_add_evacuated_move_niko_helper.sql');
