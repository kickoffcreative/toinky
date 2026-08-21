-- Generic reaction/cascade expansion.
-- Dynamic opportunities can now belong to PLAYER or NPC roles. Conditions can read world state,
-- world factors, location, motives, stats, knowledge, relationships, phase and prior action count.
-- Plot data supplies the meaning; PHP only evaluates conditions and runs the normal decision pipeline.

ALTER TABLE plot_dynamic_opportunity_conditions
  MODIFY source_type ENUM('WORLD_STATE','FACTOR','LOCATION','GOAL','FEAR','STAT','KNOWLEDGE','RELATIONSHIP','PHASE','ACTION_COUNT') NOT NULL DEFAULT 'WORLD_STATE';

ALTER TABLE plot_dynamic_opportunity_options
  ADD COLUMN IF NOT EXISTS npc_weight INT NOT NULL DEFAULT 1 AFTER result_text,
  ADD COLUMN IF NOT EXISTS rationale_text TEXT NULL AFTER npc_weight;

ALTER TABLE plot_triggered_transition_conditions
  MODIFY operator ENUM('EQ','NEQ','IN','NOT_IN','EXISTS','NOT_EXISTS','LT','LTE','GT','GTE') NOT NULL DEFAULT 'EQ';

ALTER TABLE plot_motive_lifecycle_conditions
  MODIFY operator ENUM('EQ','NEQ','IN','NOT_IN','EXISTS','NOT_EXISTS','LT','LTE','GT','GTE') NOT NULL DEFAULT 'EQ';

SET @plot=(SELECT plot_id FROM plots WHERE code='EMPTY_THRONE' LIMIT 1);

-- ---------------------------------------------------------------------------
-- PLAYER CASCADES: missed first choices do not make a player irrelevant.
-- ---------------------------------------------------------------------------

INSERT INTO plot_dynamic_opportunities(plot_id,opportunity_code,role_code,prompt_text,window_seconds,once_only,priority,active)
VALUES(@plot,'ELIAN_SECOND_CHANCE','ELIAN','The succession is still unresolved and your legal path is closing. Do you activate your claim, demand neutral review of the letter, or remain silent?',35,1,30,1)
ON DUPLICATE KEY UPDATE prompt_text=VALUES(prompt_text),window_seconds=VALUES(window_seconds),priority=VALUES(priority),active=1;
SET @d=(SELECT dynamic_opportunity_id FROM plot_dynamic_opportunities WHERE plot_id=@plot AND opportunity_code='ELIAN_SECOND_CHANCE');
DELETE FROM plot_dynamic_opportunity_conditions WHERE dynamic_opportunity_id=@d;
INSERT INTO plot_dynamic_opportunity_conditions(dynamic_opportunity_id,source_type,source_key,operator,compare_value,sort_order) VALUES
(@d,'PHASE',NULL,'IN','INCITING_INCIDENT,RISING_ACTION',1),
(@d,'ACTION_COUNT',NULL,'LTE','0',2),
(@d,'WORLD_STATE','CROWN_HOLDER','EQ','NONE',3);
DELETE FROM plot_dynamic_opportunity_options WHERE dynamic_opportunity_id=@d;
INSERT INTO plot_dynamic_opportunity_options(dynamic_opportunity_id,option_code,label,result_text,npc_weight,rationale_text,sort_order) VALUES
(@d,'ACTIVATE_CLAIM','State that you will pursue the crown only through lawful review.','Elian formally activates his claim while rejecting a seizure of power.',1,NULL,1),
(@d,'DEMAND_REVIEW','Demand neutral custody and formal review of the succession letter.','Elian asks the council to separate the evidence from either claimant.',1,NULL,2),
(@d,'REMAIN_SILENT','Remain silent and avoid escalating the succession fight.','Elian leaves the initiative to the other institutions.',1,NULL,3);
SET @o=(SELECT dynamic_option_id FROM plot_dynamic_opportunity_options WHERE dynamic_opportunity_id=@d AND option_code='ACTIVATE_CLAIM');
INSERT INTO plot_dynamic_option_effects(dynamic_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order) VALUES
(@o,'WORLD_STATE','ELIAN_CLAIM',NULL,'ACTIVE','Elian becomes an active lawful claimant.',1),
(@o,'FACTOR','LEGITIMACY',5,NULL,'A declared legal process gives the alternative claim a clearer constitutional basis.',2),
(@o,'GOAL','SELF',NULL,'Build enough lawful support to make the council choose between the two claims.','Elian moves from private possibility to an active political objective.',3);
SET @o=(SELECT dynamic_option_id FROM plot_dynamic_opportunity_options WHERE dynamic_opportunity_id=@d AND option_code='DEMAND_REVIEW');
INSERT INTO plot_dynamic_option_effects(dynamic_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order) VALUES
(@o,'FACTOR','COURT_TRUST',6,NULL,'A neutral review gives competing factions a process they can share.',1),
(@o,'FACTOR','SECRECY',-8,NULL,'Demanding review makes the hidden succession issue harder to contain.',2);

INSERT INTO plot_dynamic_opportunities(plot_id,opportunity_code,role_code,prompt_text,window_seconds,once_only,priority,active)
VALUES(@plot,'MARA_ORDER_GAP','MARA','The capital is tense while the court argues over succession. Do you protect supplies, broker a council pause, or let the claimants settle it?',35,1,35,1)
ON DUPLICATE KEY UPDATE prompt_text=VALUES(prompt_text),window_seconds=VALUES(window_seconds),priority=VALUES(priority),active=1;
SET @d=(SELECT dynamic_opportunity_id FROM plot_dynamic_opportunities WHERE plot_id=@plot AND opportunity_code='MARA_ORDER_GAP');
DELETE FROM plot_dynamic_opportunity_conditions WHERE dynamic_opportunity_id=@d;
INSERT INTO plot_dynamic_opportunity_conditions(dynamic_opportunity_id,source_type,source_key,operator,compare_value,sort_order) VALUES
(@d,'PHASE',NULL,'IN','INCITING_INCIDENT,RISING_ACTION',1),
(@d,'ACTION_COUNT',NULL,'LTE','0',2),
(@d,'FACTOR','UNREST','GTE','28',3);
DELETE FROM plot_dynamic_opportunity_options WHERE dynamic_opportunity_id=@d;
INSERT INTO plot_dynamic_opportunity_options(dynamic_opportunity_id,option_code,label,result_text,npc_weight,rationale_text,sort_order) VALUES
(@d,'SECURE_SUPPLIES','Use your grain network to secure food deliveries and calm the capital.','Mara shifts resources away from court politics and toward public stability.',1,NULL,1),
(@d,'BROKER_PAUSE','Broker a temporary council agreement that neither prince will force a coronation.','Mara creates a procedural pause both factions can publicly accept.',1,NULL,2),
(@d,'STAND_BACK','Stay out of the confrontation and preserve your influence.','Mara keeps her political capital in reserve.',1,NULL,3);
SET @o=(SELECT dynamic_option_id FROM plot_dynamic_opportunity_options WHERE dynamic_opportunity_id=@d AND option_code='SECURE_SUPPLIES');
INSERT INTO plot_dynamic_option_effects(dynamic_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order) VALUES
(@o,'FACTOR','STABILITY',8,NULL,'Reliable supplies reduce the chance that succession panic becomes a civic crisis.',1),
(@o,'FACTOR','UNREST',-7,NULL,'Visible food security reduces pressure in the capital.',2),
(@o,'WORLD_STATE','CAPITAL_SECURITY',NULL,'CALM','The capital settles as supply routes remain dependable.',3),
(@o,'GOAL','SELF',NULL,'Keep the political settlement peaceful now that the capital is supplied.','Mara completes the immediate supply problem and shifts to political stabilization.',4);
SET @o=(SELECT dynamic_option_id FROM plot_dynamic_opportunity_options WHERE dynamic_opportunity_id=@d AND option_code='BROKER_PAUSE');
INSERT INTO plot_dynamic_option_effects(dynamic_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order) VALUES
(@o,'FACTOR','COURT_TRUST',8,NULL,'A shared pause gives rivals one point of cooperation.',1),
(@o,'FACTOR','LEGITIMACY',4,NULL,'A recognized process strengthens whichever result eventually emerges.',2);

INSERT INTO plot_dynamic_opportunities(plot_id,opportunity_code,role_code,prompt_text,window_seconds,once_only,priority,active)
VALUES(@plot,'TOMAS_ROWAN_CUSTODY','TOMAS','Rowan now controls the disputed succession document. As commander of the guard, do you place it in neutral custody, lock down the palace, or stand aside?',35,1,15,1)
ON DUPLICATE KEY UPDATE prompt_text=VALUES(prompt_text),window_seconds=VALUES(window_seconds),priority=VALUES(priority),active=1;
SET @d=(SELECT dynamic_opportunity_id FROM plot_dynamic_opportunities WHERE plot_id=@plot AND opportunity_code='TOMAS_ROWAN_CUSTODY');
DELETE FROM plot_dynamic_opportunity_conditions WHERE dynamic_opportunity_id=@d;
INSERT INTO plot_dynamic_opportunity_conditions(dynamic_opportunity_id,source_type,source_key,operator,compare_value,sort_order) VALUES
(@d,'WORLD_STATE','LETTER_HOLDER','EQ','ROWAN',1),
(@d,'PHASE',NULL,'IN','INCITING_INCIDENT,RISING_ACTION',2),
(@d,'ACTION_COUNT',NULL,'LTE','0',3);
DELETE FROM plot_dynamic_opportunity_options WHERE dynamic_opportunity_id=@d;
INSERT INTO plot_dynamic_opportunity_options(dynamic_opportunity_id,option_code,label,result_text,npc_weight,rationale_text,sort_order) VALUES
(@d,'TAKE_NEUTRAL_CUSTODY','Put the succession letter under neutral palace-guard custody.','Tomas invokes the guard’s duty to lawful process and removes the document from a claimant.',1,NULL,1),
(@d,'LOCKDOWN','Lock down the palace but leave the document where it is.','Tomas secures movement around the palace without directly choosing a claimant.',1,NULL,2),
(@d,'STAND_ASIDE','Refuse to use the guard unless the council issues a lawful order.','Tomas keeps the guard formally neutral while Rowan retains the initiative.',1,NULL,3);
SET @o=(SELECT dynamic_option_id FROM plot_dynamic_opportunity_options WHERE dynamic_opportunity_id=@d AND option_code='TAKE_NEUTRAL_CUSTODY');
INSERT INTO plot_dynamic_option_effects(dynamic_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order) VALUES
(@o,'WORLD_STATE','LETTER_HOLDER',NULL,'TOMAS','The disputed document enters neutral guard custody.',1),
(@o,'FACTOR','COURT_TRUST',7,NULL,'Neutral custody makes the process look less captured by either prince.',2),
(@o,'GOAL','SELF',NULL,'Keep the succession evidence secure until lawful authority decides what it means.','Tomas turns his general security duty into a concrete custodial objective.',3);
SET @o=(SELECT dynamic_option_id FROM plot_dynamic_opportunity_options WHERE dynamic_opportunity_id=@d AND option_code='LOCKDOWN');
INSERT INTO plot_dynamic_option_effects(dynamic_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order) VALUES
(@o,'WORLD_STATE','CAPITAL_SECURITY',NULL,'LOCKDOWN','The palace guard restricts movement around the seat of government.',1),
(@o,'FACTOR','STABILITY',4,NULL,'The lockdown reduces immediate seizure risk.',2),
(@o,'FACTOR','UNREST',4,NULL,'A visible military posture also alarms the capital.',3);

-- ---------------------------------------------------------------------------
-- NPC CASCADES: NPCs react to state created by earlier NPC/player choices.
-- ---------------------------------------------------------------------------

INSERT INTO plot_dynamic_opportunities(plot_id,opportunity_code,role_code,prompt_text,window_seconds,once_only,priority,active)
VALUES(@plot,'QUEEN_ROWAN_HAS_LETTER','QUEEN','Rowan has taken control of the document you witnessed him sign. Do you expose your testimony, demand neutral custody, or withdraw from the struggle?',20,1,10,1)
ON DUPLICATE KEY UPDATE prompt_text=VALUES(prompt_text),window_seconds=VALUES(window_seconds),priority=VALUES(priority),active=1;
SET @d=(SELECT dynamic_opportunity_id FROM plot_dynamic_opportunities WHERE plot_id=@plot AND opportunity_code='QUEEN_ROWAN_HAS_LETTER');
DELETE FROM plot_dynamic_opportunity_conditions WHERE dynamic_opportunity_id=@d;
INSERT INTO plot_dynamic_opportunity_conditions(dynamic_opportunity_id,source_type,source_key,operator,compare_value,sort_order) VALUES
(@d,'WORLD_STATE','LETTER_HOLDER','EQ','ROWAN',1),
(@d,'WORLD_STATE','LETTER_STATUS','NOT_IN','DESTROYED,PUBLIC',2),
(@d,'PHASE',NULL,'IN','INCITING_INCIDENT,RISING_ACTION',3);
DELETE FROM plot_dynamic_opportunity_options WHERE dynamic_opportunity_id=@d;
INSERT INTO plot_dynamic_opportunity_options(dynamic_opportunity_id,option_code,label,result_text,npc_weight,rationale_text,sort_order) VALUES
(@d,'TESTIFY_PUBLICLY','State publicly that you witnessed Rowan sign the abdication letter.','The Queen makes the document’s authenticity a public constitutional issue.',55,'Dynastic loyalty favors preventing one claimant from privately controlling decisive evidence.',1),
(@d,'DEMAND_HAND_CUSTODY','Demand that the King’s Hand take neutral custody of the document.','The Queen tries to move the letter from claimant control into institutional custody.',30,'Caution favors neutral custody over open accusation.',2),
(@d,'WITHDRAW_AGAIN','Withdraw and refuse to legitimize either prince while the letter is controlled by a claimant.','The Queen removes her personal legitimacy from the immediate struggle.',15,'Withdrawal avoids direct escalation but gives others more room to act.',3);
SET @o=(SELECT dynamic_option_id FROM plot_dynamic_opportunity_options WHERE dynamic_opportunity_id=@d AND option_code='TESTIFY_PUBLICLY');
INSERT INTO plot_dynamic_option_effects(dynamic_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order) VALUES
(@o,'WORLD_STATE','LETTER_STATUS',NULL,'VERIFIED','The Queen’s firsthand testimony verifies that Rowan signed the abdication.',1),
(@o,'FACTOR','SECRECY',-20,NULL,'The succession secret can no longer be contained inside a few private conversations.',2),
(@o,'FACTOR','UNREST',5,NULL,'Public confirmation of a disputed abdication shocks the capital.',3);
SET @o=(SELECT dynamic_option_id FROM plot_dynamic_opportunity_options WHERE dynamic_opportunity_id=@d AND option_code='DEMAND_HAND_CUSTODY');
INSERT INTO plot_dynamic_option_effects(dynamic_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order) VALUES
(@o,'WORLD_STATE','LETTER_HOLDER',NULL,'HAND','The King’s Hand takes institutional custody of the letter.',1),
(@o,'FACTOR','COURT_TRUST',5,NULL,'Institutional custody reassures some councillors.',2);
SET @o=(SELECT dynamic_option_id FROM plot_dynamic_opportunity_options WHERE dynamic_opportunity_id=@d AND option_code='WITHDRAW_AGAIN');
INSERT INTO plot_dynamic_option_effects(dynamic_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order) VALUES
(@o,'WORLD_STATE','QUEEN_POSITION',NULL,'WITHDRAWN','The Queen refuses to participate while the succession evidence remains captured by a claimant.',1);

INSERT INTO plot_dynamic_opportunities(plot_id,opportunity_code,role_code,prompt_text,window_seconds,once_only,priority,active)
VALUES(@plot,'HAND_VERIFIED_ABDICATION','HAND','The abdication is now credibly verified. Do you keep the government neutral, support Elian’s legal claim, or suppress the dispute and back Rowan?',20,1,12,1)
ON DUPLICATE KEY UPDATE prompt_text=VALUES(prompt_text),window_seconds=VALUES(window_seconds),priority=VALUES(priority),active=1;
SET @d=(SELECT dynamic_opportunity_id FROM plot_dynamic_opportunities WHERE plot_id=@plot AND opportunity_code='HAND_VERIFIED_ABDICATION');
DELETE FROM plot_dynamic_opportunity_conditions WHERE dynamic_opportunity_id=@d;
INSERT INTO plot_dynamic_opportunity_conditions(dynamic_opportunity_id,source_type,source_key,operator,compare_value,sort_order) VALUES
(@d,'WORLD_STATE','LETTER_STATUS','EQ','VERIFIED',1),
(@d,'WORLD_STATE','CROWN_HOLDER','EQ','NONE',2),
(@d,'PHASE',NULL,'IN','INCITING_INCIDENT,RISING_ACTION,CLIMAX',3);
DELETE FROM plot_dynamic_opportunity_options WHERE dynamic_opportunity_id=@d;
INSERT INTO plot_dynamic_opportunity_options(dynamic_opportunity_id,option_code,label,result_text,npc_weight,rationale_text,sort_order) VALUES
(@d,'KEEP_NEUTRAL','Place the document under institutional review and keep government neutral.','The Hand refuses to let either claimant convert disputed evidence directly into power.',45,'Institutional loyalty favors a process that can survive either outcome.',1),
(@d,'BACK_ELIAN','Recognize that Rowan’s verified abdication creates a lawful path for Elian.','The Hand moves government support toward Elian’s alternative claim.',35,'Verified evidence makes the alternative claim materially stronger.',2),
(@d,'BACK_ROWAN_ANYWAY','Treat the abdication as withdrawn and keep the government behind Rowan.','The Hand chooses continuity over the disputed document.',20,'Pragmatism may still favor the presumed heir despite legal uncertainty.',3);
SET @o=(SELECT dynamic_option_id FROM plot_dynamic_opportunity_options WHERE dynamic_opportunity_id=@d AND option_code='KEEP_NEUTRAL');
INSERT INTO plot_dynamic_option_effects(dynamic_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order) VALUES
(@o,'WORLD_STATE','HAND_LOYALTY',NULL,'NEUTRAL','The machinery of government stays neutral during formal review.',1),
(@o,'WORLD_STATE','LETTER_HOLDER',NULL,'HAND','The succession letter enters institutional custody.',2),
(@o,'FACTOR','COURT_TRUST',7,NULL,'Neutral review restores some confidence in the council process.',3);
SET @o=(SELECT dynamic_option_id FROM plot_dynamic_opportunity_options WHERE dynamic_opportunity_id=@d AND option_code='BACK_ELIAN');
INSERT INTO plot_dynamic_option_effects(dynamic_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order) VALUES
(@o,'WORLD_STATE','HAND_LOYALTY',NULL,'ELIAN','The King’s Hand aligns government authority with Elian.',1),
(@o,'WORLD_STATE','ELIAN_CLAIM',NULL,'SUPPORTED','Elian’s claim gains institutional support.',2),
(@o,'FACTOR','LEGITIMACY',10,NULL,'Institutional support gives Elian’s claim substantial legitimacy.',3),
(@o,'FACTOR','UNREST',5,NULL,'Changing the expected succession creates public uncertainty.',4);
SET @o=(SELECT dynamic_option_id FROM plot_dynamic_opportunity_options WHERE dynamic_opportunity_id=@d AND option_code='BACK_ROWAN_ANYWAY');
INSERT INTO plot_dynamic_option_effects(dynamic_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order) VALUES
(@o,'WORLD_STATE','HAND_LOYALTY',NULL,'ROWAN','The Hand keeps government authority behind Rowan despite the verified abdication.',1),
(@o,'FACTOR','COURT_TRUST',-8,NULL,'Ignoring verified evidence fractures confidence in the government.',2),
(@o,'FACTOR','SECRECY',8,NULL,'The government attempts to contain the significance of the abdication.',3);

INSERT INTO plot_dynamic_opportunities(plot_id,opportunity_code,role_code,prompt_text,window_seconds,once_only,priority,active)
VALUES(@plot,'ROWAN_VERIFIED_LETTER','ROWAN','The abdication you signed is now verified and still under your control. Do you destroy it, rush toward coronation, or attack the Queen’s credibility?',20,1,14,1)
ON DUPLICATE KEY UPDATE prompt_text=VALUES(prompt_text),window_seconds=VALUES(window_seconds),priority=VALUES(priority),active=1;
SET @d=(SELECT dynamic_opportunity_id FROM plot_dynamic_opportunities WHERE plot_id=@plot AND opportunity_code='ROWAN_VERIFIED_LETTER');
DELETE FROM plot_dynamic_opportunity_conditions WHERE dynamic_opportunity_id=@d;
INSERT INTO plot_dynamic_opportunity_conditions(dynamic_opportunity_id,source_type,source_key,operator,compare_value,sort_order) VALUES
(@d,'WORLD_STATE','LETTER_STATUS','EQ','VERIFIED',1),
(@d,'WORLD_STATE','LETTER_HOLDER','EQ','ROWAN',2),
(@d,'WORLD_STATE','CROWN_HOLDER','EQ','NONE',3);
DELETE FROM plot_dynamic_opportunity_options WHERE dynamic_opportunity_id=@d;
INSERT INTO plot_dynamic_opportunity_options(dynamic_opportunity_id,option_code,label,result_text,npc_weight,rationale_text,sort_order) VALUES
(@d,'DESTROY_LETTER','Destroy the abdication letter before another institution can take it.','Rowan chooses physical destruction over legal argument.',45,'Ambition and self-interest strongly favor removing decisive evidence when he still controls it.',1),
(@d,'RUSH_CORONATION','Demand coronation immediately and argue that the abdication was withdrawn.','Rowan tries to turn political speed into irreversible fact.',35,'Ambition favors converting presumption into possession of the crown.',2),
(@d,'DISCREDIT_QUEEN','Attack the Queen’s memory and motives instead of touching the document.','Rowan makes the dispute about credibility rather than the text itself.',20,'Pride favors contesting the witness rather than appearing afraid of the letter.',3);
SET @o=(SELECT dynamic_option_id FROM plot_dynamic_opportunity_options WHERE dynamic_opportunity_id=@d AND option_code='DESTROY_LETTER');
INSERT INTO plot_dynamic_option_effects(dynamic_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order) VALUES
(@o,'WORLD_STATE','LETTER_STATUS',NULL,'DESTROYED','The original abdication letter is destroyed while Rowan controls it.',1),
(@o,'FACTOR','COURT_TRUST',-12,NULL,'Destruction of disputed evidence shatters confidence in Rowan’s process.',2),
(@o,'FACTOR','UNREST',10,NULL,'Rumors of destroyed succession evidence inflame the capital.',3),
(@o,'FEAR','SELF',NULL,'The court treating destruction of the letter as proof of guilt.','Rowan removes the document but creates a more dangerous political fear.',4);
SET @o=(SELECT dynamic_option_id FROM plot_dynamic_opportunity_options WHERE dynamic_opportunity_id=@d AND option_code='RUSH_CORONATION');
INSERT INTO plot_dynamic_option_effects(dynamic_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order) VALUES
(@o,'FACTOR','LEGITIMACY',5,NULL,'Some institutions value continuity and speed.',1),
(@o,'FACTOR','UNREST',7,NULL,'A rushed coronation attempt intensifies public division.',2);
SET @o=(SELECT dynamic_option_id FROM plot_dynamic_opportunity_options WHERE dynamic_opportunity_id=@d AND option_code='DISCREDIT_QUEEN');
INSERT INTO plot_dynamic_option_effects(dynamic_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order) VALUES
(@o,'FACTOR','COURT_TRUST',-6,NULL,'The royal family’s public credibility fight damages the council.',1),
(@o,'FACTOR','SECRECY',4,NULL,'Confusion over testimony partially obscures the underlying document.',2);

-- A verified letter that is not in Rowan's possession gives Elian a new state-driven opportunity,
-- even if he acted earlier on a different problem.
INSERT INTO plot_dynamic_opportunities(plot_id,opportunity_code,role_code,prompt_text,window_seconds,once_only,priority,active)
VALUES(@plot,'ELIAN_VERIFIED_PATH','ELIAN','The abdication is now verified. Do you petition for recognition, insist on a full council judgment, or refuse the crown if force would be required?',35,1,18,1)
ON DUPLICATE KEY UPDATE prompt_text=VALUES(prompt_text),window_seconds=VALUES(window_seconds),priority=VALUES(priority),active=1;
SET @d=(SELECT dynamic_opportunity_id FROM plot_dynamic_opportunities WHERE plot_id=@plot AND opportunity_code='ELIAN_VERIFIED_PATH');
DELETE FROM plot_dynamic_opportunity_conditions WHERE dynamic_opportunity_id=@d;
INSERT INTO plot_dynamic_opportunity_conditions(dynamic_opportunity_id,source_type,source_key,operator,compare_value,sort_order) VALUES
(@d,'WORLD_STATE','LETTER_STATUS','EQ','VERIFIED',1),
(@d,'WORLD_STATE','LETTER_HOLDER','NEQ','ROWAN',2),
(@d,'WORLD_STATE','CROWN_HOLDER','EQ','NONE',3),
(@d,'PHASE',NULL,'IN','RISING_ACTION,CLIMAX',4);
DELETE FROM plot_dynamic_opportunity_options WHERE dynamic_opportunity_id=@d;
INSERT INTO plot_dynamic_opportunity_options(dynamic_opportunity_id,option_code,label,result_text,npc_weight,rationale_text,sort_order) VALUES
(@d,'PETITION_RECOGNITION','Petition the council to recognize you as the lawful successor.','Elian converts the verified abdication into a direct claim for recognition.',1,NULL,1),
(@d,'FULL_JUDGMENT','Insist that the whole council judge the document before anyone is crowned.','Elian prioritizes legitimacy over speed.',1,NULL,2),
(@d,'NO_FORCE','Declare that you will not take the crown by military force even if Rowan resists.','Elian limits his own path to preserve the kingdom.',1,NULL,3);
SET @o=(SELECT dynamic_option_id FROM plot_dynamic_opportunity_options WHERE dynamic_opportunity_id=@d AND option_code='PETITION_RECOGNITION');
INSERT INTO plot_dynamic_option_effects(dynamic_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order) VALUES
(@o,'WORLD_STATE','ELIAN_CLAIM',NULL,'SUPPORTED','The verified abdication becomes the foundation of Elian’s supported claim.',1),
(@o,'FACTOR','LEGITIMACY',9,NULL,'The alternative claim gains legitimacy from verified evidence.',2),
(@o,'GOAL','SELF',NULL,'Secure council recognition and prevent Rowan from forcing a rival coronation.','Elian’s objective narrows around a now-credible path to succession.',3);
SET @o=(SELECT dynamic_option_id FROM plot_dynamic_opportunity_options WHERE dynamic_opportunity_id=@d AND option_code='FULL_JUDGMENT');
INSERT INTO plot_dynamic_option_effects(dynamic_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order) VALUES
(@o,'FACTOR','COURT_TRUST',8,NULL,'Elian accepts institutional judgment rather than claiming automatic victory.',1),
(@o,'FACTOR','LEGITIMACY',5,NULL,'A full judgment strengthens the authority of the eventual result.',2);
SET @o=(SELECT dynamic_option_id FROM plot_dynamic_opportunity_options WHERE dynamic_opportunity_id=@d AND option_code='NO_FORCE');
INSERT INTO plot_dynamic_option_effects(dynamic_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order) VALUES
(@o,'FACTOR','STABILITY',7,NULL,'Elian’s restraint reduces the immediate risk of dynastic violence.',1),
(@o,'FACTOR','UNREST',-4,NULL,'A public rejection of force calms some supporters.',2),
(@o,'FEAR','SELF',NULL,'Losing a lawful claim because you refuse to plunge Valedorn into war.','Elian transforms his fear from causing war to the cost of restraint.',3);

-- ---------------------------------------------------------------------------
-- GENERIC STATE CASCADES / INACTION PRESSURE.
-- These transitions fire from state combinations, not from specific character actions.
-- ---------------------------------------------------------------------------

INSERT INTO plot_triggered_transitions(plot_id,transition_code,label,once_only,priority,active)
VALUES(@plot,'VERIFIED_ABDICATION_DISPUTES_ROWAN','Verified abdication changes the legal succession',1,20,1)
ON DUPLICATE KEY UPDATE label=VALUES(label),priority=VALUES(priority),active=1;
SET @tr=(SELECT transition_id FROM plot_triggered_transitions WHERE plot_id=@plot AND transition_code='VERIFIED_ABDICATION_DISPUTES_ROWAN');
DELETE FROM plot_triggered_transition_conditions WHERE transition_id=@tr;
INSERT INTO plot_triggered_transition_conditions(transition_id,state_key,operator,compare_value,sort_order) VALUES
(@tr,'LETTER_STATUS','EQ','VERIFIED',1),
(@tr,'ROWAN_CLAIM','EQ','PRESUMED_HEIR',2);
DELETE FROM plot_triggered_transition_effects WHERE transition_id=@tr;
INSERT INTO plot_triggered_transition_effects(transition_id,effect_scope,target_role_code,target_code,delta_value,set_value,detail_text,sort_order) VALUES
(@tr,'WORLD_STATE',NULL,'ROWAN_CLAIM',NULL,'DISPUTED','Verified abdication evidence means Rowan is no longer an uncontested presumed heir.',1),
(@tr,'WORLD_STATE',NULL,'ELIAN_CLAIM',NULL,'ACTIVE','A lawful alternative claim becomes materially possible.',2),
(@tr,'FACTOR',NULL,'SECRECY',-8,NULL,'The legal significance of the succession secret becomes harder to contain.',3);

INSERT INTO plot_triggered_transitions(plot_id,transition_code,label,once_only,priority,active)
VALUES(@plot,'ROWAN_CONTROL_BUILDS_MOMENTUM','Unchallenged control of the letter strengthens Rowan',1,80,1)
ON DUPLICATE KEY UPDATE label=VALUES(label),priority=VALUES(priority),active=1;
SET @tr=(SELECT transition_id FROM plot_triggered_transitions WHERE plot_id=@plot AND transition_code='ROWAN_CONTROL_BUILDS_MOMENTUM');
DELETE FROM plot_triggered_transition_conditions WHERE transition_id=@tr;
INSERT INTO plot_triggered_transition_conditions(transition_id,state_key,operator,compare_value,sort_order) VALUES
(@tr,'LETTER_HOLDER','EQ','ROWAN',1),
(@tr,'LETTER_STATUS','IN','SEALED,FOUND',2),
(@tr,'STORY_PHASE','IN','RISING_ACTION,CLIMAX',3),
(@tr,'CROWN_HOLDER','EQ','NONE',4);
DELETE FROM plot_triggered_transition_effects WHERE transition_id=@tr;
INSERT INTO plot_triggered_transition_effects(transition_id,effect_scope,target_role_code,target_code,delta_value,set_value,detail_text,sort_order) VALUES
(@tr,'FACTOR',NULL,'LEGITIMACY',6,NULL,'As time passes without effective challenge, Rowan’s possession begins to look like political control.',1),
(@tr,'FACTOR',NULL,'SECRECY',6,NULL,'Private control of the succession evidence lets Rowan shape what others can prove.',2),
(@tr,'FACTOR',NULL,'COURT_TRUST',-4,NULL,'The longer a claimant controls disputed evidence, the less neutral the process appears.',3);

INSERT INTO plot_triggered_transitions(plot_id,transition_code,label,once_only,priority,active)
VALUES(@plot,'ROWAN_CORONATION_MOMENTUM','Government backing carries Rowan to coronation',1,150,1)
ON DUPLICATE KEY UPDATE label=VALUES(label),priority=VALUES(priority),active=1;
SET @tr=(SELECT transition_id FROM plot_triggered_transitions WHERE plot_id=@plot AND transition_code='ROWAN_CORONATION_MOMENTUM');
DELETE FROM plot_triggered_transition_conditions WHERE transition_id=@tr;
INSERT INTO plot_triggered_transition_conditions(transition_id,state_key,operator,compare_value,sort_order) VALUES
(@tr,'HAND_LOYALTY','EQ','ROWAN',1),
(@tr,'ROWAN_CLAIM','IN','PRESUMED_HEIR,DISPUTED',2),
(@tr,'CROWN_HOLDER','EQ','NONE',3),
(@tr,'STORY_PHASE','IN','CLIMAX,RESOLUTION',4);
DELETE FROM plot_triggered_transition_effects WHERE transition_id=@tr;
INSERT INTO plot_triggered_transition_effects(transition_id,effect_scope,target_role_code,target_code,delta_value,set_value,detail_text,sort_order) VALUES
(@tr,'WORLD_STATE',NULL,'CROWN_HOLDER',NULL,'ROWAN','Government machinery and ceremonial momentum place the crown on Rowan.',1),
(@tr,'WORLD_STATE',NULL,'ROWAN_CLAIM',NULL,'CROWNED','Rowan converts political control into possession of the crown.',2),
(@tr,'FACTOR',NULL,'LEGITIMACY',10,NULL,'Coronation creates political fact even if the underlying dispute survives.',3),
(@tr,'FACTOR',NULL,'UNREST',5,NULL,'Opponents react to a coronation completed under disputed circumstances.',4);

INSERT INTO plot_triggered_transitions(plot_id,transition_code,label,once_only,priority,active)
VALUES(@plot,'ELIAN_CORONATION_MOMENTUM','Institutional support carries Elian to coronation',1,150,1)
ON DUPLICATE KEY UPDATE label=VALUES(label),priority=VALUES(priority),active=1;
SET @tr=(SELECT transition_id FROM plot_triggered_transitions WHERE plot_id=@plot AND transition_code='ELIAN_CORONATION_MOMENTUM');
DELETE FROM plot_triggered_transition_conditions WHERE transition_id=@tr;
INSERT INTO plot_triggered_transition_conditions(transition_id,state_key,operator,compare_value,sort_order) VALUES
(@tr,'HAND_LOYALTY','EQ','ELIAN',1),
(@tr,'ELIAN_CLAIM','EQ','SUPPORTED',2),
(@tr,'CROWN_HOLDER','EQ','NONE',3),
(@tr,'STORY_PHASE','IN','CLIMAX,RESOLUTION',4);
DELETE FROM plot_triggered_transition_effects WHERE transition_id=@tr;
INSERT INTO plot_triggered_transition_effects(transition_id,effect_scope,target_role_code,target_code,delta_value,set_value,detail_text,sort_order) VALUES
(@tr,'WORLD_STATE',NULL,'CROWN_HOLDER',NULL,'ELIAN','Institutional support and the verified succession challenge carry Elian to the crown.',1),
(@tr,'WORLD_STATE',NULL,'ELIAN_CLAIM',NULL,'CROWNED','Elian becomes the crowned claimant.',2),
(@tr,'FACTOR',NULL,'LEGITIMACY',12,NULL,'The crown is backed by both evidence and government recognition.',3),
(@tr,'FACTOR',NULL,'STABILITY',4,NULL,'A recognized constitutional path reduces immediate uncertainty.',4);

-- ---------------------------------------------------------------------------
-- MOTIVE LIFECYCLE EXAMPLES FOR STORY #2.
-- ---------------------------------------------------------------------------

INSERT INTO plot_motive_lifecycle_rules(plot_id,role_code,motive_type,match_text,terminal_status,replacement_text,reason_text,priority,active)
SELECT @plot,'ROWAN','GOAL','Be crowned before the court can challenge your claim.','ACHIEVED','Keep the crown and defeat the remaining challenge to your legitimacy.','Rowan has physically taken the crown.',20,1
WHERE NOT EXISTS(SELECT 1 FROM plot_motive_lifecycle_rules WHERE plot_id=@plot AND role_code='ROWAN' AND motive_type='GOAL' AND match_text='Be crowned before the court can challenge your claim.');
SET @r=(SELECT motive_rule_id FROM plot_motive_lifecycle_rules WHERE plot_id=@plot AND role_code='ROWAN' AND motive_type='GOAL' AND match_text='Be crowned before the court can challenge your claim.' LIMIT 1);
DELETE FROM plot_motive_lifecycle_conditions WHERE motive_rule_id=@r;
INSERT INTO plot_motive_lifecycle_conditions(motive_rule_id,state_key,operator,compare_value,sort_order) VALUES(@r,'CROWN_HOLDER','EQ','ROWAN',1);

INSERT INTO plot_motive_lifecycle_rules(plot_id,role_code,motive_type,match_text,terminal_status,replacement_text,reason_text,priority,active)
SELECT @plot,'ELIAN','GOAL','Secure a legitimate path to the crown without breaking the kingdom.','ACHIEVED','Hold the kingdom together and prove the new succession can govern.','Elian has been crowned through an institutional path.',20,1
WHERE NOT EXISTS(SELECT 1 FROM plot_motive_lifecycle_rules WHERE plot_id=@plot AND role_code='ELIAN' AND motive_type='GOAL' AND match_text='Secure a legitimate path to the crown without breaking the kingdom.');
SET @r=(SELECT motive_rule_id FROM plot_motive_lifecycle_rules WHERE plot_id=@plot AND role_code='ELIAN' AND motive_type='GOAL' AND match_text='Secure a legitimate path to the crown without breaking the kingdom.' LIMIT 1);
DELETE FROM plot_motive_lifecycle_conditions WHERE motive_rule_id=@r;
INSERT INTO plot_motive_lifecycle_conditions(motive_rule_id,state_key,operator,compare_value,sort_order) VALUES(@r,'CROWN_HOLDER','EQ','ELIAN',1);

INSERT INTO plot_motive_lifecycle_rules(plot_id,role_code,motive_type,match_text,terminal_status,replacement_text,reason_text,priority,active)
SELECT @plot,'MARA','GOAL','Keep the succession peaceful and the capital supplied.','ACHIEVED','Preserve the political settlement now that the capital is stable.','The capital is calm and the kingdom has reached a stable threshold.',25,1
WHERE NOT EXISTS(SELECT 1 FROM plot_motive_lifecycle_rules WHERE plot_id=@plot AND role_code='MARA' AND motive_type='GOAL' AND match_text='Keep the succession peaceful and the capital supplied.');
SET @r=(SELECT motive_rule_id FROM plot_motive_lifecycle_rules WHERE plot_id=@plot AND role_code='MARA' AND motive_type='GOAL' AND match_text='Keep the succession peaceful and the capital supplied.' LIMIT 1);
DELETE FROM plot_motive_lifecycle_conditions WHERE motive_rule_id=@r;
INSERT INTO plot_motive_lifecycle_conditions(motive_rule_id,state_key,operator,compare_value,sort_order) VALUES
(@r,'CAPITAL_SECURITY','EQ','CALM',1),(@r,'FACTOR:STABILITY','GTE','70',2);

INSERT IGNORE INTO schema_migrations(migration) VALUES('024_generic_reaction_cascade_engine.sql');
