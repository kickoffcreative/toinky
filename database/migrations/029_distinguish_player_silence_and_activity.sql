-- Correct Rowan's silence opportunity and add a distinct response to player activity.
-- PLAYER_ACTION_COUNT is a reusable engine condition; no story-specific PHP is added.

SET @plot=(SELECT plot_id FROM plots WHERE code='EMPTY_THRONE' LIMIT 1);

-- The original rule used ACTION_COUNT, which counts Rowan's own chosen decisions.
-- Genuine silence requires that no PLAYER character has chosen an action.
SET @silence=(SELECT dynamic_opportunity_id FROM plot_dynamic_opportunities
  WHERE plot_id=@plot AND opportunity_code='ROWAN_EXPLOIT_SILENCE' LIMIT 1);

UPDATE plot_dynamic_opportunities
SET prompt_text='The rival claimant and senior officials have allowed the succession to drift. Your goal is still the crown. Do you turn their silence into political momentum, seek a controlled compromise, or wait for a stronger opening?'
WHERE dynamic_opportunity_id=@silence;

DELETE FROM plot_dynamic_opportunity_conditions WHERE dynamic_opportunity_id=@silence;
INSERT INTO plot_dynamic_opportunity_conditions
(dynamic_opportunity_id,source_type,source_key,operator,compare_value,sort_order) VALUES
(@silence,'PHASE',NULL,'EQ','RISING_ACTION',1),
(@silence,'WORLD_STATE','CROWN_HOLDER','EQ','NONE',2),
(@silence,'WORLD_STATE','ELIAN_CLAIM','IN','SECONDARY,ACTIVE',3),
(@silence,'PLAYER_ACTION_COUNT',NULL,'EQ','0',4),
(@silence,'ACTION_COUNT',NULL,'GTE','1',5),
(@silence,'STAT','AMBITION','GTE','70',6);

UPDATE plot_decision_templates
SET prompt_text=(SELECT prompt_text FROM plot_dynamic_opportunities WHERE dynamic_opportunity_id=@silence)
WHERE plot_id=@plot AND event_code='ROWAN_EXPLOIT_SILENCE' AND role_code='ROWAN';

-- When players have acted, use a separate neutral opportunity that does not falsely
-- describe silence or assume whether their activity supports or opposes Rowan.
INSERT INTO plot_dynamic_opportunities
(plot_id,opportunity_code,role_code,prompt_text,window_seconds,once_only,priority,active,reconsideration_key,opportunity_kind)
VALUES
(@plot,'ROWAN_RESPOND_TO_ACTIVITY','ROWAN',
 'The rival claimant and senior officials are actively shaping the succession while no one holds the crown. Your goal is still the crown. Do you counter their activity with political momentum, offer a controlled compromise, or wait for their position to become clearer?',
 25,1,40,1,NULL,'GOAL_PURSUIT')
ON DUPLICATE KEY UPDATE
prompt_text=VALUES(prompt_text),window_seconds=VALUES(window_seconds),priority=VALUES(priority),
active=1,opportunity_kind='GOAL_PURSUIT';

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

DELETE FROM plot_dynamic_opportunity_options WHERE dynamic_opportunity_id=@activity;
INSERT INTO plot_dynamic_opportunity_options
(dynamic_opportunity_id,option_code,label,result_text,npc_weight,rationale_text,sort_order) VALUES
(@activity,'COUNTER_ACTIVITY',
 'Rally councillors before the other actors can define the succession.',
 'Rowan responds to the active succession contest by building political momentum of his own.',
 45,'High ambition favors answering visible political activity with organized support.',1),
(@activity,'OFFER_CONTROLLED_COMPROMISE',
 'Offer a formal review if the council recognizes you as provisional heir.',
 'Rowan tries to channel the active succession contest into a process he can influence.',
 35,'A controlled process can preserve Rowan’s claim while acknowledging that others are acting.',2),
(@activity,'WAIT_FOR_CLARITY',
 'Keep your supporters ready and wait until the other actors reveal their position.',
 'Rowan delays escalation while watching how the active succession contest develops.',
 20,'Waiting can avoid committing before Rowan understands whether the activity helps or threatens him.',3);

SET @o=(SELECT dynamic_option_id FROM plot_dynamic_opportunity_options
  WHERE dynamic_opportunity_id=@activity AND option_code='COUNTER_ACTIVITY' LIMIT 1);
INSERT INTO plot_dynamic_option_effects
(dynamic_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order) VALUES
(@o,'FACTOR','LEGITIMACY',4,NULL,'Rowan’s organized response preserves political support in an active contest.',1),
(@o,'FACTOR','UNREST',4,NULL,'Competing political mobilization increases tension around the succession.',2);

SET @o=(SELECT dynamic_option_id FROM plot_dynamic_opportunity_options
  WHERE dynamic_opportunity_id=@activity AND option_code='OFFER_CONTROLLED_COMPROMISE' LIMIT 1);
INSERT INTO plot_dynamic_option_effects
(dynamic_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order) VALUES
(@o,'FACTOR','LEGITIMACY',2,NULL,'Conditional recognition preserves part of Rowan’s political standing.',1),
(@o,'FACTOR','COURT_TRUST',3,NULL,'Acknowledging an active dispute gives the court a procedural path forward.',2);

SET @o=(SELECT dynamic_option_id FROM plot_dynamic_opportunity_options
  WHERE dynamic_opportunity_id=@activity AND option_code='WAIT_FOR_CLARITY' LIMIT 1);
INSERT INTO plot_dynamic_option_effects
(dynamic_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order) VALUES
(@o,'FACTOR','STABILITY',2,NULL,'Avoiding immediate escalation gives the court limited time to assess the active contest.',1);

INSERT IGNORE INTO schema_migrations(migration)
VALUES('029_distinguish_player_silence_and_activity.sql');
