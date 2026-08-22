-- Configure semantic outcome summaries per plot without story-specific PHP.
CREATE TABLE IF NOT EXISTS plot_outcome_dimensions (
  outcome_dimension_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  plot_id BIGINT UNSIGNED NOT NULL,
  dimension_code VARCHAR(80) NOT NULL,
  label_text VARCHAR(120) NOT NULL,
  source_type ENUM('WORLD_STATE','FACTOR') NOT NULL,
  source_key VARCHAR(120) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (outcome_dimension_id),
  UNIQUE KEY uq_plot_outcome_dimension (plot_id,dimension_code),
  KEY idx_plot_outcome_dimensions (plot_id,active,sort_order),
  CONSTRAINT fk_plot_outcome_dimensions_plot FOREIGN KEY (plot_id) REFERENCES plots(plot_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET @empty_throne=(SELECT plot_id FROM plots WHERE code='EMPTY_THRONE' LIMIT 1);
INSERT INTO plot_outcome_dimensions(plot_id,dimension_code,label_text,source_type,source_key,sort_order,active)
SELECT @empty_throne,'RULER','RULER','WORLD_STATE','CROWN_HOLDER',10,1 WHERE @empty_throne IS NOT NULL
ON DUPLICATE KEY UPDATE label_text=VALUES(label_text),source_type=VALUES(source_type),source_key=VALUES(source_key),sort_order=VALUES(sort_order),active=1;
INSERT INTO plot_outcome_dimensions(plot_id,dimension_code,label_text,source_type,source_key,sort_order,active)
SELECT @empty_throne,'EVIDENCE','EVIDENCE','WORLD_STATE','LETTER_STATUS',20,1 WHERE @empty_throne IS NOT NULL
ON DUPLICATE KEY UPDATE label_text=VALUES(label_text),source_type=VALUES(source_type),source_key=VALUES(source_key),sort_order=VALUES(sort_order),active=1;
INSERT INTO plot_outcome_dimensions(plot_id,dimension_code,label_text,source_type,source_key,sort_order,active)
SELECT @empty_throne,'ELIAN_CLAIM','ELIAN','WORLD_STATE','ELIAN_CLAIM',30,1 WHERE @empty_throne IS NOT NULL
ON DUPLICATE KEY UPDATE label_text=VALUES(label_text),source_type=VALUES(source_type),source_key=VALUES(source_key),sort_order=VALUES(sort_order),active=1;
INSERT INTO plot_outcome_dimensions(plot_id,dimension_code,label_text,source_type,source_key,sort_order,active)
SELECT @empty_throne,'ROWAN_CLAIM','ROWAN','WORLD_STATE','ROWAN_CLAIM',40,1 WHERE @empty_throne IS NOT NULL
ON DUPLICATE KEY UPDATE label_text=VALUES(label_text),source_type=VALUES(source_type),source_key=VALUES(source_key),sort_order=VALUES(sort_order),active=1;
INSERT INTO plot_outcome_dimensions(plot_id,dimension_code,label_text,source_type,source_key,sort_order,active)
SELECT @empty_throne,'STABILITY','STABILITY','FACTOR','STABILITY',50,1 WHERE @empty_throne IS NOT NULL
ON DUPLICATE KEY UPDATE label_text=VALUES(label_text),source_type=VALUES(source_type),source_key=VALUES(source_key),sort_order=VALUES(sort_order),active=1;
INSERT INTO plot_outcome_dimensions(plot_id,dimension_code,label_text,source_type,source_key,sort_order,active)
SELECT @empty_throne,'UNREST','UNREST','FACTOR','UNREST',60,1 WHERE @empty_throne IS NOT NULL
ON DUPLICATE KEY UPDATE label_text=VALUES(label_text),source_type=VALUES(source_type),source_key=VALUES(source_key),sort_order=VALUES(sort_order),active=1;

SET @concealed_failure=(SELECT plot_id FROM plots WHERE code='CONCEALED_FAILURE' LIMIT 1);
INSERT INTO plot_outcome_dimensions(plot_id,dimension_code,label_text,source_type,source_key,sort_order,active)
SELECT @concealed_failure,'FAILURE','FAILURE','WORLD_STATE','FAILURE_STATUS',10,1 WHERE @concealed_failure IS NOT NULL
ON DUPLICATE KEY UPDATE label_text=VALUES(label_text),source_type=VALUES(source_type),source_key=VALUES(source_key),sort_order=VALUES(sort_order),active=1;
INSERT INTO plot_outcome_dimensions(plot_id,dimension_code,label_text,source_type,source_key,sort_order,active)
SELECT @concealed_failure,'EVACUATION','EVACUATION','WORLD_STATE','EVACUATION_STATUS',20,1 WHERE @concealed_failure IS NOT NULL
ON DUPLICATE KEY UPDATE label_text=VALUES(label_text),source_type=VALUES(source_type),source_key=VALUES(source_key),sort_order=VALUES(sort_order),active=1;
INSERT INTO plot_outcome_dimensions(plot_id,dimension_code,label_text,source_type,source_key,sort_order,active)
SELECT @concealed_failure,'GUESTS','GUESTS','WORLD_STATE','GUEST_STATUS',30,1 WHERE @concealed_failure IS NOT NULL
ON DUPLICATE KEY UPDATE label_text=VALUES(label_text),source_type=VALUES(source_type),source_key=VALUES(source_key),sort_order=VALUES(sort_order),active=1;
INSERT INTO plot_outcome_dimensions(plot_id,dimension_code,label_text,source_type,source_key,sort_order,active)
SELECT @concealed_failure,'OVERRIDE','OVERRIDE','WORLD_STATE','OVERRIDE_STATUS',40,1 WHERE @concealed_failure IS NOT NULL
ON DUPLICATE KEY UPDATE label_text=VALUES(label_text),source_type=VALUES(source_type),source_key=VALUES(source_key),sort_order=VALUES(sort_order),active=1;
INSERT INTO plot_outcome_dimensions(plot_id,dimension_code,label_text,source_type,source_key,sort_order,active)
SELECT @concealed_failure,'SAFETY','SAFETY','FACTOR','SAFETY',50,1 WHERE @concealed_failure IS NOT NULL
ON DUPLICATE KEY UPDATE label_text=VALUES(label_text),source_type=VALUES(source_type),source_key=VALUES(source_key),sort_order=VALUES(sort_order),active=1;
INSERT INTO plot_outcome_dimensions(plot_id,dimension_code,label_text,source_type,source_key,sort_order,active)
SELECT @concealed_failure,'SUSPICION','SUSPICION','FACTOR','SUSPICION',60,1 WHERE @concealed_failure IS NOT NULL
ON DUPLICATE KEY UPDATE label_text=VALUES(label_text),source_type=VALUES(source_type),source_key=VALUES(source_key),sort_order=VALUES(sort_order),active=1;

INSERT IGNORE INTO schema_migrations(migration) VALUES('038_database_driven_outcome_dimensions.sql');
