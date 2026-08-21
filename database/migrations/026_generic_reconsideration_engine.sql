-- Generic belief/decision reconsideration.
-- A plot declares when a character's current commitment has become materially questionable.
-- The engine opens an ordinary dynamic opportunity; no story meaning lives in PHP.

ALTER TABLE plot_dynamic_opportunities
  ADD COLUMN IF NOT EXISTS reconsideration_key VARCHAR(190) NULL AFTER active;

-- A reconsideration key is optional. When present, it documents which commitment/state is being reconsidered.
-- Existing dynamic opportunity conditions/options/effects remain the execution mechanism.

SET @plot=(SELECT plot_id FROM plots WHERE code='EMPTY_THRONE' LIMIT 1);

-- Soren: credible contradiction to an earlier Rowan commitment.
INSERT INTO plot_dynamic_opportunities(plot_id,opportunity_code,role_code,prompt_text,window_seconds,once_only,priority,active,reconsideration_key)
VALUES(@plot,'HAND_RECONSIDER_ROWAN','HAND','New credible succession evidence conflicts with your earlier support for Rowan and the King’s instruction to delay a disputed coronation. Reconsider your position.',35,1,8,1,'HAND_LOYALTY')
ON DUPLICATE KEY UPDATE prompt_text=VALUES(prompt_text),window_seconds=VALUES(window_seconds),priority=VALUES(priority),active=1,reconsideration_key=VALUES(reconsideration_key);
SET @d=(SELECT dynamic_opportunity_id FROM plot_dynamic_opportunities WHERE plot_id=@plot AND opportunity_code='HAND_RECONSIDER_ROWAN');
DELETE FROM plot_dynamic_opportunity_conditions WHERE dynamic_opportunity_id=@d;
INSERT INTO plot_dynamic_opportunity_conditions(dynamic_opportunity_id,source_type,source_key,operator,compare_value,sort_order) VALUES
(@d,'WORLD_STATE','HAND_LOYALTY','EQ','ROWAN',1),
(@d,'WORLD_STATE','LETTER_STATUS','IN','VERIFIED,PUBLIC',2),
(@d,'WORLD_STATE','CROWN_HOLDER','EQ','NONE',3),
(@d,'STAT','LOYALTY','GTE','70',4);
DELETE FROM plot_dynamic_opportunity_options WHERE dynamic_opportunity_id=@d;
INSERT INTO plot_dynamic_opportunity_options(dynamic_opportunity_id,option_code,label,result_text,npc_weight,rationale_text,sort_order) VALUES
(@d,'RETURN_NEUTRAL','Withdraw government backing and return to institutional neutrality.','Soren treats the verified dispute as more important than his earlier political commitment.',55,'High institutional loyalty favors the King’s instruction to delay a disputed coronation.',1),
(@d,'BACK_ELIAN','Switch government support to Elian’s lawful alternative claim.','Soren concludes that Rowan’s verified abdication makes Elian the stronger constitutional path.',20,'Pragmatism can favor a viable alternative once Rowan’s claim is compromised.',2),
(@d,'HOLD_ROWAN','Keep backing Rowan despite the new evidence.','Soren decides continuity and political fact outweigh the disputed document.',25,'Pragmatism can still favor the incumbent coalition even when institutional evidence conflicts with it.',3);
SET @o=(SELECT dynamic_option_id FROM plot_dynamic_opportunity_options WHERE dynamic_opportunity_id=@d AND option_code='RETURN_NEUTRAL');
INSERT INTO plot_dynamic_option_effects(dynamic_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order) VALUES
(@o,'WORLD_STATE','HAND_LOYALTY',NULL,'NEUTRAL','Soren withdraws institutional backing from Rowan while the succession remains disputed.',1),
(@o,'FACTOR','COURT_TRUST',5,NULL,'The Hand visibly follows the late King’s instruction instead of protecting his earlier choice.',2);
SET @o=(SELECT dynamic_option_id FROM plot_dynamic_opportunity_options WHERE dynamic_opportunity_id=@d AND option_code='BACK_ELIAN');
INSERT INTO plot_dynamic_option_effects(dynamic_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order) VALUES
(@o,'WORLD_STATE','HAND_LOYALTY',NULL,'ELIAN','The Hand changes institutional support after reconsidering the verified succession evidence.',1),
(@o,'WORLD_STATE','ELIAN_CLAIM',NULL,'SUPPORTED','Government recognition strengthens Elian’s alternative claim.',2),
(@o,'FACTOR','LEGITIMACY',4,NULL,'A reasoned institutional switch gives the alternative claim greater credibility.',3);
SET @o=(SELECT dynamic_option_id FROM plot_dynamic_opportunity_options WHERE dynamic_opportunity_id=@d AND option_code='HOLD_ROWAN');
INSERT INTO plot_dynamic_option_effects(dynamic_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order) VALUES
(@o,'FACTOR','COURT_TRUST',-8,NULL,'Keeping Rowan’s backing despite verified contradictory evidence damages confidence in the Hand.',1),
(@o,'FACTOR','UNREST',5,NULL,'The apparent constitutional contradiction increases political anger.',2);

-- Tomas: a disputed or crowned succession can directly conflict with his fear and duty.
INSERT INTO plot_dynamic_opportunities(plot_id,opportunity_code,role_code,prompt_text,window_seconds,once_only,priority,active,reconsideration_key)
VALUES(@plot,'TOMAS_ILLEGITIMATE_ENFORCEMENT','TOMAS','The succession now risks turning the palace guard into the enforcement arm of a disputed crown. Reconsider what lawful duty requires.',35,1,12,1,'TOMAS_DUTY')
ON DUPLICATE KEY UPDATE prompt_text=VALUES(prompt_text),window_seconds=VALUES(window_seconds),priority=VALUES(priority),active=1,reconsideration_key=VALUES(reconsideration_key);
SET @d=(SELECT dynamic_opportunity_id FROM plot_dynamic_opportunities WHERE plot_id=@plot AND opportunity_code='TOMAS_ILLEGITIMATE_ENFORCEMENT');
DELETE FROM plot_dynamic_opportunity_conditions WHERE dynamic_opportunity_id=@d;
INSERT INTO plot_dynamic_opportunity_conditions(dynamic_opportunity_id,source_type,source_key,operator,compare_value,sort_order) VALUES
(@d,'WORLD_STATE','LETTER_STATUS','IN','VERIFIED,PUBLIC',1),
(@d,'WORLD_STATE','HAND_LOYALTY','EQ','ROWAN',2),
(@d,'STAT','DUTY','GTE','75',3);
DELETE FROM plot_dynamic_opportunity_options WHERE dynamic_opportunity_id=@d;
INSERT INTO plot_dynamic_opportunity_options(dynamic_opportunity_id,option_code,label,result_text,npc_weight,rationale_text,sort_order) VALUES
(@d,'DECLARE_NEUTRAL_GUARD','Declare that the guard will enforce security but not a disputed succession.','Tomas separates public safety from political enforcement.',1,NULL,1),
(@d,'SECURE_EVIDENCE','Place the disputed succession evidence under guard protection.','Tomas uses his office to protect the process rather than a claimant.',1,NULL,2),
(@d,'FOLLOW_HAND','Continue following the Hand’s commands unless the council formally removes him.','Tomas prioritizes the existing chain of command despite his doubts.',1,NULL,3);
SET @o=(SELECT dynamic_option_id FROM plot_dynamic_opportunity_options WHERE dynamic_opportunity_id=@d AND option_code='DECLARE_NEUTRAL_GUARD');
INSERT INTO plot_dynamic_option_effects(dynamic_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order) VALUES
(@o,'FACTOR','STABILITY',4,NULL,'A neutral guard reduces the immediate risk of armed factional enforcement.',1),
(@o,'FACTOR','LEGITIMACY',-3,NULL,'The guard’s refusal to enforce a disputed succession weakens claims of settled authority.',2),
(@o,'GOAL','SELF',NULL,'Keep the palace secure while refusing to enforce any disputed claimant.','Tomas clarifies his duty after the succession becomes materially disputed.',3);
SET @o=(SELECT dynamic_option_id FROM plot_dynamic_opportunity_options WHERE dynamic_opportunity_id=@d AND option_code='SECURE_EVIDENCE');
INSERT INTO plot_dynamic_option_effects(dynamic_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order) VALUES
(@o,'WORLD_STATE','LETTER_HOLDER',NULL,'TOMAS','The palace guard takes protective custody of the succession evidence.',1),
(@o,'FACTOR','COURT_TRUST',5,NULL,'Neutral security custody makes the evidence harder for one political faction to control.',2);
SET @o=(SELECT dynamic_option_id FROM plot_dynamic_opportunity_options WHERE dynamic_opportunity_id=@d AND option_code='FOLLOW_HAND');
INSERT INTO plot_dynamic_option_effects(dynamic_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order) VALUES
(@o,'FACTOR','COURT_TRUST',-4,NULL,'Guard obedience leaves the contradiction between evidence and political authority unresolved.',1);

-- Rowan: verified evidence threatens his active goal/fear and forces a new decision rather than leaving him frozen after the archive beat.
INSERT INTO plot_dynamic_opportunities(plot_id,opportunity_code,role_code,prompt_text,window_seconds,once_only,priority,active,reconsideration_key)
VALUES(@plot,'ROWAN_REACT_VERIFIED_ABDICATION','ROWAN','The abdication is now credibly verified. Your earlier path to an uncontested coronation is collapsing. Decide how to respond.',35,1,10,1,'ROWAN_CLAIM')
ON DUPLICATE KEY UPDATE prompt_text=VALUES(prompt_text),window_seconds=VALUES(window_seconds),priority=VALUES(priority),active=1,reconsideration_key=VALUES(reconsideration_key);
SET @d=(SELECT dynamic_opportunity_id FROM plot_dynamic_opportunities WHERE plot_id=@plot AND opportunity_code='ROWAN_REACT_VERIFIED_ABDICATION');
DELETE FROM plot_dynamic_opportunity_conditions WHERE dynamic_opportunity_id=@d;
INSERT INTO plot_dynamic_opportunity_conditions(dynamic_opportunity_id,source_type,source_key,operator,compare_value,sort_order) VALUES
(@d,'WORLD_STATE','LETTER_STATUS','IN','VERIFIED,PUBLIC',1),
(@d,'WORLD_STATE','ROWAN_CLAIM','EQ','DISPUTED',2),
(@d,'WORLD_STATE','CROWN_HOLDER','EQ','NONE',3),
(@d,'STAT','AMBITION','GTE','70',4);
DELETE FROM plot_dynamic_opportunity_options WHERE dynamic_opportunity_id=@d;
INSERT INTO plot_dynamic_opportunity_options(dynamic_opportunity_id,option_code,label,result_text,npc_weight,rationale_text,sort_order) VALUES
(@d,'CONTEST_AUTHENTICITY','Attack the letter’s legal force and demand a formal hearing.','Rowan stops treating the document as a secret problem and openly contests its authority.',45,'High ambition favors fighting through institutions rather than conceding immediately.',1),
(@d,'PRESS_POLITICAL_CLAIM','Argue that continuity and public recognition outweigh the old abdication.','Rowan doubles down on political legitimacy despite the verified document.',35,'Pride and ambition favor preserving the claim even after evidentiary damage.',2),
(@d,'RENOUNCE','Accept that the abdication controls and renounce the crown.','Rowan abandons the succession fight rather than risk wider conflict.',20,'A lower-probability retreat remains possible when the cost of contest becomes too high.',3);
SET @o=(SELECT dynamic_option_id FROM plot_dynamic_opportunity_options WHERE dynamic_opportunity_id=@d AND option_code='CONTEST_AUTHENTICITY');
INSERT INTO plot_dynamic_option_effects(dynamic_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order) VALUES
(@o,'FACTOR','COURT_TRUST',-3,NULL,'The succession moves into open legal conflict.',1),
(@o,'FACTOR','UNREST',3,NULL,'Public uncertainty grows as Rowan contests the verified document.',2),
(@o,'GOAL','SELF',NULL,'Defeat the legal force of the abdication and restore your claim.','Rowan’s goal changes because an uncontested coronation is no longer available.',3);
SET @o=(SELECT dynamic_option_id FROM plot_dynamic_opportunity_options WHERE dynamic_opportunity_id=@d AND option_code='PRESS_POLITICAL_CLAIM');
INSERT INTO plot_dynamic_option_effects(dynamic_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order) VALUES
(@o,'FACTOR','LEGITIMACY',3,NULL,'Rowan tries to convert existing recognition into political legitimacy.',1),
(@o,'FACTOR','UNREST',6,NULL,'Ignoring the verified constitutional dispute raises the stakes.',2),
(@o,'GOAL','SELF',NULL,'Win recognition despite the abdication by making your rule a political fact.','Rowan shifts from legal presumption to political consolidation.',3);
SET @o=(SELECT dynamic_option_id FROM plot_dynamic_opportunity_options WHERE dynamic_opportunity_id=@d AND option_code='RENOUNCE');
INSERT INTO plot_dynamic_option_effects(dynamic_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order) VALUES
(@o,'WORLD_STATE','ROWAN_CLAIM',NULL,'RENOUNCED','Rowan accepts that the abdication ends his claim.',1),
(@o,'FACTOR','STABILITY',8,NULL,'Removing one claimant sharply reduces the immediate succession conflict.',2),
(@o,'GOAL','SELF',NULL,'Preserve your position and family after renouncing the crown.','Rowan abandons the coronation goal after accepting the document’s authority.',3);

-- If Soren receives the contradiction and refuses to reconsider by doing nothing, his earlier commitment still has a cost.
INSERT INTO plot_no_action_effects(plot_id,event_code,role_code,effect_scope,target_code,delta_value,set_value,detail_text,sort_order)
SELECT @plot,'HAND_RECONSIDER_ROWAN','HAND','FACTOR','COURT_TRUST',-6,NULL,'The Hand receives credible contradictory evidence but leaves his earlier political commitment unexplained.',1
WHERE NOT EXISTS(SELECT 1 FROM plot_no_action_effects WHERE plot_id=@plot AND event_code='HAND_RECONSIDER_ROWAN' AND role_code='HAND' AND target_code='COURT_TRUST');
INSERT INTO plot_no_action_effects(plot_id,event_code,role_code,effect_scope,target_code,delta_value,set_value,detail_text,sort_order)
SELECT @plot,'HAND_RECONSIDER_ROWAN','HAND','FACTOR','UNREST',4,NULL,'Institutional silence in the face of a verified succession dispute increases public uncertainty.',2
WHERE NOT EXISTS(SELECT 1 FROM plot_no_action_effects WHERE plot_id=@plot AND event_code='HAND_RECONSIDER_ROWAN' AND role_code='HAND' AND target_code='UNREST');

INSERT IGNORE INTO schema_migrations(migration) VALUES('026_generic_reconsideration_engine.sql');
