-- Strengthen player agency by connecting existing player choices to decisive story states.
-- No new engine subsystem: ordinary option effects + consequence rules do the work.
SET @plot=(SELECT plot_id FROM plots WHERE code='EMPTY_THRONE' LIMIT 1);

-- Elian: political support is now an actual claim action, not merely a factor bump.
SET @t=(SELECT template_id FROM plot_decision_templates WHERE plot_id=@plot AND event_code='SEALED_ARCHIVE' AND role_code='ELIAN');
SET @o=(SELECT template_option_id FROM plot_decision_template_options WHERE template_id=@t AND option_code='BUILD_SUPPORT');
INSERT INTO plot_option_effects(template_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order)
SELECT @o,'WORLD_STATE','ELIAN_CLAIM',NULL,'ACTIVE','Testing support openly enough to build a coalition makes Elian an active alternative claimant.',2
WHERE @o IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_option_effects WHERE template_option_id=@o AND effect_scope='WORLD_STATE' AND target_code='ELIAN_CLAIM');

-- Mara: demanding delay creates a formal review and blocks an immediate coronation during this crisis.
SET @t=(SELECT template_id FROM plot_decision_templates WHERE plot_id=@plot AND event_code='SEALED_ARCHIVE' AND role_code='MARA');
SET @o=(SELECT template_option_id FROM plot_decision_template_options WHERE template_id=@t AND option_code='DELAY_PROCLAMATION');
INSERT INTO plot_option_effects(template_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order)
SELECT @o,'WORLD_STATE','REVIEW_ORDERED',NULL,'YES','The council is formally required to review the disputed succession evidence before proclamation.',3
WHERE @o IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_option_effects WHERE template_option_id=@o AND effect_scope='WORLD_STATE' AND target_code='REVIEW_ORDERED');
INSERT INTO plot_option_effects(template_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order)
SELECT @o,'WORLD_STATE','CORONATION_BLOCKED',NULL,'YES','The formal delay prevents either claimant from completing an immediate coronation while review remains unresolved.',4
WHERE @o IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_option_effects WHERE template_option_id=@o AND effect_scope='WORLD_STATE' AND target_code='CORONATION_BLOCKED');

-- Asking the Queen makes corroborating testimony available to a later institutional review.
SET @o=(SELECT template_option_id FROM plot_decision_template_options WHERE template_id=@t AND option_code='ASK_QUEEN');
INSERT INTO plot_option_effects(template_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order)
SELECT @o,'WORLD_STATE','QUEEN_TESTIMONY_AVAILABLE',NULL,'YES','Mara now has a firsthand witness who can corroborate Rowan’s signed abdication in a formal review.',2
WHERE @o IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_option_effects WHERE template_option_id=@o AND effect_scope='WORLD_STATE' AND target_code='QUEEN_TESTIMONY_AVAILABLE');
INSERT INTO plot_option_effects(template_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order)
SELECT @o,'WORLD_STATE','LETTER_STATUS',NULL,'FOUND','The Queen’s firsthand account establishes that the abdication letter is a real succession document rather than a mere rumor.',3
WHERE @o IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_option_effects WHERE template_option_id=@o AND effect_scope='WORLD_STATE' AND target_code='LETTER_STATUS');

-- Tomas: neutral custody establishes that a recoverable succession document is physically secured.
SET @t=(SELECT template_id FROM plot_decision_templates WHERE plot_id=@plot AND event_code='SEALED_ARCHIVE' AND role_code='TOMAS');
SET @o=(SELECT template_option_id FROM plot_decision_template_options WHERE template_id=@t AND option_code='LOCK_ARCHIVE');
INSERT INTO plot_option_effects(template_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order)
SELECT @o,'WORLD_STATE','LETTER_STATUS',NULL,'FOUND','Neutral guard custody confirms that the disputed succession document has been recovered and secured.',3
WHERE @o IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_option_effects WHERE template_option_id=@o AND effect_scope='WORLD_STATE' AND target_code='LETTER_STATUS');

-- Visible guard support for Rowan is now a decisive institutional fact rather than only a numeric legitimacy bonus.
SET @o=(SELECT template_option_id FROM plot_decision_template_options WHERE template_id=@t AND option_code='GUARD_ROWAN');
INSERT INTO plot_option_effects(template_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order)
SELECT @o,'WORLD_STATE','ROWAN_SECURITY_BACKING',NULL,'YES','The palace guard visibly recognizes Rowan’s presumed authority.',3
WHERE @o IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_option_effects WHERE template_option_id=@o AND effect_scope='WORLD_STATE' AND target_code='ROWAN_SECURITY_BACKING');

-- A neutral guard stance prevents a claimant from treating palace force as automatic coronation enforcement.
SET @o=(SELECT template_option_id FROM plot_decision_template_options WHERE template_id=@t AND option_code='GUARD_COUNCIL');
INSERT INTO plot_option_effects(template_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order)
SELECT @o,'WORLD_STATE','GUARD_NEUTRAL',NULL,'YES','The palace guard will protect the council but will not enforce an unresolved claimant’s coronation.',2
WHERE @o IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_option_effects WHERE template_option_id=@o AND effect_scope='WORLD_STATE' AND target_code='GUARD_NEUTRAL');

-- Coordinated player action can now convert custody + lawful review into verified evidence.
INSERT INTO plot_consequence_rules(plot_id,rule_code,label,once_only,priority,active)
VALUES(@plot,'PLAYER_REVIEW_VERIFIES_ABDICATION','Neutral review verifies the abdication',1,8,1)
ON DUPLICATE KEY UPDATE label=VALUES(label),priority=VALUES(priority),active=1;
SET @r=(SELECT consequence_rule_id FROM plot_consequence_rules WHERE plot_id=@plot AND rule_code='PLAYER_REVIEW_VERIFIES_ABDICATION');
DELETE FROM plot_consequence_conditions WHERE consequence_rule_id=@r;
INSERT INTO plot_consequence_conditions(consequence_rule_id,state_key,operator,compare_value,sort_order) VALUES
(@r,'REVIEW_ORDERED','EQ','YES',1),
(@r,'LETTER_HOLDER','IN','TOMAS,HAND',2),
(@r,'LETTER_STATUS','IN','SEALED,FOUND',3);
DELETE FROM plot_consequence_effects WHERE consequence_rule_id=@r;
INSERT INTO plot_consequence_effects(consequence_rule_id,effect_scope,target_role_code,target_code,delta_value,set_value,detail_text,sort_order) VALUES
(@r,'WORLD_STATE',NULL,'LETTER_STATUS',NULL,'VERIFIED','Institutional review of the secured original verifies Rowan’s signed abdication.',1),
(@r,'FACTOR',NULL,'COURT_TRUST',6,NULL,'A shared evidentiary process gives the court a fact it can act on.',2);

-- Queen testimony can independently complete a review once the document is recovered.
INSERT INTO plot_consequence_rules(plot_id,rule_code,label,once_only,priority,active)
VALUES(@plot,'QUEEN_CORROBORATES_REVIEW','Queen testimony corroborates the recovered letter',1,9,1)
ON DUPLICATE KEY UPDATE label=VALUES(label),priority=VALUES(priority),active=1;
SET @r=(SELECT consequence_rule_id FROM plot_consequence_rules WHERE plot_id=@plot AND rule_code='QUEEN_CORROBORATES_REVIEW');
DELETE FROM plot_consequence_conditions WHERE consequence_rule_id=@r;
INSERT INTO plot_consequence_conditions(consequence_rule_id,state_key,operator,compare_value,sort_order) VALUES
(@r,'QUEEN_TESTIMONY_AVAILABLE','EQ','YES',1),
(@r,'LETTER_STATUS','EQ','FOUND',2),
(@r,'REVIEW_ORDERED','EQ','YES',3);
DELETE FROM plot_consequence_effects WHERE consequence_rule_id=@r;
INSERT INTO plot_consequence_effects(consequence_rule_id,effect_scope,target_role_code,target_code,delta_value,set_value,detail_text,sort_order) VALUES
(@r,'WORLD_STATE',NULL,'LETTER_STATUS',NULL,'VERIFIED','The recovered document and the Queen’s firsthand testimony corroborate one another.',1),
(@r,'FACTOR',NULL,'LEGITIMACY',-5,NULL,'The verified abdication makes an uncontested Rowan coronation harder to justify.',2);

-- Guard neutrality plus a formal review is also an enforceable coronation block.
INSERT INTO plot_consequence_rules(plot_id,rule_code,label,once_only,priority,active)
VALUES(@plot,'NEUTRAL_GUARD_ENFORCES_REVIEW_PAUSE','Neutral guard enforces the review pause',1,12,1)
ON DUPLICATE KEY UPDATE label=VALUES(label),priority=VALUES(priority),active=1;
SET @r=(SELECT consequence_rule_id FROM plot_consequence_rules WHERE plot_id=@plot AND rule_code='NEUTRAL_GUARD_ENFORCES_REVIEW_PAUSE');
DELETE FROM plot_consequence_conditions WHERE consequence_rule_id=@r;
INSERT INTO plot_consequence_conditions(consequence_rule_id,state_key,operator,compare_value,sort_order) VALUES
(@r,'GUARD_NEUTRAL','EQ','YES',1),
(@r,'REVIEW_ORDERED','EQ','YES',2),
(@r,'CROWN_HOLDER','EQ','NONE',3);
DELETE FROM plot_consequence_effects WHERE consequence_rule_id=@r;
INSERT INTO plot_consequence_effects(consequence_rule_id,effect_scope,target_role_code,target_code,delta_value,set_value,detail_text,sort_order) VALUES
(@r,'WORLD_STATE',NULL,'CORONATION_BLOCKED',NULL,'YES','The council’s review order now has neutral security enforcement behind it.',1),
(@r,'FACTOR',NULL,'STABILITY',4,NULL,'The guard’s refusal to enforce either claimant reduces the risk of a forced coronation.',2);

-- Rowan’s automatic/current-state coronation cannot override a player-created formal block.
SET @r=(SELECT consequence_rule_id FROM plot_consequence_rules WHERE plot_id=@plot AND rule_code='ROWAN_CURRENT_STATE_CORONATION');
INSERT INTO plot_consequence_conditions(consequence_rule_id,state_key,operator,compare_value,sort_order)
SELECT @r,'CORONATION_BLOCKED','NEQ','YES',7
WHERE @r IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_consequence_conditions WHERE consequence_rule_id=@r AND state_key='CORONATION_BLOCKED');

-- Rowan’s active climax attempt is subject to the same current-state block.
SET @d=(SELECT dynamic_opportunity_id FROM plot_dynamic_opportunities WHERE plot_id=@plot AND opportunity_code='ROWAN_CLIMAX_MOVE');
INSERT INTO plot_dynamic_opportunity_conditions(dynamic_opportunity_id,source_type,source_key,operator,compare_value,sort_order)
SELECT @d,'WORLD_STATE','CORONATION_BLOCKED','NEQ','YES',6
WHERE @d IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_dynamic_opportunity_conditions WHERE dynamic_opportunity_id=@d AND source_key='CORONATION_BLOCKED');

-- If Rowan has visible guard backing and no formal block, that support reinforces the existing path instead of merely changing a factor.
INSERT INTO plot_consequence_rules(plot_id,rule_code,label,once_only,priority,active)
VALUES(@plot,'ROWAN_GUARD_BACKING_STRENGTHENS_POSITION','Guard backing strengthens Rowan’s position',1,80,1)
ON DUPLICATE KEY UPDATE label=VALUES(label),priority=VALUES(priority),active=1;
SET @r=(SELECT consequence_rule_id FROM plot_consequence_rules WHERE plot_id=@plot AND rule_code='ROWAN_GUARD_BACKING_STRENGTHENS_POSITION');
DELETE FROM plot_consequence_conditions WHERE consequence_rule_id=@r;
INSERT INTO plot_consequence_conditions(consequence_rule_id,state_key,operator,compare_value,sort_order) VALUES
(@r,'ROWAN_SECURITY_BACKING','EQ','YES',1),
(@r,'ROWAN_CLAIM','EQ','PRESUMED_HEIR',2),
(@r,'CORONATION_BLOCKED','NEQ','YES',3),
(@r,'LETTER_STATUS','NOT_IN','VERIFIED,PUBLIC',4);
DELETE FROM plot_consequence_effects WHERE consequence_rule_id=@r;
INSERT INTO plot_consequence_effects(consequence_rule_id,effect_scope,target_role_code,target_code,delta_value,set_value,detail_text,sort_order) VALUES
(@r,'FACTOR',NULL,'LEGITIMACY',6,NULL,'Visible security recognition makes Rowan’s presumed authority harder to treat as merely ceremonial.',1),
(@r,'FACTOR',NULL,'STABILITY',3,NULL,'A unified security chain produces short-term continuity.',2);

INSERT IGNORE INTO schema_migrations(migration) VALUES('030_strengthen_player_causal_agency.sql');
