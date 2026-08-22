-- Generic database-driven effects attached to authored world events.
CREATE TABLE IF NOT EXISTS plot_world_event_effects (
  world_event_effect_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  plot_id BIGINT UNSIGNED NOT NULL,
  event_code VARCHAR(120) NOT NULL,
  effect_scope ENUM('WORLD_STATE','FACTOR') NOT NULL,
  target_code VARCHAR(190) NOT NULL,
  delta_value INT NULL,
  set_value VARCHAR(500) NULL,
  detail_text TEXT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY(world_event_effect_id),
  KEY idx_world_event_effect(plot_id,event_code,active,sort_order),
  CONSTRAINT fk_world_event_effect_plot FOREIGN KEY(plot_id) REFERENCES plots(plot_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plot_world_event_effect_conditions (
  world_event_effect_condition_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  world_event_effect_id BIGINT UNSIGNED NOT NULL,
  state_key VARCHAR(190) NOT NULL,
  operator ENUM('EQ','NEQ','IN','NOT_IN','EXISTS','NOT_EXISTS','LT','LTE','GT','GTE') NOT NULL DEFAULT 'EQ',
  compare_value VARCHAR(500) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  PRIMARY KEY(world_event_effect_condition_id),
  KEY idx_world_event_effect_condition(world_event_effect_id,sort_order),
  CONSTRAINT fk_world_event_effect_condition FOREIGN KEY(world_event_effect_id) REFERENCES plot_world_event_effects(world_event_effect_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @plot=(SELECT plot_id FROM plots WHERE code='CONCEALED_FAILURE' LIMIT 1);

DELETE c FROM plot_world_event_effect_conditions c
JOIN plot_world_event_effects e ON e.world_event_effect_id=c.world_event_effect_id
WHERE e.plot_id=@plot AND e.event_code='REPORT_MISSING' AND e.target_code IN ('REPORT_HOLDER','FAILED_REPORT_STATE');
DELETE FROM plot_world_event_effects
WHERE plot_id=@plot AND event_code='REPORT_MISSING' AND target_code IN ('REPORT_HOLDER','FAILED_REPORT_STATE');

INSERT INTO plot_world_event_effects(plot_id,event_code,effect_scope,target_code,set_value,detail_text,sort_order,active)
SELECT @plot,'REPORT_MISSING','WORLD_STATE','REPORT_HOLDER','ALDEN','When the report dispute becomes public, Alden produces and controls the copied failed report unless someone already secured it.',10,1
WHERE @plot IS NOT NULL;
SET @holder_effect=LAST_INSERT_ID();
INSERT INTO plot_world_event_effect_conditions(world_event_effect_id,state_key,operator,compare_value,sort_order) VALUES
(@holder_effect,'REPORT_HOLDER','EQ','NONE',1),
(@holder_effect,'FAILED_REPORT_STATE','EQ','UNSECURED',2);

INSERT INTO plot_world_event_effects(plot_id,event_code,effect_scope,target_code,set_value,detail_text,sort_order,active)
SELECT @plot,'REPORT_MISSING','WORLD_STATE','FAILED_REPORT_STATE','SECURED_BY_ALDEN','Alden’s production of the copied failed report makes its custody authoritative.',20,1
WHERE @plot IS NOT NULL;
SET @state_effect=LAST_INSERT_ID();
INSERT INTO plot_world_event_effect_conditions(world_event_effect_id,state_key,operator,compare_value,sort_order) VALUES
(@state_effect,'FAILED_REPORT_STATE','EQ','UNSECURED',1);

INSERT IGNORE INTO schema_migrations(migration)
VALUES('040_database_driven_world_event_effects.sql');
