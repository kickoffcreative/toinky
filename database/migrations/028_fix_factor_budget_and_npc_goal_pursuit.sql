-- Fix the Empty Throne factor-budget cleanup and add continuous NPC goal pursuit.
-- Goal pursuit reuses the existing dynamic-opportunity engine; no story-specific PHP is added.

ALTER TABLE plot_dynamic_opportunities
  ADD COLUMN IF NOT EXISTS opportunity_kind ENUM('REACTION','GOAL_PURSUIT','RECONSIDERATION') NOT NULL DEFAULT 'REACTION' AFTER reconsideration_key;

SET @plot=(SELECT plot_id FROM plots WHERE code='EMPTY_THRONE' LIMIT 1);

-- ---------------------------------------------------------------------------
-- FACTOR BUDGET REPAIR
-- Migration 027 targeted SECRET_CONTROL, but this plot's actual factor code is SECRECY.
-- Evidence/entity state now carries this causal meaning directly.
-- ---------------------------------------------------------------------------
DELETE sf
FROM simulation_factors sf
JOIN simulations s ON s.simulation_id=sf.simulation_id
WHERE s.plot_id=@plot AND sf.factor_code='SECRECY';

DELETE FROM plot_factors WHERE plot_id=@plot AND factor_code='SECRECY';

DELETE pdoe
FROM plot_dynamic_option_effects pdoe
JOIN plot_dynamic_opportunity_options pdoo ON pdoo.dynamic_option_id=pdoe.dynamic_option_id
JOIN plot_dynamic_opportunities pdo ON pdo.dynamic_opportunity_id=pdoo.dynamic_opportunity_id
WHERE pdo.plot_id=@plot AND pdoe.effect_scope='FACTOR' AND pdoe.target_code='SECRECY';

DELETE pce
FROM plot_consequence_effects pce
JOIN plot_consequence_rules pcr ON pcr.consequence_rule_id=pce.consequence_rule_id
WHERE pcr.plot_id=@plot AND pce.effect_scope='FACTOR' AND pce.target_code='SECRECY';

DELETE poe
FROM plot_option_effects poe
JOIN plot_decision_template_options pdto ON pdto.template_option_id=poe.template_option_id
JOIN plot_decision_templates pdt ON pdt.template_id=pdto.template_id
WHERE pdt.plot_id=@plot AND poe.effect_scope='FACTOR' AND poe.target_code='SECRECY';

-- Mark existing relevant opportunities by purpose for design clarity.
UPDATE plot_dynamic_opportunities SET opportunity_kind='RECONSIDERATION'
WHERE plot_id=@plot AND reconsideration_key IS NOT NULL;
UPDATE plot_dynamic_opportunities SET opportunity_kind='REACTION'
WHERE plot_id=@plot AND opportunity_code IN ('ELIAN_SECOND_CHANCE','MARA_ORDER_GAP','TOMAS_ROWAN_CUSTODY','TOMAS_HAND_CUSTODY_CONFLICT');

-- ---------------------------------------------------------------------------
-- ROWAN GOAL PURSUIT 1: exploit a vacuum created by continued player silence.
-- This can happen whether or not Rowan physically controls the letter.
-- ---------------------------------------------------------------------------
INSERT INTO plot_dynamic_opportunities(plot_id,opportunity_code,role_code,prompt_text,window_seconds,once_only,priority,active,reconsideration_key,opportunity_kind)
VALUES(@plot,'ROWAN_EXPLOIT_SILENCE','ROWAN','The rival claimant and senior officials have allowed the succession to drift. Your goal is still the crown. Do you turn their silence into political momentum, seek a controlled compromise, or wait for a stronger opening?',25,1,40,1,NULL,'GOAL_PURSUIT')
ON DUPLICATE KEY UPDATE prompt_text=VALUES(prompt_text),window_seconds=VALUES(window_seconds),priority=VALUES(priority),active=1,opportunity_kind='GOAL_PURSUIT';
SET @d=(SELECT dynamic_opportunity_id FROM plot_dynamic_opportunities WHERE plot_id=@plot AND opportunity_code='ROWAN_EXPLOIT_SILENCE');
DELETE FROM plot_dynamic_opportunity_conditions WHERE dynamic_opportunity_id=@d;
INSERT INTO plot_dynamic_opportunity_conditions(dynamic_opportunity_id,source_type,source_key,operator,compare_value,sort_order) VALUES
(@d,'PHASE',NULL,'EQ','RISING_ACTION',1),
(@d,'WORLD_STATE','CROWN_HOLDER','EQ','NONE',2),
(@d,'WORLD_STATE','ELIAN_CLAIM','IN','SECONDARY,ACTIVE',3),
(@d,'ACTION_COUNT',NULL,'GTE','1',4),
(@d,'STAT','AMBITION','GTE','70',5);
DELETE FROM plot_dynamic_opportunity_options WHERE dynamic_opportunity_id=@d;
INSERT INTO plot_dynamic_opportunity_options(dynamic_opportunity_id,option_code,label,result_text,npc_weight,rationale_text,sort_order) VALUES
(@d,'BUILD_MOMENTUM','Use the silence to rally councillors around immediate recognition.','Rowan treats the absence of effective opposition as permission to build political momentum.',55,'High ambition favors converting an uncontested opening into visible support.',1),
(@d,'OFFER_CONTROLLED_COMPROMISE','Offer a formal review only if the council recognizes you as provisional heir.','Rowan tries to control the process without abandoning his claim.',25,'A controlled compromise can preserve legitimacy while reducing immediate resistance.',2),
(@d,'WAIT_FOR_OPENING','Do not overreach yet; keep your supporters ready and wait.','Rowan preserves his position without creating a new confrontation.',20,'Pride can favor appearing confident enough not to chase every opening.',3);
SET @o=(SELECT dynamic_option_id FROM plot_dynamic_opportunity_options WHERE dynamic_opportunity_id=@d AND option_code='BUILD_MOMENTUM');
INSERT INTO plot_dynamic_option_effects(dynamic_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order) VALUES
(@o,'FACTOR','LEGITIMACY',6,NULL,'Visible support makes Rowan’s succession look increasingly like political fact.',1),
(@o,'FACTOR','UNREST',3,NULL,'Opponents become more alarmed as Rowan converts silence into momentum.',2);
SET @o=(SELECT dynamic_option_id FROM plot_dynamic_opportunity_options WHERE dynamic_opportunity_id=@d AND option_code='OFFER_CONTROLLED_COMPROMISE');
INSERT INTO plot_dynamic_option_effects(dynamic_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order) VALUES
(@o,'FACTOR','LEGITIMACY',3,NULL,'Provisional recognition gives Rowan some political standing.',1),
(@o,'FACTOR','COURT_TRUST',2,NULL,'A review process gives the court a limited procedural path forward.',2);

-- ---------------------------------------------------------------------------
-- ROWAN GOAL PURSUIT 2: if he controls the letter, he must decide how to use that advantage.
-- ---------------------------------------------------------------------------
INSERT INTO plot_dynamic_opportunities(plot_id,opportunity_code,role_code,prompt_text,window_seconds,once_only,priority,active,reconsideration_key,opportunity_kind)
VALUES(@plot,'ROWAN_USE_LETTER_CONTROL','ROWAN','You control the document that could undermine your claim. Your goal is still coronation. Do you keep it contained, submit it to a controlled legal challenge, or destroy the source of the dispute?',25,1,22,1,NULL,'GOAL_PURSUIT')
ON DUPLICATE KEY UPDATE prompt_text=VALUES(prompt_text),window_seconds=VALUES(window_seconds),priority=VALUES(priority),active=1,opportunity_kind='GOAL_PURSUIT';
SET @d=(SELECT dynamic_opportunity_id FROM plot_dynamic_opportunities WHERE plot_id=@plot AND opportunity_code='ROWAN_USE_LETTER_CONTROL');
DELETE FROM plot_dynamic_opportunity_conditions WHERE dynamic_opportunity_id=@d;
INSERT INTO plot_dynamic_opportunity_conditions(dynamic_opportunity_id,source_type,source_key,operator,compare_value,sort_order) VALUES
(@d,'WORLD_STATE','LETTER_HOLDER','EQ','ROWAN',1),
(@d,'WORLD_STATE','LETTER_STATUS','IN','SEALED,FOUND',2),
(@d,'WORLD_STATE','CROWN_HOLDER','EQ','NONE',3),
(@d,'PHASE',NULL,'IN','INCITING_INCIDENT,RISING_ACTION',4),
(@d,'ACTION_COUNT',NULL,'GTE','1',5);
DELETE FROM plot_dynamic_opportunity_options WHERE dynamic_opportunity_id=@d;
INSERT INTO plot_dynamic_opportunity_options(dynamic_opportunity_id,option_code,label,result_text,npc_weight,rationale_text,sort_order) VALUES
(@d,'KEEP_CONTAINED','Keep the letter secured and deny opponents access to it.','Rowan keeps control of the evidence and forces opponents to challenge him without the original document.',50,'Control preserves leverage without the irreversible risk of destroying evidence.',1),
(@d,'CONTROLLED_REVIEW','Allow a narrow legal review while publicly insisting the abdication was withdrawn.','Rowan tries to transform a hidden vulnerability into a dispute he can manage.',30,'A controlled process may preserve legitimacy better than obvious suppression.',2),
(@d,'DESTROY_LETTER','Destroy the original before it can become authoritative.','Rowan removes the physical document but creates a dangerous appearance of concealment.',20,'High ambition may justify an irreversible move when the document threatens the crown directly.',3);
SET @o=(SELECT dynamic_option_id FROM plot_dynamic_opportunity_options WHERE dynamic_opportunity_id=@d AND option_code='KEEP_CONTAINED');
INSERT INTO plot_dynamic_option_effects(dynamic_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order) VALUES
(@o,'FACTOR','LEGITIMACY',3,NULL,'Control of the succession process gives Rowan a short-term political advantage.',1),
(@o,'FACTOR','COURT_TRUST',-4,NULL,'Continued private control of decisive evidence deepens institutional suspicion.',2);
SET @o=(SELECT dynamic_option_id FROM plot_dynamic_opportunity_options WHERE dynamic_opportunity_id=@d AND option_code='CONTROLLED_REVIEW');
INSERT INTO plot_dynamic_option_effects(dynamic_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order) VALUES
(@o,'WORLD_STATE','LETTER_STATUS',NULL,'VERIFIED','The letter enters a formal review and its authenticity can no longer be dismissed as rumor.',1),
(@o,'FACTOR','COURT_TRUST',3,NULL,'Allowing review restores some confidence in process.',2);
SET @o=(SELECT dynamic_option_id FROM plot_dynamic_opportunity_options WHERE dynamic_opportunity_id=@d AND option_code='DESTROY_LETTER');
INSERT INTO plot_dynamic_option_effects(dynamic_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order) VALUES
(@o,'WORLD_STATE','LETTER_STATUS',NULL,'DESTROYED','The original abdication letter is destroyed.',1),
(@o,'FACTOR','COURT_TRUST',-10,NULL,'The disappearance of decisive succession evidence creates severe institutional suspicion.',2),
(@o,'FACTOR','UNREST',6,NULL,'Rumors of destroyed succession evidence inflame the capital.',3),
(@o,'FEAR','SELF',NULL,'Being exposed as the person who destroyed the succession evidence.','Rowan removes one threat but creates a new personal vulnerability.',4);

-- ---------------------------------------------------------------------------
-- ROWAN GOAL PURSUIT 3: the climax must give the active NPC a real final move.
-- ---------------------------------------------------------------------------
INSERT INTO plot_dynamic_opportunities(plot_id,opportunity_code,role_code,prompt_text,window_seconds,once_only,priority,active,reconsideration_key,opportunity_kind)
VALUES(@plot,'ROWAN_CLIMAX_MOVE','ROWAN','The coronation hour has arrived and no one yet holds the crown. Your goal remains unresolved. Do you attempt to take the crown now, accept a final council delay, or step back from the immediate contest?',25,1,5,1,NULL,'GOAL_PURSUIT')
ON DUPLICATE KEY UPDATE prompt_text=VALUES(prompt_text),window_seconds=VALUES(window_seconds),priority=VALUES(priority),active=1,opportunity_kind='GOAL_PURSUIT';
SET @d=(SELECT dynamic_opportunity_id FROM plot_dynamic_opportunities WHERE plot_id=@plot AND opportunity_code='ROWAN_CLIMAX_MOVE');
DELETE FROM plot_dynamic_opportunity_conditions WHERE dynamic_opportunity_id=@d;
INSERT INTO plot_dynamic_opportunity_conditions(dynamic_opportunity_id,source_type,source_key,operator,compare_value,sort_order) VALUES
(@d,'PHASE',NULL,'EQ','CLIMAX',1),
(@d,'WORLD_STATE','CROWN_HOLDER','EQ','NONE',2),
(@d,'WORLD_STATE','ROWAN_CLAIM','IN','PRESUMED_HEIR,DISPUTED',3),
(@d,'FACTOR','LEGITIMACY','GTE','55',4),
(@d,'STAT','AMBITION','GTE','70',5);
DELETE FROM plot_dynamic_opportunity_options WHERE dynamic_opportunity_id=@d;
INSERT INTO plot_dynamic_opportunity_options(dynamic_opportunity_id,option_code,label,result_text,npc_weight,rationale_text,sort_order) VALUES
(@d,'TAKE_CROWN','Attempt coronation while your political position is still viable.','Rowan converts his accumulated position into a direct attempt to take the crown.',55,'An ambitious claimant with sufficient legitimacy is likely to act before the opportunity closes.',1),
(@d,'ACCEPT_FINAL_DELAY','Accept a final council delay in exchange for continued recognition as presumed heir.','Rowan chooses position-preservation over an immediate coronation fight.',30,'A delay can protect the claim when forcing the crown would create excessive resistance.',2),
(@d,'STEP_BACK','Step back from the immediate coronation contest.','Rowan declines to force the crown at the climax.',15,'Even an ambitious claimant may retreat when the cost of forcing the issue becomes too high.',3);
SET @o=(SELECT dynamic_option_id FROM plot_dynamic_opportunity_options WHERE dynamic_opportunity_id=@d AND option_code='TAKE_CROWN');
INSERT INTO plot_dynamic_option_effects(dynamic_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order) VALUES
(@o,'WORLD_STATE','CROWN_HOLDER',NULL,'ROWAN','Rowan takes the crown while his claim retains enough political support to make the attempt viable.',1),
(@o,'WORLD_STATE','ROWAN_CLAIM',NULL,'CROWNED','Rowan becomes the crowned claimant.',2),
(@o,'FACTOR','LEGITIMACY',8,NULL,'Coronation creates immediate political fact.',3),
(@o,'FACTOR','UNREST',5,NULL,'Opponents react to Rowan forcing the succession to a conclusion.',4);
SET @o=(SELECT dynamic_option_id FROM plot_dynamic_opportunity_options WHERE dynamic_opportunity_id=@d AND option_code='ACCEPT_FINAL_DELAY');
INSERT INTO plot_dynamic_option_effects(dynamic_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order) VALUES
(@o,'FACTOR','STABILITY',4,NULL,'A formal delay lowers the immediate risk of confrontation.',1),
(@o,'FACTOR','COURT_TRUST',3,NULL,'The court gains time to resolve the disputed claim through process.',2),
(@o,'GOAL','SELF',NULL,'Preserve your position as presumed heir until the council resolves the succession dispute.','Rowan postpones coronation without abandoning his claim.',3);
SET @o=(SELECT dynamic_option_id FROM plot_dynamic_opportunity_options WHERE dynamic_opportunity_id=@d AND option_code='STEP_BACK');
INSERT INTO plot_dynamic_option_effects(dynamic_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order) VALUES
(@o,'FACTOR','STABILITY',5,NULL,'Stepping back reduces immediate confrontation.',1),
(@o,'FACTOR','LEGITIMACY',-5,NULL,'Failing to press the claim at the decisive moment weakens Rowan’s position.',2);

INSERT IGNORE INTO schema_migrations(migration) VALUES('028_fix_factor_budget_and_npc_goal_pursuit.sql');
