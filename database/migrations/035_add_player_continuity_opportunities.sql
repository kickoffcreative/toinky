-- Empty Throne multi-character continuity.
-- These are story-defined opportunities consumed by the generic dynamic-opportunity engine.

SET @plot=(SELECT plot_id FROM plots WHERE code='EMPTY_THRONE' LIMIT 1);

-- Elian reacts to the loss of the succession evidence.
INSERT INTO plot_dynamic_opportunities
(plot_id,opportunity_code,role_code,prompt_text,window_seconds,once_only,priority,active,reconsideration_key,opportunity_kind)
VALUES
(@plot,'ELIAN_RESPOND_TO_DESTROYED_EVIDENCE','ELIAN',
 'The succession letter has disappeared after Rowan took control of it, and the court now suspects destruction. Your goal is a legitimate path to the crown without civil war. Do you accuse Rowan publicly, build a legal coalition, or restrain your claim until stronger proof appears?',
 25,1,24,1,NULL,'REACTION')
ON DUPLICATE KEY UPDATE prompt_text=VALUES(prompt_text),window_seconds=VALUES(window_seconds),
priority=VALUES(priority),active=1,opportunity_kind='REACTION';

SET @d=(SELECT dynamic_opportunity_id FROM plot_dynamic_opportunities
  WHERE plot_id=@plot AND opportunity_code='ELIAN_RESPOND_TO_DESTROYED_EVIDENCE' LIMIT 1);
DELETE FROM plot_dynamic_opportunity_conditions WHERE dynamic_opportunity_id=@d;
INSERT INTO plot_dynamic_opportunity_conditions
(dynamic_opportunity_id,source_type,source_key,operator,compare_value,sort_order) VALUES
(@d,'PHASE',NULL,'EQ','RISING_ACTION',1),
(@d,'WORLD_STATE','CROWN_HOLDER','EQ','NONE',2),
(@d,'WORLD_STATE','LETTER_STATUS','EQ','DESTROYED',3),
(@d,'ACTION_COUNT',NULL,'GTE','1',4);
DELETE FROM plot_dynamic_opportunity_options WHERE dynamic_opportunity_id=@d;
INSERT INTO plot_dynamic_opportunity_options
(dynamic_opportunity_id,option_code,label,result_text,npc_weight,rationale_text,sort_order) VALUES
(@d,'ACCUSE_ROWAN_PUBLICLY',
 'Publicly accuse Rowan of destroying the succession evidence.',
 'Elian turns the missing letter into a direct public challenge to Rowan’s legitimacy.',
 30,'A direct accusation advances Elian’s claim but risks the civil conflict he fears.',1),
(@d,'BUILD_LEGAL_COALITION',
 'Quietly assemble councillors and witnesses around a lawful investigation.',
 'Elian builds an institutional challenge without immediately forcing the capital into open conflict.',
 50,'A legal coalition advances legitimacy while respecting Elian’s restraint.',2),
(@d,'RESTRAIN_CLAIM',
 'Avoid escalating the claim until stronger proof of destruction appears.',
 'Elian preserves peace but allows Rowan’s position to remain largely uncontested.',
 20,'Restraint reduces immediate conflict at the cost of political momentum.',3);

SET @o=(SELECT dynamic_option_id FROM plot_dynamic_opportunity_options WHERE dynamic_opportunity_id=@d AND option_code='ACCUSE_ROWAN_PUBLICLY');
INSERT INTO plot_dynamic_option_effects
(dynamic_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order) VALUES
(@o,'FACTOR','LEGITIMACY',4,NULL,'A direct accusation makes the succession dispute impossible to ignore.',1),
(@o,'FACTOR','UNREST',6,NULL,'Open accusations sharpen factional conflict in the capital.',2);
SET @o=(SELECT dynamic_option_id FROM plot_dynamic_opportunity_options WHERE dynamic_opportunity_id=@d AND option_code='BUILD_LEGAL_COALITION');
INSERT INTO plot_dynamic_option_effects
(dynamic_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order) VALUES
(@o,'FACTOR','COURT_TRUST',5,NULL,'A lawful investigation gives councillors a credible institutional response.',1),
(@o,'FACTOR','LEGITIMACY',3,NULL,'Elian gains standing by pursuing evidence through process.',2);
SET @o=(SELECT dynamic_option_id FROM plot_dynamic_opportunity_options WHERE dynamic_opportunity_id=@d AND option_code='RESTRAIN_CLAIM');
INSERT INTO plot_dynamic_option_effects
(dynamic_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order) VALUES
(@o,'FACTOR','STABILITY',4,NULL,'Elian’s restraint lowers the immediate risk of factional confrontation.',1);

-- Mara reacts to visible escalation around the succession.
INSERT INTO plot_dynamic_opportunities
(plot_id,opportunity_code,role_code,prompt_text,window_seconds,once_only,priority,active,reconsideration_key,opportunity_kind)
VALUES
(@plot,'MARA_RESPOND_TO_SUCCESSION_ESCALATION','MARA',
 'The evidence dispute has intensified, trust is falling, and rival political blocs are forming. Your goal is to keep the succession peaceful and the capital supplied. Do you convene mediation, prioritize the capital’s essential routes, or demand an enforced political pause?',
 25,1,25,1,NULL,'REACTION')
ON DUPLICATE KEY UPDATE prompt_text=VALUES(prompt_text),window_seconds=VALUES(window_seconds),
priority=VALUES(priority),active=1,opportunity_kind='REACTION';

SET @d=(SELECT dynamic_opportunity_id FROM plot_dynamic_opportunities
  WHERE plot_id=@plot AND opportunity_code='MARA_RESPOND_TO_SUCCESSION_ESCALATION' LIMIT 1);
DELETE FROM plot_dynamic_opportunity_conditions WHERE dynamic_opportunity_id=@d;
INSERT INTO plot_dynamic_opportunity_conditions
(dynamic_opportunity_id,source_type,source_key,operator,compare_value,sort_order) VALUES
(@d,'PHASE',NULL,'EQ','RISING_ACTION',1),
(@d,'WORLD_STATE','CROWN_HOLDER','EQ','NONE',2),
(@d,'FACTOR','UNREST','GTE','35',3),
(@d,'ACTION_COUNT',NULL,'GTE','1',4);
DELETE FROM plot_dynamic_opportunity_options WHERE dynamic_opportunity_id=@d;
INSERT INTO plot_dynamic_opportunity_options
(dynamic_opportunity_id,option_code,label,result_text,npc_weight,rationale_text,sort_order) VALUES
(@d,'CONVENE_MEDIATION',
 'Convene Elian, Rowan’s representatives, and senior councillors for structured mediation.',
 'Mara creates a controlled forum for the rival claims before factional pressure becomes violence.',
 50,'Diplomacy and pragmatism favor converting escalation into a managed process.',1),
(@d,'SECURE_ESSENTIAL_ROUTES',
 'Step away from the court dispute long enough to secure food and supply routes.',
 'Mara protects the capital from the practical consequences of a prolonged succession struggle.',
 30,'Supply security serves Mara’s goal even if the political dispute remains unresolved.',2),
(@d,'DEMAND_ENFORCED_PAUSE',
 'Demand that the guard suspend all succession moves until the court restores order.',
 'Mara attempts to impose a temporary political freeze before rival factions mobilize further.',
 20,'A forced pause may stabilize the capital but risks deepening political resentment.',3);

SET @o=(SELECT dynamic_option_id FROM plot_dynamic_opportunity_options WHERE dynamic_opportunity_id=@d AND option_code='CONVENE_MEDIATION');
INSERT INTO plot_dynamic_option_effects
(dynamic_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order) VALUES
(@o,'FACTOR','COURT_TRUST',5,NULL,'Structured mediation restores confidence that the dispute can remain political.',1),
(@o,'FACTOR','UNREST',-3,NULL,'A credible forum reduces pressure for immediate factional action.',2);
SET @o=(SELECT dynamic_option_id FROM plot_dynamic_opportunity_options WHERE dynamic_opportunity_id=@d AND option_code='SECURE_ESSENTIAL_ROUTES');
INSERT INTO plot_dynamic_option_effects
(dynamic_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order) VALUES
(@o,'FACTOR','STABILITY',6,NULL,'Secured supplies make the capital more resilient during the succession crisis.',1);
SET @o=(SELECT dynamic_option_id FROM plot_dynamic_opportunity_options WHERE dynamic_opportunity_id=@d AND option_code='DEMAND_ENFORCED_PAUSE');
INSERT INTO plot_dynamic_option_effects
(dynamic_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order) VALUES
(@o,'FACTOR','STABILITY',4,NULL,'A temporary political pause reduces immediate operational risk.',1),
(@o,'FACTOR','UNREST',2,NULL,'Factions resent being prevented from advancing their claims.',2);

-- Tomas reacts to the apparent destruction of evidence while remaining responsible for lawful security.
INSERT INTO plot_dynamic_opportunities
(plot_id,opportunity_code,role_code,prompt_text,window_seconds,once_only,priority,active,reconsideration_key,opportunity_kind)
VALUES
(@plot,'TOMAS_RESPOND_TO_DESTROYED_EVIDENCE','TOMAS',
 'The letter disappeared after Rowan seized the archive, and the council now fears that decisive evidence was destroyed. Your goal is lawful security, and you fear enforcing an illegitimate succession. Do you investigate Rowan’s attendants, maintain institutional neutrality, or place Rowan under guarded restriction?',
 25,1,23,1,NULL,'REACTION')
ON DUPLICATE KEY UPDATE prompt_text=VALUES(prompt_text),window_seconds=VALUES(window_seconds),
priority=VALUES(priority),active=1,opportunity_kind='REACTION';

SET @d=(SELECT dynamic_opportunity_id FROM plot_dynamic_opportunities
  WHERE plot_id=@plot AND opportunity_code='TOMAS_RESPOND_TO_DESTROYED_EVIDENCE' LIMIT 1);
DELETE FROM plot_dynamic_opportunity_conditions WHERE dynamic_opportunity_id=@d;
INSERT INTO plot_dynamic_opportunity_conditions
(dynamic_opportunity_id,source_type,source_key,operator,compare_value,sort_order) VALUES
(@d,'PHASE',NULL,'EQ','RISING_ACTION',1),
(@d,'WORLD_STATE','CROWN_HOLDER','EQ','NONE',2),
(@d,'WORLD_STATE','LETTER_STATUS','EQ','DESTROYED',3),
(@d,'ACTION_COUNT',NULL,'GTE','1',4);
DELETE FROM plot_dynamic_opportunity_options WHERE dynamic_opportunity_id=@d;
INSERT INTO plot_dynamic_opportunity_options
(dynamic_opportunity_id,option_code,label,result_text,npc_weight,rationale_text,sort_order) VALUES
(@d,'INVESTIGATE_DESTRUCTION',
 'Secure Rowan’s attendants and investigate how the evidence disappeared.',
 'Tomas opens a disciplined investigation without declaring either claimant legitimate.',
 50,'Duty favors establishing facts before the guard enforces a disputed succession.',1),
(@d,'MAINTAIN_NEUTRAL_LINE',
 'Keep the guard protecting institutions while refusing to accuse Rowan without proof.',
 'Tomas preserves institutional neutrality as suspicion grows around the missing evidence.',
 30,'Caution favors neutrality when destruction is suspected but not directly witnessed.',2),
(@d,'RESTRICT_ROWAN',
 'Place Rowan under guarded restriction until the council completes an investigation.',
 'Tomas limits Rowan’s freedom to prevent further interference with the succession process.',
 20,'Strong intervention may protect lawful authority but risks turning suspicion into confrontation.',3);

SET @o=(SELECT dynamic_option_id FROM plot_dynamic_opportunity_options WHERE dynamic_opportunity_id=@d AND option_code='INVESTIGATE_DESTRUCTION');
INSERT INTO plot_dynamic_option_effects
(dynamic_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order) VALUES
(@o,'FACTOR','COURT_TRUST',5,NULL,'A disciplined investigation reassures the council that evidence destruction will be examined.',1),
(@o,'FACTOR','STABILITY',2,NULL,'A lawful inquiry channels suspicion into an institutional process.',2);
SET @o=(SELECT dynamic_option_id FROM plot_dynamic_opportunity_options WHERE dynamic_opportunity_id=@d AND option_code='MAINTAIN_NEUTRAL_LINE');
INSERT INTO plot_dynamic_option_effects
(dynamic_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order) VALUES
(@o,'FACTOR','STABILITY',3,NULL,'Continued guard neutrality prevents immediate armed alignment.',1);
SET @o=(SELECT dynamic_option_id FROM plot_dynamic_opportunity_options WHERE dynamic_opportunity_id=@d AND option_code='RESTRICT_ROWAN');
INSERT INTO plot_dynamic_option_effects
(dynamic_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order) VALUES
(@o,'FACTOR','COURT_TRUST',4,NULL,'Restricting interference demonstrates that the guard serves lawful process.',1),
(@o,'FACTOR','UNREST',4,NULL,'Rowan’s supporters react angrily to guarded restriction.',2);

INSERT IGNORE INTO schema_migrations(migration)
VALUES('035_add_player_continuity_opportunities.sql');
