-- Allow dynamic opportunities to distinguish player activity from character activity.
-- Repair the two Rowan condition sets created while PLAYER_ACTION_COUNT was not yet an allowed ENUM value.

ALTER TABLE plot_dynamic_opportunity_conditions
  MODIFY source_type ENUM(
    'WORLD_STATE','FACTOR','LOCATION','GOAL','FEAR','STAT',
    'KNOWLEDGE','RELATIONSHIP','PHASE','ACTION_COUNT','PLAYER_ACTION_COUNT'
  ) NOT NULL DEFAULT 'WORLD_STATE';

SET @plot=(SELECT plot_id FROM plots WHERE code='EMPTY_THRONE' LIMIT 1);

SET @silence=(SELECT dynamic_opportunity_id FROM plot_dynamic_opportunities
  WHERE plot_id=@plot AND opportunity_code='ROWAN_EXPLOIT_SILENCE' LIMIT 1);
DELETE FROM plot_dynamic_opportunity_conditions WHERE dynamic_opportunity_id=@silence;
INSERT INTO plot_dynamic_opportunity_conditions
(dynamic_opportunity_id,source_type,source_key,operator,compare_value,sort_order) VALUES
(@silence,'PHASE',NULL,'EQ','RISING_ACTION',1),
(@silence,'WORLD_STATE','CROWN_HOLDER','EQ','NONE',2),
(@silence,'WORLD_STATE','ELIAN_CLAIM','IN','SECONDARY,ACTIVE',3),
(@silence,'PLAYER_ACTION_COUNT',NULL,'EQ','0',4),
(@silence,'ACTION_COUNT',NULL,'GTE','1',5),
(@silence,'STAT','AMBITION','GTE','70',6);

SET @activity=(SELECT dynamic_opportunity_id FROM plot_dynamic_opportunities
  WHERE plot_id=@plot AND opportunity_code='ROWAN_RESPOND_TO_ACTIVITY' LIMIT 1);
DELETE FROM plot_dynamic_opportunity_conditions WHERE dynamic_opportunity_id=@activity;
INSERT INTO plot_dynamic_opportunity_conditions
(dynamic_opportunity_id,source_type,source_key,operator,compare_value,sort_order) VALUES
(@activity,'PHASE',NULL,'EQ','RISING_ACTION',1),
(@activity,'WORLD_STATE','CROWN_HOLDER','EQ','NONE',2),
(@activity,'WORLD_STATE','ELIAN_CLAIM','IN','SECONDARY,ACTIVE',3),
(@activity,'PLAYER_ACTION_COUNT',NULL,'GTE','1',4),
(@activity,'ACTION_COUNT',NULL,'GTE','1',5),
(@activity,'STAT','AMBITION','GTE','70',6);

INSERT IGNORE INTO schema_migrations(migration)
VALUES('030_allow_player_action_count_conditions.sql');
