-- Generic entity/property state model. Engine code knows only entities, properties, values and transitions.
CREATE TABLE IF NOT EXISTS plot_entities (
  plot_entity_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  plot_id BIGINT UNSIGNED NOT NULL,
  entity_code VARCHAR(100) NOT NULL,
  label VARCHAR(180) NOT NULL,
  entity_type ENUM('PERSON','OBJECT','PLACE','INFORMATION','TASK','ABSTRACT') NOT NULL DEFAULT 'ABSTRACT',
  UNIQUE KEY uq_plot_entity(plot_id,entity_code),
  CONSTRAINT fk_plot_entity_plot FOREIGN KEY(plot_id) REFERENCES plots(plot_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plot_entity_properties (
  property_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  plot_entity_id BIGINT UNSIGNED NOT NULL,
  property_code VARCHAR(100) NOT NULL,
  label VARCHAR(180) NOT NULL,
  value_type ENUM('TEXT','NUMBER','BOOLEAN') NOT NULL DEFAULT 'TEXT',
  initial_value VARCHAR(500) NULL,
  allowed_values TEXT NULL,
  min_value DECIMAL(14,4) NULL,
  max_value DECIMAL(14,4) NULL,
  UNIQUE KEY uq_entity_property(plot_entity_id,property_code),
  CONSTRAINT fk_pep_entity FOREIGN KEY(plot_entity_id) REFERENCES plot_entities(plot_entity_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plot_property_transitions (
  property_transition_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  property_id BIGINT UNSIGNED NOT NULL,
  from_value VARCHAR(500) NULL,
  to_value VARCHAR(500) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_property_transition(property_id,from_value,to_value),
  CONSTRAINT fk_ppt_property FOREIGN KEY(property_id) REFERENCES plot_entity_properties(property_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plot_state_aliases (
  plot_id BIGINT UNSIGNED NOT NULL,
  state_key VARCHAR(190) NOT NULL,
  entity_code VARCHAR(100) NOT NULL,
  property_code VARCHAR(100) NOT NULL,
  PRIMARY KEY(plot_id,state_key),
  CONSTRAINT fk_psa_plot FOREIGN KEY(plot_id) REFERENCES plots(plot_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS simulation_entity_state (
  simulation_id BIGINT UNSIGNED NOT NULL,
  entity_code VARCHAR(100) NOT NULL,
  property_code VARCHAR(100) NOT NULL,
  state_value VARCHAR(500) NOT NULL,
  updated_at_second INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY(simulation_id,entity_code,property_code),
  CONSTRAINT fk_ses_sim FOREIGN KEY(simulation_id) REFERENCES simulations(simulation_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Generic, data-driven ending fragments. A plot can define any number of sections and alternatives.
CREATE TABLE IF NOT EXISTS plot_resolution_fragments (
  resolution_fragment_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  plot_id BIGINT UNSIGNED NOT NULL,
  section_code VARCHAR(100) NOT NULL,
  priority INT NOT NULL DEFAULT 100,
  fragment_text TEXT NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  KEY idx_resolution_fragments(plot_id,section_code,priority),
  CONSTRAINT fk_prf_plot FOREIGN KEY(plot_id) REFERENCES plots(plot_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plot_resolution_conditions (
  resolution_condition_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  resolution_fragment_id BIGINT UNSIGNED NOT NULL,
  state_key VARCHAR(190) NOT NULL,
  operator ENUM('EQ','NEQ','IN','NOT_IN','EXISTS','NOT_EXISTS','LT','LTE','GT','GTE') NOT NULL DEFAULT 'EQ',
  compare_value VARCHAR(500) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_prc_fragment FOREIGN KEY(resolution_fragment_id) REFERENCES plot_resolution_fragments(resolution_fragment_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @plot_id=(SELECT plot_id FROM plots WHERE code='CONCEALED_FAILURE' LIMIT 1);

INSERT IGNORE INTO plot_entities(plot_id,entity_code,label,entity_type) VALUES
(@plot_id,'GUEST','Injured Guest','PERSON'),
(@plot_id,'WEST_ROUTE','West Evacuation Route','PLACE'),
(@plot_id,'FARAH','Farah Xu','PERSON'),
(@plot_id,'FAILED_REPORT','Failed Inspection Report','OBJECT'),
(@plot_id,'ALDEN','Alden Rowan','PERSON'),
(@plot_id,'REPORT_COPY','Report Copy','OBJECT'),
(@plot_id,'EVIDENCE','Evidence Network','INFORMATION');

SET @e=(SELECT plot_entity_id FROM plot_entities WHERE plot_id=@plot_id AND entity_code='GUEST');
INSERT IGNORE INTO plot_entity_properties(plot_entity_id,property_code,label,value_type,initial_value,allowed_values,min_value,max_value) VALUES
(@e,'status','Guest Status','TEXT','INJURED','INJURED,STABILIZED,EVACUATING,EVACUATED,AT_RISK',NULL,NULL),
(@e,'helpers','Committed Helpers','NUMBER','1',NULL,0,6),
(@e,'second_helper','Second Helper','TEXT','NONE','NONE,KELLAN,WREN,NIKO',NULL,NULL);
SET @p=(SELECT property_id FROM plot_entity_properties WHERE plot_entity_id=@e AND property_code='status');
INSERT IGNORE INTO plot_property_transitions(property_id,from_value,to_value) VALUES
(@p,'INJURED','STABILIZED'),(@p,'INJURED','AT_RISK'),(@p,'STABILIZED','EVACUATING'),(@p,'STABILIZED','AT_RISK'),(@p,'EVACUATING','EVACUATED'),(@p,'EVACUATING','AT_RISK'),(@p,'AT_RISK','STABILIZED'),(@p,'AT_RISK','EVACUATING'),(@p,'AT_RISK','EVACUATED');

SET @e=(SELECT plot_entity_id FROM plot_entities WHERE plot_id=@plot_id AND entity_code='WEST_ROUTE');
INSERT IGNORE INTO plot_entity_properties(plot_entity_id,property_code,label,value_type,initial_value,allowed_values) VALUES
(@e,'status','Route Status','TEXT','UNKNOWN','UNKNOWN,USABLE,BLOCKED');

SET @e=(SELECT plot_entity_id FROM plot_entities WHERE plot_id=@plot_id AND entity_code='FARAH');
INSERT IGNORE INTO plot_entity_properties(plot_entity_id,property_code,label,value_type,initial_value,allowed_values) VALUES
(@e,'exit_status','Exit Status','TEXT','INSIDE','INSIDE,POSITIONED,ATTEMPTING,LEFT,BLOCKED');
SET @p=(SELECT property_id FROM plot_entity_properties WHERE plot_entity_id=@e AND property_code='exit_status');
INSERT IGNORE INTO plot_property_transitions(property_id,from_value,to_value) VALUES
(@p,'INSIDE','POSITIONED'),(@p,'POSITIONED','INSIDE'),(@p,'POSITIONED','ATTEMPTING'),(@p,'ATTEMPTING','LEFT'),(@p,'ATTEMPTING','BLOCKED');

SET @e=(SELECT plot_entity_id FROM plot_entities WHERE plot_id=@plot_id AND entity_code='FAILED_REPORT');
INSERT IGNORE INTO plot_entity_properties(plot_entity_id,property_code,label,value_type,initial_value,allowed_values) VALUES
(@e,'holder','Report Holder','TEXT','NONE','NONE,ALDEN,FARAH'),
(@e,'status','Report Status','TEXT','UNSECURED','UNSECURED,SECURED,CONCEALED,DAMAGED,DESTROYED');

SET @e=(SELECT plot_entity_id FROM plot_entities WHERE plot_id=@plot_id AND entity_code='ALDEN');
INSERT IGNORE INTO plot_entity_properties(plot_entity_id,property_code,label,value_type,initial_value,allowed_values) VALUES
(@e,'evidence_knowledge','Evidence Knowledge','TEXT','KNOWN','UNKNOWN,KNOWN'),
(@e,'shareable_evidence','Shareable Evidence','BOOLEAN','NO','NO,YES');

SET @e=(SELECT plot_entity_id FROM plot_entities WHERE plot_id=@plot_id AND entity_code='REPORT_COPY');
INSERT IGNORE INTO plot_entity_properties(plot_entity_id,property_code,label,value_type,initial_value,allowed_values) VALUES
(@e,'status','Copy Status','TEXT','NONE','NONE,ALDEN_COPY_EXISTS');

SET @e=(SELECT plot_entity_id FROM plot_entities WHERE plot_id=@plot_id AND entity_code='EVIDENCE');
INSERT IGNORE INTO plot_entity_properties(plot_entity_id,property_code,label,value_type,initial_value,allowed_values) VALUES
(@e,'witnesses','Evidence Witnesses','TEXT','ALDEN_ONLY','ALDEN_ONLY,NIKO_INFORMED,CORROBORATED'),
(@e,'externalized','Externalized','BOOLEAN','NO','NO,YES'),
(@e,'distribution','Distribution','TEXT','PRIVATE','PRIVATE,SHARED');

INSERT IGNORE INTO plot_state_aliases(plot_id,state_key,entity_code,property_code) VALUES
(@plot_id,'GUEST_STATUS','GUEST','status'),
(@plot_id,'GUEST_HELPERS','GUEST','helpers'),
(@plot_id,'GUEST_SECOND_HELPER','GUEST','second_helper'),
(@plot_id,'WEST_ROUTE','WEST_ROUTE','status'),
(@plot_id,'FARAH_EXIT_STATUS','FARAH','exit_status'),
(@plot_id,'REPORT_HOLDER','FAILED_REPORT','holder'),
(@plot_id,'FAILED_REPORT_STATE','FAILED_REPORT','status'),
(@plot_id,'ALDEN_EVIDENCE_KNOWLEDGE','ALDEN','evidence_knowledge'),
(@plot_id,'ALDEN_SHAREABLE_EVIDENCE','ALDEN','shareable_evidence'),
(@plot_id,'REPORT_COPY_STATE','REPORT_COPY','status'),
(@plot_id,'EVIDENCE_WITNESSES','EVIDENCE','witnesses'),
(@plot_id,'EVIDENCE_EXTERNALIZED','EVIDENCE','externalized'),
(@plot_id,'EVIDENCE_DISTRIBUTION','EVIDENCE','distribution');

-- Bellweather resolution is now data, not PHP.
DELETE c FROM plot_resolution_conditions c JOIN plot_resolution_fragments f ON f.resolution_fragment_id=c.resolution_fragment_id WHERE f.plot_id=@plot_id;
DELETE FROM plot_resolution_fragments WHERE plot_id=@plot_id;

INSERT INTO plot_resolution_fragments(plot_id,section_code,priority,fragment_text) VALUES
(@plot_id,'PEOPLE',10,'The injured guest reaches the west evacuation point.'),
(@plot_id,'PEOPLE',20,'The injured guest remains exposed as the east-side danger peaks.'),
(@plot_id,'PEOPLE',30,'The injured guest is still being moved when the immediate story closes.'),
(@plot_id,'PEOPLE',40,'The injured guest is stabilized but remains inside the danger zone.'),
(@plot_id,'PEOPLE',100,'The injured guest’s evacuation remains unresolved.'),
(@plot_id,'TRUTH',10,'An evidence summary now exists outside Bellweather, so local control of the original report can no longer erase the entire evidence trail.'),
(@plot_id,'TRUTH',20,'Evidence survives in more than one recoverable path, so no single character completely controls the proof.'),
(@plot_id,'TRUTH',30,'The evidence is no longer only Alden’s private claim; another person can corroborate at least part of what he knows.'),
(@plot_id,'TRUTH',40,'Alden still physically controls the failed report, but the evidence has not yet been distributed beyond him.'),
(@plot_id,'TRUTH',50,'Farah physically controls the failed report, leaving the documentary evidence vulnerable to her decisions.'),
(@plot_id,'TRUTH',100,'Enough knowledge and circumstantial evidence survives to keep the concealment question alive, but the physical report is not securely distributed.'),
(@plot_id,'FARAH',10,'Farah is stopped at the rear exit and cannot simply carry the night’s unresolved questions away with her.'),
(@plot_id,'FARAH',20,'Farah leaves Bellweather before the group can fully resolve the confrontation.'),
(@plot_id,'FARAH',30,'Farah is still trying to leave as the final confrontation closes.'),
(@plot_id,'FARAH',40,'Farah remains near the rear exit but never completes an escape attempt.'),
(@plot_id,'FARAH',100,'Farah remains inside Bellweather as the confrontation closes.');

SET @f=(SELECT resolution_fragment_id FROM plot_resolution_fragments WHERE plot_id=@plot_id AND section_code='PEOPLE' AND priority=10 LIMIT 1); INSERT INTO plot_resolution_conditions(resolution_fragment_id,state_key,operator,compare_value) VALUES(@f,'GUEST_STATUS','EQ','EVACUATED');
SET @f=(SELECT resolution_fragment_id FROM plot_resolution_fragments WHERE plot_id=@plot_id AND section_code='PEOPLE' AND priority=20 LIMIT 1); INSERT INTO plot_resolution_conditions(resolution_fragment_id,state_key,operator,compare_value) VALUES(@f,'GUEST_STATUS','EQ','AT_RISK');
SET @f=(SELECT resolution_fragment_id FROM plot_resolution_fragments WHERE plot_id=@plot_id AND section_code='PEOPLE' AND priority=30 LIMIT 1); INSERT INTO plot_resolution_conditions(resolution_fragment_id,state_key,operator,compare_value) VALUES(@f,'GUEST_STATUS','EQ','EVACUATING');
SET @f=(SELECT resolution_fragment_id FROM plot_resolution_fragments WHERE plot_id=@plot_id AND section_code='PEOPLE' AND priority=40 LIMIT 1); INSERT INTO plot_resolution_conditions(resolution_fragment_id,state_key,operator,compare_value) VALUES(@f,'GUEST_STATUS','EQ','STABILIZED');

SET @f=(SELECT resolution_fragment_id FROM plot_resolution_fragments WHERE plot_id=@plot_id AND section_code='TRUTH' AND priority=10 LIMIT 1); INSERT INTO plot_resolution_conditions(resolution_fragment_id,state_key,operator,compare_value) VALUES(@f,'EVIDENCE_EXTERNALIZED','EQ','YES');
SET @f=(SELECT resolution_fragment_id FROM plot_resolution_fragments WHERE plot_id=@plot_id AND section_code='TRUTH' AND priority=20 LIMIT 1); INSERT INTO plot_resolution_conditions(resolution_fragment_id,state_key,operator,compare_value) VALUES(@f,'EVIDENCE_DISTRIBUTION','EQ','SHARED');
SET @f=(SELECT resolution_fragment_id FROM plot_resolution_fragments WHERE plot_id=@plot_id AND section_code='TRUTH' AND priority=30 LIMIT 1); INSERT INTO plot_resolution_conditions(resolution_fragment_id,state_key,operator,compare_value) VALUES(@f,'EVIDENCE_WITNESSES','IN','NIKO_INFORMED,CORROBORATED');
SET @f=(SELECT resolution_fragment_id FROM plot_resolution_fragments WHERE plot_id=@plot_id AND section_code='TRUTH' AND priority=40 LIMIT 1); INSERT INTO plot_resolution_conditions(resolution_fragment_id,state_key,operator,compare_value) VALUES(@f,'REPORT_HOLDER','EQ','ALDEN');
SET @f=(SELECT resolution_fragment_id FROM plot_resolution_fragments WHERE plot_id=@plot_id AND section_code='TRUTH' AND priority=50 LIMIT 1); INSERT INTO plot_resolution_conditions(resolution_fragment_id,state_key,operator,compare_value) VALUES(@f,'REPORT_HOLDER','EQ','FARAH');

SET @f=(SELECT resolution_fragment_id FROM plot_resolution_fragments WHERE plot_id=@plot_id AND section_code='FARAH' AND priority=10 LIMIT 1); INSERT INTO plot_resolution_conditions(resolution_fragment_id,state_key,operator,compare_value) VALUES(@f,'FARAH_EXIT_STATUS','EQ','BLOCKED');
SET @f=(SELECT resolution_fragment_id FROM plot_resolution_fragments WHERE plot_id=@plot_id AND section_code='FARAH' AND priority=20 LIMIT 1); INSERT INTO plot_resolution_conditions(resolution_fragment_id,state_key,operator,compare_value) VALUES(@f,'FARAH_EXIT_STATUS','EQ','LEFT');
SET @f=(SELECT resolution_fragment_id FROM plot_resolution_fragments WHERE plot_id=@plot_id AND section_code='FARAH' AND priority=30 LIMIT 1); INSERT INTO plot_resolution_conditions(resolution_fragment_id,state_key,operator,compare_value) VALUES(@f,'FARAH_EXIT_STATUS','EQ','ATTEMPTING');
SET @f=(SELECT resolution_fragment_id FROM plot_resolution_fragments WHERE plot_id=@plot_id AND section_code='FARAH' AND priority=40 LIMIT 1); INSERT INTO plot_resolution_conditions(resolution_fragment_id,state_key,operator,compare_value) VALUES(@f,'FARAH_EXIT_STATUS','EQ','POSITIONED');

INSERT IGNORE INTO schema_migrations(migration) VALUES('020_generic_entity_state_and_resolution.sql');