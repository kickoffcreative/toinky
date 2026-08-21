-- Resolve temporary coronation blocks instead of letting them freeze the story.
-- A review can end in a verified Elian path, an inconclusive Rowan-reopened path,
-- or remain unresolved through the climax. No new PHP subsystem is required.
SET @plot=(SELECT plot_id FROM plots WHERE code='EMPTY_THRONE' LIMIT 1);

-- Record the review lifecycle when Mara creates the formal pause.
SET @t=(SELECT template_id FROM plot_decision_templates WHERE plot_id=@plot AND event_code='SEALED_ARCHIVE' AND role_code='MARA');
SET @o=(SELECT template_option_id FROM plot_decision_template_options WHERE template_id=@t AND option_code='DELAY_PROCLAMATION');
INSERT INTO plot_option_effects(template_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order)
SELECT @o,'WORLD_STATE','REVIEW_STATUS',NULL,'BLOCKED','A formal succession review is active and the immediate coronation path is suspended.',5
WHERE @o IS NOT NULL AND NOT EXISTS(
  SELECT 1 FROM plot_option_effects WHERE template_option_id=@o AND effect_scope='WORLD_STATE' AND target_code='REVIEW_STATUS'
);

-- VERIFIED/PUBLIC evidence resolves the pause toward Elian.
-- Soren is now a world/institutional entity rather than an active NPC, so institutional support
-- is expressed as a world consequence of the completed lawful review.
INSERT INTO plot_consequence_rules(plot_id,rule_code,label,once_only,priority,active)
VALUES(@plot,'VERIFIED_REVIEW_RESOLVES_FOR_ELIAN','Verified review resolves the succession toward Elian',1,14,1)
ON DUPLICATE KEY UPDATE label=VALUES(label),priority=VALUES(priority),active=1;
SET @r=(SELECT consequence_rule_id FROM plot_consequence_rules WHERE plot_id=@plot AND rule_code='VERIFIED_REVIEW_RESOLVES_FOR_ELIAN');
DELETE FROM plot_consequence_conditions WHERE consequence_rule_id=@r;
INSERT INTO plot_consequence_conditions(consequence_rule_id,state_key,operator,compare_value,sort_order) VALUES
(@r,'REVIEW_ORDERED','EQ','YES',1),
(@r,'REVIEW_STATUS','EQ','BLOCKED',2),
(@r,'LETTER_STATUS','IN','VERIFIED,PUBLIC',3),
(@r,'CROWN_HOLDER','EQ','NONE',4);
DELETE FROM plot_consequence_effects WHERE consequence_rule_id=@r;
INSERT INTO plot_consequence_effects(consequence_rule_id,effect_scope,target_role_code,target_code,delta_value,set_value,detail_text,sort_order) VALUES
(@r,'WORLD_STATE',NULL,'REVIEW_STATUS',NULL,'RESOLVED','The formal review reaches a constitutional conclusion.',1),
(@r,'WORLD_STATE',NULL,'REVIEW_RESOLUTION',NULL,'ELIAN_VALIDATED','Verified abdication evidence validates Elian as the viable lawful alternative.',2),
(@r,'WORLD_STATE',NULL,'CORONATION_BLOCKED',NULL,'NO','The general pause ends because the review has produced a lawful path forward.',3),
(@r,'WORLD_STATE',NULL,'ELIAN_CLAIM',NULL,'SUPPORTED','The completed review gives Elian institutional support rather than merely an active claim.',4),
(@r,'WORLD_STATE',NULL,'HAND_LOYALTY',NULL,'ELIAN','The machinery of government follows the completed review and recognizes Elian’s lawful path.',5),
(@r,'WORLD_STATE',NULL,'ROWAN_CLAIM',NULL,'DISPUTED','Verified abdication evidence prevents Rowan from returning to uncontested presumed-heir status.',6),
(@r,'FACTOR',NULL,'COURT_TRUST',5,NULL,'The court gains confidence because the temporary pause produced an actual constitutional conclusion.',7),
(@r,'FACTOR',NULL,'STABILITY',4,NULL,'A resolved review gives institutions a common lawful path.',8);

-- If the evidence is destroyed before review can establish its authority, the pause ends inconclusively.
-- This does not crown Rowan automatically; it merely reopens his political route if his claim otherwise remains viable.
INSERT INTO plot_consequence_rules(plot_id,rule_code,label,once_only,priority,active)
VALUES(@plot,'DESTROYED_EVIDENCE_RESOLVES_REVIEW_INCONCLUSIVELY','Destroyed evidence ends review without a constitutional finding',1,15,1)
ON DUPLICATE KEY UPDATE label=VALUES(label),priority=VALUES(priority),active=1;
SET @r=(SELECT consequence_rule_id FROM plot_consequence_rules WHERE plot_id=@plot AND rule_code='DESTROYED_EVIDENCE_RESOLVES_REVIEW_INCONCLUSIVELY');
DELETE FROM plot_consequence_conditions WHERE consequence_rule_id=@r;
INSERT INTO plot_consequence_conditions(consequence_rule_id,state_key,operator,compare_value,sort_order) VALUES
(@r,'REVIEW_ORDERED','EQ','YES',1),
(@r,'REVIEW_STATUS','EQ','BLOCKED',2),
(@r,'LETTER_STATUS','EQ','DESTROYED',3),
(@r,'CROWN_HOLDER','EQ','NONE',4);
DELETE FROM plot_consequence_effects WHERE consequence_rule_id=@r;
INSERT INTO plot_consequence_effects(consequence_rule_id,effect_scope,target_role_code,target_code,delta_value,set_value,detail_text,sort_order) VALUES
(@r,'WORLD_STATE',NULL,'REVIEW_STATUS',NULL,'RESOLVED','The formal review ends because the original evidence can no longer be examined.',1),
(@r,'WORLD_STATE',NULL,'REVIEW_RESOLUTION',NULL,'INCONCLUSIVE','The review cannot establish a final constitutional finding after the original is destroyed.',2),
(@r,'WORLD_STATE',NULL,'CORONATION_BLOCKED',NULL,'NO','The procedural pause expires once the review can no longer reach an evidentiary conclusion.',3),
(@r,'FACTOR',NULL,'COURT_TRUST',-7,NULL,'The destruction of evidence damages confidence in the succession process.',4),
(@r,'FACTOR',NULL,'UNREST',5,NULL,'An inconclusive review and missing original deepen public suspicion.',5);

-- A verified review that validates Elian must not reopen Rowan's direct climax route merely because
-- the generic pause itself has ended.
SET @r=(SELECT consequence_rule_id FROM plot_consequence_rules WHERE plot_id=@plot AND rule_code='ROWAN_CURRENT_STATE_CORONATION');
INSERT INTO plot_consequence_conditions(consequence_rule_id,state_key,operator,compare_value,sort_order)
SELECT @r,'REVIEW_RESOLUTION','NEQ','ELIAN_VALIDATED',8
WHERE @r IS NOT NULL AND NOT EXISTS(
  SELECT 1 FROM plot_consequence_conditions WHERE consequence_rule_id=@r AND state_key='REVIEW_RESOLUTION'
);

SET @d=(SELECT dynamic_opportunity_id FROM plot_dynamic_opportunities WHERE plot_id=@plot AND opportunity_code='ROWAN_CLIMAX_MOVE');
INSERT INTO plot_dynamic_opportunity_conditions(dynamic_opportunity_id,source_type,source_key,operator,compare_value,sort_order)
SELECT @d,'WORLD_STATE','REVIEW_RESOLUTION','NEQ','ELIAN_VALIDATED',7
WHERE @d IS NOT NULL AND NOT EXISTS(
  SELECT 1 FROM plot_dynamic_opportunity_conditions WHERE dynamic_opportunity_id=@d AND source_key='REVIEW_RESOLUTION'
);

-- Elian's coronation route should explicitly require the review not to be unresolved.
-- Existing requirements (SUPPORTED claim, verified evidence, institutional support, legitimacy) still apply.
SET @r=(SELECT consequence_rule_id FROM plot_consequence_rules WHERE plot_id=@plot AND rule_code='ELIAN_CURRENT_STATE_CORONATION');
INSERT INTO plot_consequence_conditions(consequence_rule_id,state_key,operator,compare_value,sort_order)
SELECT @r,'REVIEW_RESOLUTION','EQ','ELIAN_VALIDATED',7
WHERE @r IS NOT NULL AND NOT EXISTS(
  SELECT 1 FROM plot_consequence_conditions WHERE consequence_rule_id=@r AND state_key='REVIEW_RESOLUTION'
);

-- Resolution narration for the temporary review state.
INSERT INTO plot_resolution_fragments(plot_id,section_code,priority,fragment_text)
SELECT @plot,'REVIEW',10,'The formal succession review verifies the abdication and produces a lawful institutional path for Elian.'
WHERE NOT EXISTS(SELECT 1 FROM plot_resolution_fragments WHERE plot_id=@plot AND section_code='REVIEW' AND priority=10);
SET @f=(SELECT resolution_fragment_id FROM plot_resolution_fragments WHERE plot_id=@plot AND section_code='REVIEW' AND priority=10 LIMIT 1);
DELETE FROM plot_resolution_conditions WHERE resolution_fragment_id=@f;
INSERT INTO plot_resolution_conditions(resolution_fragment_id,state_key,operator,compare_value) VALUES(@f,'REVIEW_RESOLUTION','EQ','ELIAN_VALIDATED');

INSERT INTO plot_resolution_fragments(plot_id,section_code,priority,fragment_text)
SELECT @plot,'REVIEW',20,'The formal succession review ends inconclusively after the original evidence is destroyed, reopening the political contest without proving either claim.'
WHERE NOT EXISTS(SELECT 1 FROM plot_resolution_fragments WHERE plot_id=@plot AND section_code='REVIEW' AND priority=20);
SET @f=(SELECT resolution_fragment_id FROM plot_resolution_fragments WHERE plot_id=@plot AND section_code='REVIEW' AND priority=20 LIMIT 1);
DELETE FROM plot_resolution_conditions WHERE resolution_fragment_id=@f;
INSERT INTO plot_resolution_conditions(resolution_fragment_id,state_key,operator,compare_value) VALUES(@f,'REVIEW_RESOLUTION','EQ','INCONCLUSIVE');

INSERT INTO plot_resolution_fragments(plot_id,section_code,priority,fragment_text)
SELECT @plot,'REVIEW',100,'The succession review remains unresolved at the close of the immediate crisis, so no claimant can treat the temporary pause as a final constitutional judgment.'
WHERE NOT EXISTS(SELECT 1 FROM plot_resolution_fragments WHERE plot_id=@plot AND section_code='REVIEW' AND priority=100);
SET @f=(SELECT resolution_fragment_id FROM plot_resolution_fragments WHERE plot_id=@plot AND section_code='REVIEW' AND priority=100 LIMIT 1);
DELETE FROM plot_resolution_conditions WHERE resolution_fragment_id=@f;
INSERT INTO plot_resolution_conditions(resolution_fragment_id,state_key,operator,compare_value) VALUES(@f,'REVIEW_STATUS','EQ','BLOCKED');

INSERT IGNORE INTO schema_migrations(migration) VALUES('031_resolve_coronation_block_lifecycle.sql');
