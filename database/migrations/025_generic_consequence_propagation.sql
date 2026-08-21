-- Generic consequence propagation.
-- Plot data declares state dependencies. Engine repeatedly evaluates them until the causal graph settles.
CREATE TABLE IF NOT EXISTS plot_consequence_rules (
  consequence_rule_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  plot_id BIGINT UNSIGNED NOT NULL,
  rule_code VARCHAR(120) NOT NULL,
  label VARCHAR(255) NOT NULL,
  once_only TINYINT(1) NOT NULL DEFAULT 1,
  priority INT NOT NULL DEFAULT 100,
  active TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_consequence_rule(plot_id,rule_code),
  KEY idx_consequence_rules(plot_id,priority),
  CONSTRAINT fk_pcr_plot FOREIGN KEY(plot_id) REFERENCES plots(plot_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plot_consequence_conditions (
  consequence_condition_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  consequence_rule_id BIGINT UNSIGNED NOT NULL,
  state_key VARCHAR(190) NOT NULL,
  operator ENUM('EQ','NEQ','IN','NOT_IN','EXISTS','NOT_EXISTS','LT','LTE','GT','GTE') NOT NULL DEFAULT 'EQ',
  compare_value VARCHAR(500) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_pcc_rule FOREIGN KEY(consequence_rule_id) REFERENCES plot_consequence_rules(consequence_rule_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plot_consequence_effects (
  consequence_effect_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  consequence_rule_id BIGINT UNSIGNED NOT NULL,
  effect_scope ENUM('WORLD_STATE','FACTOR','LOCATION','STAT','GOAL','FEAR') NOT NULL,
  target_role_code VARCHAR(100) NULL,
  target_code VARCHAR(190) NOT NULL,
  delta_value INT NULL,
  set_value VARCHAR(500) NULL,
  detail_text TEXT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_pce_rule FOREIGN KEY(consequence_rule_id) REFERENCES plot_consequence_rules(consequence_rule_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @plot=(SELECT plot_id FROM plots WHERE code='EMPTY_THRONE' LIMIT 1);

-- The old climax shortcuts are disabled. Coronation is now evaluated by consequence rules against current state.
UPDATE plot_triggered_transitions SET active=0 WHERE plot_id=@plot AND transition_code IN ('ROWAN_CORONATION_MOMENTUM','ELIAN_CORONATION_MOMENTUM');

-- Verified abdication has constitutional consequences everywhere, not just on secrecy.
INSERT INTO plot_consequence_rules(plot_id,rule_code,label,once_only,priority,active)
VALUES(@plot,'VERIFIED_ABDICATION_CHANGES_CLAIMS','Verified abdication reshapes the succession',1,10,1)
ON DUPLICATE KEY UPDATE label=VALUES(label),priority=VALUES(priority),active=1;
SET @r=(SELECT consequence_rule_id FROM plot_consequence_rules WHERE plot_id=@plot AND rule_code='VERIFIED_ABDICATION_CHANGES_CLAIMS');
DELETE FROM plot_consequence_conditions WHERE consequence_rule_id=@r;
INSERT INTO plot_consequence_conditions(consequence_rule_id,state_key,operator,compare_value,sort_order) VALUES
(@r,'LETTER_STATUS','IN','VERIFIED,PUBLIC',1);
DELETE FROM plot_consequence_effects WHERE consequence_rule_id=@r;
INSERT INTO plot_consequence_effects(consequence_rule_id,effect_scope,target_role_code,target_code,delta_value,set_value,detail_text,sort_order) VALUES
(@r,'WORLD_STATE',NULL,'ROWAN_CLAIM',NULL,'DISPUTED','A verified abdication prevents Rowan from remaining an uncontested presumed heir.',1),
(@r,'WORLD_STATE',NULL,'ELIAN_CLAIM',NULL,'ACTIVE','A verified abdication creates a concrete lawful path for Elian.',2),
(@r,'FACTOR',NULL,'LEGITIMACY',-8,NULL,'The verified conflict weakens confidence in an immediate coronation.',3),
(@r,'FACTOR',NULL,'COURT_TRUST',-4,NULL,'The court realizes the succession process was not as settled as claimed.',4);

-- Rowan can only convert government support into a crown while his claim is still legally viable
-- and no verified/public abdication blocks that path.
INSERT INTO plot_consequence_rules(plot_id,rule_code,label,once_only,priority,active)
VALUES(@plot,'ROWAN_CURRENT_STATE_CORONATION','Current state permits Rowan coronation',1,200,1)
ON DUPLICATE KEY UPDATE label=VALUES(label),priority=VALUES(priority),active=1;
SET @r=(SELECT consequence_rule_id FROM plot_consequence_rules WHERE plot_id=@plot AND rule_code='ROWAN_CURRENT_STATE_CORONATION');
DELETE FROM plot_consequence_conditions WHERE consequence_rule_id=@r;
INSERT INTO plot_consequence_conditions(consequence_rule_id,state_key,operator,compare_value,sort_order) VALUES
(@r,'HAND_LOYALTY','EQ','ROWAN',1),
(@r,'ROWAN_CLAIM','EQ','PRESUMED_HEIR',2),
(@r,'LETTER_STATUS','NOT_IN','VERIFIED,PUBLIC',3),
(@r,'CROWN_HOLDER','EQ','NONE',4),
(@r,'STORY_PHASE','IN','CLIMAX,RESOLUTION',5),
(@r,'FACTOR:LEGITIMACY','GTE','50',6);
DELETE FROM plot_consequence_effects WHERE consequence_rule_id=@r;
INSERT INTO plot_consequence_effects(consequence_rule_id,effect_scope,target_role_code,target_code,delta_value,set_value,detail_text,sort_order) VALUES
(@r,'WORLD_STATE',NULL,'CROWN_HOLDER',NULL,'ROWAN','Government support, an intact claim and sufficient legitimacy carry Rowan to coronation.',1),
(@r,'WORLD_STATE',NULL,'ROWAN_CLAIM',NULL,'CROWNED','Rowan becomes the crowned claimant.',2),
(@r,'FACTOR',NULL,'LEGITIMACY',10,NULL,'Coronation creates political fact.',3),
(@r,'FACTOR',NULL,'UNREST',5,NULL,'Opponents react to the completed coronation.',4);

-- Elian's path likewise requires current institutional and evidentiary support.
INSERT INTO plot_consequence_rules(plot_id,rule_code,label,once_only,priority,active)
VALUES(@plot,'ELIAN_CURRENT_STATE_CORONATION','Current state permits Elian coronation',1,205,1)
ON DUPLICATE KEY UPDATE label=VALUES(label),priority=VALUES(priority),active=1;
SET @r=(SELECT consequence_rule_id FROM plot_consequence_rules WHERE plot_id=@plot AND rule_code='ELIAN_CURRENT_STATE_CORONATION');
DELETE FROM plot_consequence_conditions WHERE consequence_rule_id=@r;
INSERT INTO plot_consequence_conditions(consequence_rule_id,state_key,operator,compare_value,sort_order) VALUES
(@r,'HAND_LOYALTY','EQ','ELIAN',1),
(@r,'ELIAN_CLAIM','EQ','SUPPORTED',2),
(@r,'LETTER_STATUS','IN','VERIFIED,PUBLIC',3),
(@r,'CROWN_HOLDER','EQ','NONE',4),
(@r,'STORY_PHASE','IN','CLIMAX,RESOLUTION',5),
(@r,'FACTOR:LEGITIMACY','GTE','50',6);
DELETE FROM plot_consequence_effects WHERE consequence_rule_id=@r;
INSERT INTO plot_consequence_effects(consequence_rule_id,effect_scope,target_role_code,target_code,delta_value,set_value,detail_text,sort_order) VALUES
(@r,'WORLD_STATE',NULL,'CROWN_HOLDER',NULL,'ELIAN','Verified evidence and institutional support carry Elian to coronation.',1),
(@r,'WORLD_STATE',NULL,'ELIAN_CLAIM',NULL,'CROWNED','Elian becomes the crowned claimant.',2),
(@r,'FACTOR',NULL,'LEGITIMACY',12,NULL,'Evidence and institutional recognition strengthen the new crown.',3),
(@r,'FACTOR',NULL,'STABILITY',4,NULL,'A recognized constitutional path reduces immediate uncertainty.',4);

-- A Hand who backs Rowan while holding disputed evidence creates a new security contradiction for Tomas.
INSERT INTO plot_dynamic_opportunities(plot_id,opportunity_code,role_code,prompt_text,window_seconds,once_only,priority,active)
VALUES(@plot,'TOMAS_HAND_CUSTODY_CONFLICT','TOMAS','The King’s Hand is backing Rowan while controlling disputed succession evidence. Do you demand independent custody, secure the council chamber, or obey the Hand?',35,1,18,1)
ON DUPLICATE KEY UPDATE prompt_text=VALUES(prompt_text),window_seconds=VALUES(window_seconds),priority=VALUES(priority),active=1;
SET @d=(SELECT dynamic_opportunity_id FROM plot_dynamic_opportunities WHERE plot_id=@plot AND opportunity_code='TOMAS_HAND_CUSTODY_CONFLICT');
DELETE FROM plot_dynamic_opportunity_conditions WHERE dynamic_opportunity_id=@d;
INSERT INTO plot_dynamic_opportunity_conditions(dynamic_opportunity_id,source_type,source_key,operator,compare_value,sort_order) VALUES
(@d,'WORLD_STATE','LETTER_HOLDER','EQ','HAND',1),
(@d,'WORLD_STATE','HAND_LOYALTY','EQ','ROWAN',2),
(@d,'WORLD_STATE','CROWN_HOLDER','EQ','NONE',3),
(@d,'PHASE',NULL,'IN','INCITING_INCIDENT,RISING_ACTION',4);
DELETE FROM plot_dynamic_opportunity_options WHERE dynamic_opportunity_id=@d;
INSERT INTO plot_dynamic_opportunity_options(dynamic_opportunity_id,option_code,label,result_text,npc_weight,rationale_text,sort_order) VALUES
(@d,'DEMAND_INDEPENDENT_CUSTODY','Demand that the guard hold the document independently of the Hand.','Tomas challenges the conflict between institutional custody and political loyalty.',1,NULL,1),
(@d,'SECURE_COUNCIL','Deploy guards around the council while leaving the document with the Hand.','Tomas contains immediate disorder without deciding the evidence dispute.',1,NULL,2),
(@d,'OBEY_HAND','Accept the Hand’s authority and keep the guard out of the dispute.','Tomas chooses formal obedience despite doubts about the succession process.',1,NULL,3);
SET @o=(SELECT dynamic_option_id FROM plot_dynamic_opportunity_options WHERE dynamic_opportunity_id=@d AND option_code='DEMAND_INDEPENDENT_CUSTODY');
INSERT INTO plot_dynamic_option_effects(dynamic_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order) VALUES
(@o,'WORLD_STATE','LETTER_HOLDER',NULL,'TOMAS','The palace guard takes independent custody of the succession evidence.',1),
(@o,'FACTOR','COURT_TRUST',6,NULL,'Independent custody reduces the appearance that evidence is controlled by Rowan’s political ally.',2),
(@o,'GOAL','SELF',NULL,'Protect the succession evidence until a lawful authority is unquestionably established.','Tomas turns his fear of illegitimate enforcement into an active custodial duty.',3);
SET @o=(SELECT dynamic_option_id FROM plot_dynamic_opportunity_options WHERE dynamic_opportunity_id=@d AND option_code='SECURE_COUNCIL');
INSERT INTO plot_dynamic_option_effects(dynamic_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order) VALUES
(@o,'FACTOR','STABILITY',4,NULL,'Visible security reduces immediate seizure risk.',1),
(@o,'FACTOR','UNREST',2,NULL,'The military posture also increases public anxiety.',2);
SET @o=(SELECT dynamic_option_id FROM plot_dynamic_opportunity_options WHERE dynamic_opportunity_id=@d AND option_code='OBEY_HAND');
INSERT INTO plot_dynamic_option_effects(dynamic_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order) VALUES
(@o,'FACTOR','LEGITIMACY',3,NULL,'Guard obedience makes the Hand’s chosen process look more settled.',1);

-- Dynamic inaction is now causal. If the second opportunity expires, initiative shifts elsewhere.
INSERT INTO plot_no_action_effects(plot_id,event_code,role_code,effect_scope,target_code,delta_value,set_value,detail_text,sort_order)
SELECT @plot,'ELIAN_SECOND_CHANCE','ELIAN','FACTOR','LEGITIMACY',4,NULL,'Elian’s continued silence makes the existing succession path look more settled.',1
WHERE NOT EXISTS(SELECT 1 FROM plot_no_action_effects WHERE plot_id=@plot AND event_code='ELIAN_SECOND_CHANCE' AND role_code='ELIAN' AND target_code='LEGITIMACY');
INSERT INTO plot_no_action_effects(plot_id,event_code,role_code,effect_scope,target_code,delta_value,set_value,detail_text,sort_order)
SELECT @plot,'MARA_ORDER_GAP','MARA','FACTOR','UNREST',4,NULL,'Mara leaves the capital’s political anxiety unmanaged while the succession fight continues.',1
WHERE NOT EXISTS(SELECT 1 FROM plot_no_action_effects WHERE plot_id=@plot AND event_code='MARA_ORDER_GAP' AND role_code='MARA' AND target_code='UNREST');
INSERT INTO plot_no_action_effects(plot_id,event_code,role_code,effect_scope,target_code,delta_value,set_value,detail_text,sort_order)
SELECT @plot,'MARA_ORDER_GAP','MARA','FACTOR','STABILITY',-3,NULL,'Without a stabilizing intervention, uncertainty begins to affect ordinary government and supplies.',2
WHERE NOT EXISTS(SELECT 1 FROM plot_no_action_effects WHERE plot_id=@plot AND event_code='MARA_ORDER_GAP' AND role_code='MARA' AND target_code='STABILITY');
INSERT INTO plot_no_action_effects(plot_id,event_code,role_code,effect_scope,target_code,delta_value,set_value,detail_text,sort_order)
SELECT @plot,'TOMAS_HAND_CUSTODY_CONFLICT','TOMAS','FACTOR','COURT_TRUST',-4,NULL,'The guard’s silence leaves the conflict between political loyalty and evidence custody unresolved.',1
WHERE NOT EXISTS(SELECT 1 FROM plot_no_action_effects WHERE plot_id=@plot AND event_code='TOMAS_HAND_CUSTODY_CONFLICT' AND role_code='TOMAS' AND target_code='COURT_TRUST');

INSERT IGNORE INTO schema_migrations(migration) VALUES('025_generic_consequence_propagation.sql');