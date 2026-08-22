-- Distinguish supportive, opposing, and neutral player activity for dynamic opportunities.
-- OPTION_CHOICE_COUNT is generic; story data supplies the meaningful option codes.

ALTER TABLE plot_dynamic_opportunity_conditions
  MODIFY source_type ENUM(
    'WORLD_STATE','FACTOR','LOCATION','GOAL','FEAR','STAT',
    'KNOWLEDGE','RELATIONSHIP','PHASE','ACTION_COUNT','PLAYER_ACTION_COUNT',
    'OPTION_CHOICE_COUNT'
  ) NOT NULL DEFAULT 'WORLD_STATE';

SET @plot=(SELECT plot_id FROM plots WHERE code='EMPTY_THRONE' LIMIT 1);

-- Neutral fallback: players acted, but neither configured alignment signal occurred.
SET @neutral=(SELECT dynamic_opportunity_id FROM plot_dynamic_opportunities
  WHERE plot_id=@plot AND opportunity_code='ROWAN_RESPOND_TO_ACTIVITY' LIMIT 1);
UPDATE plot_dynamic_opportunities
SET prompt_text='The rival claimant and senior officials are actively shaping the succession, but their position toward your claim is still unclear. Your goal remains the crown. Do you build political momentum, offer a controlled compromise, or wait for their position to become clearer?',
    priority=40,active=1,opportunity_kind='GOAL_PURSUIT'
WHERE dynamic_opportunity_id=@neutral;
DELETE FROM plot_dynamic_opportunity_conditions WHERE dynamic_opportunity_id=@neutral;
INSERT INTO plot_dynamic_opportunity_conditions
(dynamic_opportunity_id,source_type,source_key,operator,compare_value,sort_order) VALUES
(@neutral,'PHASE',NULL,'EQ','RISING_ACTION',1),
(@neutral,'WORLD_STATE','CROWN_HOLDER','EQ','NONE',2),
(@neutral,'WORLD_STATE','ELIAN_CLAIM','IN','SECONDARY,ACTIVE',3),
(@neutral,'PLAYER_ACTION_COUNT',NULL,'GTE','1',4),
(@neutral,'OPTION_CHOICE_COUNT','GUARD_ROWAN','EQ','0',5),
(@neutral,'OPTION_CHOICE_COUNT','GUARD_COUNCIL','EQ','0',6),
(@neutral,'ACTION_COUNT',NULL,'GTE','1',7),
(@neutral,'STAT','AMBITION','GTE','70',8);
UPDATE plot_decision_templates
SET prompt_text=(SELECT prompt_text FROM plot_dynamic_opportunities WHERE dynamic_opportunity_id=@neutral)
WHERE plot_id=@plot AND event_code='ROWAN_RESPOND_TO_ACTIVITY' AND role_code='ROWAN';

-- Support branch.
INSERT INTO plot_dynamic_opportunities
(plot_id,opportunity_code,role_code,prompt_text,window_seconds,once_only,priority,active,reconsideration_key,opportunity_kind)
VALUES
(@plot,'ROWAN_RESPOND_TO_SUPPORT','ROWAN',
 'Captain Reed has placed protection around you while the succession remains unresolved. Your position has visible support. Do you consolidate that support into immediate recognition, use it to permit a controlled review, or hold it in reserve?',
 25,1,38,1,NULL,'GOAL_PURSUIT')
ON DUPLICATE KEY UPDATE
prompt_text=VALUES(prompt_text),window_seconds=VALUES(window_seconds),priority=VALUES(priority),
active=1,opportunity_kind='GOAL_PURSUIT';
SET @support=(SELECT dynamic_opportunity_id FROM plot_dynamic_opportunities
  WHERE plot_id=@plot AND opportunity_code='ROWAN_RESPOND_TO_SUPPORT' LIMIT 1);
DELETE FROM plot_dynamic_opportunity_conditions WHERE dynamic_opportunity_id=@support;
INSERT INTO plot_dynamic_opportunity_conditions
(dynamic_opportunity_id,source_type,source_key,operator,compare_value,sort_order) VALUES
(@support,'PHASE',NULL,'EQ','RISING_ACTION',1),
(@support,'WORLD_STATE','CROWN_HOLDER','EQ','NONE',2),
(@support,'WORLD_STATE','ELIAN_CLAIM','IN','SECONDARY,ACTIVE',3),
(@support,'OPTION_CHOICE_COUNT','GUARD_ROWAN','GTE','1',4),
(@support,'ACTION_COUNT',NULL,'GTE','1',5),
(@support,'STAT','AMBITION','GTE','70',6);
DELETE FROM plot_dynamic_opportunity_options WHERE dynamic_opportunity_id=@support;
INSERT INTO plot_dynamic_opportunity_options
(dynamic_opportunity_id,option_code,label,result_text,npc_weight,rationale_text,sort_order) VALUES
(@support,'CONSOLIDATE_SUPPORT',
 'Use the visible protection to rally councillors around immediate recognition.',
 'Rowan converts visible protection into a coordinated demand for recognition.',
 50,'High ambition favors turning concrete support into political fact.',1),
(@support,'REVIEW_FROM_STRENGTH',
 'Permit a controlled review while your supporters protect your provisional position.',
 'Rowan uses his strengthened position to allow a limited review without surrendering his claim.',
 30,'Support can make procedural restraint safer without requiring Rowan to abandon the crown.',2),
(@support,'HOLD_SUPPORT_IN_RESERVE',
 'Keep the protection in place without forcing another confrontation yet.',
 'Rowan preserves visible support while waiting for a clearer opening.',
 20,'Holding support in reserve protects Rowan from committing before the court settles.',3);
SET @o=(SELECT dynamic_option_id FROM plot_dynamic_opportunity_options
  WHERE dynamic_opportunity_id=@support AND option_code='CONSOLIDATE_SUPPORT' LIMIT 1);
INSERT INTO plot_dynamic_option_effects
(dynamic_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order) VALUES
(@o,'FACTOR','LEGITIMACY',6,NULL,'Visible institutional protection strengthens Rowan’s claim.',1),
(@o,'FACTOR','UNREST',2,NULL,'Opponents become uneasy as Rowan converts protection into political pressure.',2);
SET @o=(SELECT dynamic_option_id FROM plot_dynamic_opportunity_options
  WHERE dynamic_opportunity_id=@support AND option_code='REVIEW_FROM_STRENGTH' LIMIT 1);
INSERT INTO plot_dynamic_option_effects
(dynamic_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order) VALUES
(@o,'FACTOR','LEGITIMACY',3,NULL,'Rowan retains standing while permitting limited scrutiny.',1),
(@o,'FACTOR','COURT_TRUST',4,NULL,'Allowing review from a protected position restores confidence in process.',2);
SET @o=(SELECT dynamic_option_id FROM plot_dynamic_opportunity_options
  WHERE dynamic_opportunity_id=@support AND option_code='HOLD_SUPPORT_IN_RESERVE' LIMIT 1);
INSERT INTO plot_dynamic_option_effects
(dynamic_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order) VALUES
(@o,'FACTOR','STABILITY',2,NULL,'Maintaining protection without escalating gives the court time to respond.',1);

-- Opposition branch.
INSERT INTO plot_dynamic_opportunities
(plot_id,opportunity_code,role_code,prompt_text,window_seconds,once_only,priority,active,reconsideration_key,opportunity_kind)
VALUES
(@plot,'ROWAN_RESPOND_TO_OPPOSITION','ROWAN',
 'Captain Reed has placed the council under protection instead of securing your position. Institutional resistance is forming while the succession remains unresolved. Do you counter it with political momentum, offer a controlled compromise, or wait for the resistance to reveal its limits?',
 25,1,39,1,NULL,'GOAL_PURSUIT')
ON DUPLICATE KEY UPDATE
prompt_text=VALUES(prompt_text),window_seconds=VALUES(window_seconds),priority=VALUES(priority),
active=1,opportunity_kind='GOAL_PURSUIT';
SET @opposition=(SELECT dynamic_opportunity_id FROM plot_dynamic_opportunities
  WHERE plot_id=@plot AND opportunity_code='ROWAN_RESPOND_TO_OPPOSITION' LIMIT 1);
DELETE FROM plot_dynamic_opportunity_conditions WHERE dynamic_opportunity_id=@opposition;
INSERT INTO plot_dynamic_opportunity_conditions
(dynamic_opportunity_id,source_type,source_key,operator,compare_value,sort_order) VALUES
(@opposition,'PHASE',NULL,'EQ','RISING_ACTION',1),
(@opposition,'WORLD_STATE','CROWN_HOLDER','EQ','NONE',2),
(@opposition,'WORLD_STATE','ELIAN_CLAIM','IN','SECONDARY,ACTIVE',3),
(@opposition,'OPTION_CHOICE_COUNT','GUARD_COUNCIL','GTE','1',4),
(@opposition,'ACTION_COUNT',NULL,'GTE','1',5),
(@opposition,'STAT','AMBITION','GTE','70',6);
DELETE FROM plot_dynamic_opportunity_options WHERE dynamic_opportunity_id=@opposition;
INSERT INTO plot_dynamic_opportunity_options
(dynamic_opportunity_id,option_code,label,result_text,npc_weight,rationale_text,sort_order) VALUES
(@opposition,'COUNTER_OPPOSITION',
 'Rally councillors before institutional resistance can define the succession.',
 'Rowan answers institutional resistance by building political momentum of his own.',
 45,'High ambition favors meeting organized resistance with organized support.',1),
(@opposition,'OFFER_CONTROLLED_COMPROMISE',
 'Offer a formal review if the council recognizes you as provisional heir.',
 'Rowan tries to channel institutional resistance into a process he can influence.',
 35,'A controlled process can preserve Rowan’s claim while acknowledging real resistance.',2),
(@opposition,'WAIT_FOR_LIMITS',
 'Keep your supporters ready and wait until the resistance reveals its limits.',
 'Rowan delays escalation while testing the strength of institutional resistance.',
 20,'Waiting can avoid overcommitting before Rowan understands the resistance.',3);
SET @o=(SELECT dynamic_option_id FROM plot_dynamic_opportunity_options
  WHERE dynamic_opportunity_id=@opposition AND option_code='COUNTER_OPPOSITION' LIMIT 1);
INSERT INTO plot_dynamic_option_effects
(dynamic_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order) VALUES
(@o,'FACTOR','LEGITIMACY',4,NULL,'Rowan’s organized response preserves support against institutional resistance.',1),
(@o,'FACTOR','UNREST',4,NULL,'Competing political mobilization increases succession tension.',2);
SET @o=(SELECT dynamic_option_id FROM plot_dynamic_opportunity_options
  WHERE dynamic_opportunity_id=@opposition AND option_code='OFFER_CONTROLLED_COMPROMISE' LIMIT 1);
INSERT INTO plot_dynamic_option_effects
(dynamic_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order) VALUES
(@o,'FACTOR','LEGITIMACY',2,NULL,'Conditional recognition preserves part of Rowan’s standing.',1),
(@o,'FACTOR','COURT_TRUST',3,NULL,'Acknowledging institutional resistance gives the court a procedural path.',2);
SET @o=(SELECT dynamic_option_id FROM plot_dynamic_opportunity_options
  WHERE dynamic_opportunity_id=@opposition AND option_code='WAIT_FOR_LIMITS' LIMIT 1);
INSERT INTO plot_dynamic_option_effects
(dynamic_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order) VALUES
(@o,'FACTOR','STABILITY',2,NULL,'Avoiding immediate escalation gives the court time to assess the resistance.',1);

INSERT IGNORE INTO schema_migrations(migration)
VALUES('031_distinguish_support_and_opposition.sql');
