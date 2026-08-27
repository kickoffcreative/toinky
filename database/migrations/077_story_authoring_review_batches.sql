-- Managed Story Writer vocabulary and durable simulation-review batches.

CREATE TABLE IF NOT EXISTS plot_locations (
  plot_location_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  plot_id BIGINT UNSIGNED NOT NULL,
  location_code VARCHAR(80) NOT NULL,
  label_text VARCHAR(190) NOT NULL,
  description TEXT NULL,
  sort_order INT NOT NULL DEFAULT 100,
  active TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_plot_location(plot_id,location_code),
  KEY idx_plot_location_order(plot_id,active,sort_order),
  CONSTRAINT fk_plot_location_plot FOREIGN KEY(plot_id) REFERENCES plots(plot_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO plot_locations(plot_id,location_code,label_text,sort_order)
SELECT plot_id,UPPER(TRIM(BOTH '_' FROM REGEXP_REPLACE(starting_location,'[^A-Za-z0-9]+','_'))),starting_location,slot_no
FROM plot_roles WHERE TRIM(starting_location)<>'';

CREATE TABLE IF NOT EXISTS plot_state_definitions (
  plot_state_definition_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  plot_id BIGINT UNSIGNED NOT NULL,
  effect_scope ENUM('FACTOR','STAT','LOCATION','KNOWLEDGE','RELATIONSHIP','WORLD_STATE','GOAL','FEAR') NOT NULL,
  target_code VARCHAR(100) NOT NULL,
  label_text VARCHAR(190) NOT NULL,
  value_type ENUM('NUMERIC','PRESET','TEXT') NOT NULL DEFAULT 'TEXT',
  default_numeric_value INT NULL,
  allowed_values_text TEXT NULL,
  description TEXT NULL,
  sort_order INT NOT NULL DEFAULT 100,
  active TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_plot_state_definition(plot_id,effect_scope,target_code),
  KEY idx_plot_state_definition_order(plot_id,effect_scope,active,sort_order),
  CONSTRAINT fk_plot_state_definition_plot FOREIGN KEY(plot_id) REFERENCES plots(plot_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO plot_state_definitions(plot_id,effect_scope,target_code,label_text,value_type,default_numeric_value,description,sort_order)
SELECT plot_id,'FACTOR',factor_code,label,'NUMERIC',initial_value,'A numeric story factor changed by option effects.',plot_factor_id
FROM plot_factors;

INSERT IGNORE INTO plot_state_definitions(plot_id,effect_scope,target_code,label_text,value_type,default_numeric_value,description,sort_order)
SELECT pr.plot_id,'STAT',s.stat_code,s.label,'NUMERIC',s.initial_value,'A numerical character statistic.',s.plot_role_stat_id
FROM plot_role_stats s JOIN plot_roles pr ON pr.plot_role_id=s.plot_role_id;

INSERT IGNORE INTO plot_state_definitions(plot_id,effect_scope,target_code,label_text,value_type,description,sort_order)
SELECT DISTINCT t.plot_id,e.effect_scope,e.target_code,
  CONCAT(UPPER(LEFT(REPLACE(e.target_code,'_',' '),1)),LOWER(SUBSTRING(REPLACE(e.target_code,'_',' '),2))),
  CASE WHEN e.delta_value IS NOT NULL THEN 'NUMERIC' WHEN e.set_value IS NOT NULL THEN 'PRESET' ELSE 'TEXT' END,
  'Imported from an existing authored option effect.',e.sort_order
FROM plot_option_effects e
JOIN plot_decision_template_options o ON o.template_option_id=e.template_option_id
JOIN plot_decision_templates t ON t.template_id=o.template_id
WHERE TRIM(e.target_code)<>'';

INSERT IGNORE INTO plot_state_definitions(plot_id,effect_scope,target_code,label_text,value_type,description,sort_order)
SELECT DISTINCT o.plot_id,c.source_type,c.source_key,
  CONCAT(UPPER(LEFT(REPLACE(c.source_key,'_',' '),1)),LOWER(SUBSTRING(REPLACE(c.source_key,'_',' '),2))),
  CASE WHEN c.operator IN ('LT','LTE','GT','GTE') THEN 'NUMERIC' ELSE 'PRESET' END,
  'Imported from an existing conditional opportunity.',c.sort_order
FROM plot_dynamic_opportunity_conditions c JOIN plot_dynamic_opportunities o ON o.dynamic_opportunity_id=c.dynamic_opportunity_id
WHERE c.source_type IN ('WORLD_STATE','FACTOR','STAT','LOCATION','KNOWLEDGE','RELATIONSHIP','GOAL','FEAR') AND c.source_key IS NOT NULL AND TRIM(c.source_key)<>'';

INSERT IGNORE INTO plot_state_definitions(plot_id,effect_scope,target_code,label_text,value_type,description,sort_order)
SELECT DISTINCT f.plot_id,
  CASE WHEN EXISTS(SELECT 1 FROM plot_factors pf WHERE pf.plot_id=f.plot_id AND pf.factor_code=c.state_key) THEN 'FACTOR' ELSE 'WORLD_STATE' END,
  c.state_key,
  CONCAT(UPPER(LEFT(REPLACE(c.state_key,'_',' '),1)),LOWER(SUBSTRING(REPLACE(c.state_key,'_',' '),2))),
  CASE WHEN c.operator IN ('LT','LTE','GT','GTE') THEN 'NUMERIC' ELSE 'PRESET' END,
  'Imported from an existing ending condition.',c.sort_order
FROM plot_resolution_conditions c JOIN plot_resolution_fragments f ON f.resolution_fragment_id=c.resolution_fragment_id
WHERE TRIM(c.state_key)<>'';

INSERT IGNORE INTO plot_state_definitions(plot_id,effect_scope,target_code,label_text,value_type,description,sort_order)
SELECT DISTINCT o.plot_id,e.effect_scope,e.target_code,
  CONCAT(UPPER(LEFT(REPLACE(e.target_code,'_',' '),1)),LOWER(SUBSTRING(REPLACE(e.target_code,'_',' '),2))),
  CASE WHEN e.delta_value IS NOT NULL THEN 'NUMERIC' WHEN e.set_value IS NOT NULL THEN 'PRESET' ELSE 'TEXT' END,
  'Imported from an existing authored conditional effect.',e.sort_order
FROM plot_dynamic_option_effects e
JOIN plot_dynamic_opportunity_options x ON x.dynamic_option_id=e.dynamic_option_id
JOIN plot_dynamic_opportunities o ON o.dynamic_opportunity_id=x.dynamic_opportunity_id
WHERE TRIM(e.target_code)<>'';

UPDATE plot_state_definitions d
JOIN (
  SELECT plot_id,effect_scope,target_code,GROUP_CONCAT(DISTINCT set_value ORDER BY set_value SEPARATOR '\n') allowed_values
  FROM (
    SELECT t.plot_id,e.effect_scope,e.target_code,e.set_value
    FROM plot_option_effects e JOIN plot_decision_template_options o ON o.template_option_id=e.template_option_id JOIN plot_decision_templates t ON t.template_id=o.template_id
    WHERE e.set_value IS NOT NULL AND TRIM(e.set_value)<>''
    UNION ALL
    SELECT o.plot_id,e.effect_scope,e.target_code,e.set_value
    FROM plot_dynamic_option_effects e JOIN plot_dynamic_opportunity_options x ON x.dynamic_option_id=e.dynamic_option_id JOIN plot_dynamic_opportunities o ON o.dynamic_opportunity_id=x.dynamic_opportunity_id
    WHERE e.set_value IS NOT NULL AND TRIM(e.set_value)<>''
  ) values_used GROUP BY plot_id,effect_scope,target_code
) allowed ON allowed.plot_id=d.plot_id AND allowed.effect_scope=d.effect_scope AND allowed.target_code=d.target_code
SET d.allowed_values_text=allowed.allowed_values
WHERE d.value_type='PRESET';

CREATE TABLE IF NOT EXISTS plot_story_sections (
  plot_story_section_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  plot_id BIGINT UNSIGNED NOT NULL,
  section_code VARCHAR(80) NOT NULL,
  label_text VARCHAR(190) NOT NULL,
  description TEXT NULL,
  sort_order INT NOT NULL DEFAULT 100,
  active TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_plot_story_section(plot_id,section_code),
  KEY idx_plot_story_section_order(plot_id,active,sort_order),
  CONSTRAINT fk_plot_story_section_plot FOREIGN KEY(plot_id) REFERENCES plots(plot_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO plot_story_sections(plot_id,section_code,label_text,sort_order)
SELECT DISTINCT plot_id,section_code,CONCAT(UPPER(LEFT(REPLACE(section_code,'_',' '),1)),LOWER(SUBSTRING(REPLACE(section_code,'_',' '),2))),section_order
FROM plot_resolution_fragments;
INSERT IGNORE INTO plot_story_sections(plot_id,section_code,label_text,description,sort_order)
SELECT plot_id,'AFTERMATH','Aftermath','The wider consequences after the immediate outcome.',80 FROM plots;
INSERT IGNORE INTO plot_story_sections(plot_id,section_code,label_text,description,sort_order)
SELECT plot_id,'INVITATION','Invitation','A final question or hook that invites replay.',90 FROM plots;

CREATE TABLE IF NOT EXISTS simulation_review_batches (
  simulation_review_batch_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  plot_id BIGINT UNSIGNED NOT NULL,
  plot_version_id BIGINT UNSIGNED NULL,
  requested_by_user_id BIGINT UNSIGNED NOT NULL,
  controller_mode ENUM('AUTONOMOUS','HUMAN_RANDOM','HUMAN_BIASED') NOT NULL,
  requested_runs INT UNSIGNED NOT NULL,
  completed_runs INT UNSIGNED NOT NULL DEFAULT 0,
  duration_seconds INT UNSIGNED NOT NULL DEFAULT 300,
  step_seconds INT UNSIGNED NOT NULL DEFAULT 30,
  status ENUM('QUEUED','RUNNING','COMPLETE','FAILED','CANCELLED') NOT NULL DEFAULT 'QUEUED',
  summary_json LONGTEXT NULL,
  review_package LONGTEXT NULL,
  last_error TEXT NULL,
  created_at_utc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  started_at_utc DATETIME NULL,
  completed_at_utc DATETIME NULL,
  updated_at_utc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_sim_review_queue(status,created_at_utc),
  KEY idx_sim_review_plot(plot_id,created_at_utc),
  CONSTRAINT fk_sim_review_plot FOREIGN KEY(plot_id) REFERENCES plots(plot_id) ON DELETE CASCADE,
  CONSTRAINT fk_sim_review_version FOREIGN KEY(plot_version_id) REFERENCES plot_versions(plot_version_id) ON DELETE SET NULL,
  CONSTRAINT fk_sim_review_user FOREIGN KEY(requested_by_user_id) REFERENCES users(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS simulation_review_batch_runs (
  simulation_review_batch_run_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  simulation_review_batch_id BIGINT UNSIGNED NOT NULL,
  run_no INT UNSIGNED NOT NULL,
  simulation_id BIGINT UNSIGNED NULL,
  result_json LONGTEXT NOT NULL,
  created_at_utc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_sim_review_run(simulation_review_batch_id,run_no),
  KEY idx_sim_review_simulation(simulation_id),
  CONSTRAINT fk_sim_review_run_batch FOREIGN KEY(simulation_review_batch_id) REFERENCES simulation_review_batches(simulation_review_batch_id) ON DELETE CASCADE,
  CONSTRAINT fk_sim_review_run_sim FOREIGN KEY(simulation_id) REFERENCES simulations(simulation_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO schema_migrations(migration) VALUES('077_story_authoring_review_batches.sql');
