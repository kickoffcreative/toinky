-- Expand reusable effect vocabulary. Existing stories continue to work.
ALTER TABLE plot_option_effects MODIFY effect_scope ENUM('FACTOR','STAT','LOCATION','KNOWLEDGE','RELATIONSHIP','WORLD_STATE','GOAL','FEAR') NOT NULL;
ALTER TABLE plot_no_action_effects MODIFY effect_scope ENUM('FACTOR','STAT','LOCATION','KNOWLEDGE','RELATIONSHIP','WORLD_STATE','GOAL','FEAR') NOT NULL;

-- A triggered transition is plot data, not engine code. It fires when ALL of its state conditions are true.
CREATE TABLE IF NOT EXISTS plot_triggered_transitions (
  transition_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  plot_id BIGINT UNSIGNED NOT NULL,
  transition_code VARCHAR(120) NOT NULL,
  label VARCHAR(255) NOT NULL,
  once_only TINYINT(1) NOT NULL DEFAULT 1,
  priority INT NOT NULL DEFAULT 100,
  active TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_plot_transition(plot_id,transition_code),
  CONSTRAINT fk_ptt_plot FOREIGN KEY(plot_id) REFERENCES plots(plot_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plot_triggered_transition_conditions (
  transition_condition_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  transition_id BIGINT UNSIGNED NOT NULL,
  state_key VARCHAR(190) NOT NULL,
  operator ENUM('EQ','NEQ','IN','NOT_IN','EXISTS','NOT_EXISTS') NOT NULL DEFAULT 'EQ',
  compare_value VARCHAR(500) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_pttc_transition FOREIGN KEY(transition_id) REFERENCES plot_triggered_transitions(transition_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plot_triggered_transition_effects (
  transition_effect_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  transition_id BIGINT UNSIGNED NOT NULL,
  effect_scope ENUM('FACTOR','STAT','LOCATION','KNOWLEDGE','RELATIONSHIP','WORLD_STATE','GOAL','FEAR') NOT NULL,
  target_role_code VARCHAR(100) NULL,
  target_code VARCHAR(190) NOT NULL,
  delta_value INT NULL,
  set_value VARCHAR(500) NULL,
  detail_text TEXT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_ptte_transition FOREIGN KEY(transition_id) REFERENCES plot_triggered_transitions(transition_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @plot_id=(SELECT plot_id FROM plots WHERE code='CONCEALED_FAILURE' LIMIT 1);

-- Generic causal rule: a stabilized guest + two committed helpers + usable route means evacuation begins.
INSERT INTO plot_triggered_transitions(plot_id,transition_code,label,once_only,priority,active)
VALUES(@plot_id,'RESCUE_READY_TO_EVACUATE','Rescue conditions are sufficient to begin evacuation',1,20,1)
ON DUPLICATE KEY UPDATE label=VALUES(label),priority=VALUES(priority),active=1;
SET @tr=(SELECT transition_id FROM plot_triggered_transitions WHERE plot_id=@plot_id AND transition_code='RESCUE_READY_TO_EVACUATE');
DELETE FROM plot_triggered_transition_conditions WHERE transition_id=@tr;
INSERT INTO plot_triggered_transition_conditions(transition_id,state_key,operator,compare_value,sort_order) VALUES
(@tr,'GUEST_STATUS','EQ','STABILIZED',1),
(@tr,'GUEST_HELPERS','EQ','2',2),
(@tr,'WEST_ROUTE','EQ','USABLE',3);
DELETE FROM plot_triggered_transition_effects WHERE transition_id=@tr;
INSERT INTO plot_triggered_transition_effects(transition_id,effect_scope,target_role_code,target_code,set_value,detail_text,sort_order) VALUES
(@tr,'WORLD_STATE',NULL,'GUEST_STATUS','EVACUATING','The rescue now has a stabilized guest, enough committed helpers and a usable route, so evacuation begins.',1),
(@tr,'GOAL','LEILA','SELF','Get the injured guest to the west evacuation point.','The objective advances from treatment and preparation to completing the evacuation.',2),
(@tr,'FEAR','LEILA','SELF','The evacuation failing before the guest reaches safety.','The immediate risk is now completing movement through the hazardous building.',3),
(@tr,'GOAL','KELLAN','SELF','Get the injured guest to the west evacuation point.','Kellan’s earlier commitment as second helper now becomes active evacuation work.',4),
(@tr,'FEAR','KELLAN','SELF','The evacuation failing before the guest reaches safety.','Kellan is now physically committed to the rescue route.',5);

-- If the evacuation has begun, the climax can complete it without reverting to shelter logic.
INSERT INTO plot_triggered_transitions(plot_id,transition_code,label,once_only,priority,active)
VALUES(@plot_id,'RESCUE_COMPLETE_AT_CLIMAX','Complete an evacuation that is already underway',1,200,1)
ON DUPLICATE KEY UPDATE label=VALUES(label),priority=VALUES(priority),active=1;
SET @tr=(SELECT transition_id FROM plot_triggered_transitions WHERE plot_id=@plot_id AND transition_code='RESCUE_COMPLETE_AT_CLIMAX');
DELETE FROM plot_triggered_transition_conditions WHERE transition_id=@tr;
INSERT INTO plot_triggered_transition_conditions(transition_id,state_key,operator,compare_value,sort_order) VALUES
(@tr,'GUEST_STATUS','EQ','EVACUATING',1),
(@tr,'STORY_PHASE','IN','CLIMAX,RESOLUTION,COMPLETE',2);
DELETE FROM plot_triggered_transition_effects WHERE transition_id=@tr;
INSERT INTO plot_triggered_transition_effects(transition_id,effect_scope,target_role_code,target_code,set_value,detail_text,sort_order) VALUES
(@tr,'WORLD_STATE',NULL,'GUEST_STATUS','EVACUATED','The evacuation already underway reaches the west evacuation point.',1),
(@tr,'LOCATION','LEILA','SELF','West Evacuation Point','Leila completes the evacuation with the injured guest.',2),
(@tr,'GOAL','LEILA','SELF','Keep the evacuated guest stable and help others clear the remaining danger.','The original rescue objective has been achieved.',3);

INSERT IGNORE INTO schema_migrations(migration) VALUES('016_generic_triggered_transitions.sql');