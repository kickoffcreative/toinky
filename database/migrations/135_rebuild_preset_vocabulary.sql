-- Rebuild the Story Framework preset vocabulary from every value the
-- stories actually use (migration 135).
-- Migration 077 imported each PRESET definition's allowed values only from
-- effect set_values, so every legitimate condition value that no effect
-- ever sets fails the immersion audit as "not an allowed Preset Value":
-- starting states (NONE, INJURED), intermediate states (PUBLIC, SEALED,
-- EVACUATING, STABILIZED), holder names (FARAH, NIKO), claim states
-- (PRESUMED_HEIR, SECONDARY), route states (UNKNOWN). Migration 134 fixed
-- two keys by hand; this rebuilds the vocabulary for every PRESET
-- definition at once, from effect set_values plus the compare values of
-- the two condition tables the audit validates (dynamic opportunities and
-- ending conditions). Existing allowed values are kept, so nothing is ever
-- removed; values are only added.

SET SESSION group_concat_max_len = 100000;

CREATE TEMPORARY TABLE tmp_preset_vocabulary (
  plot_id BIGINT UNSIGNED NOT NULL,
  effect_scope VARCHAR(20) NOT NULL,
  target_code VARCHAR(100) NOT NULL,
  value_text VARCHAR(255) NOT NULL,
  PRIMARY KEY (plot_id, effect_scope, target_code, value_text)
);

-- 1. Keep every allowed value already recorded.
INSERT IGNORE INTO tmp_preset_vocabulary(plot_id,effect_scope,target_code,value_text)
SELECT d.plot_id,d.effect_scope,d.target_code,
  TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(d.allowed_values_text,'\n',n.n),'\n',-1))
FROM plot_state_definitions d
JOIN (SELECT 1 n UNION ALL SELECT 2 n UNION ALL SELECT 3 n UNION ALL SELECT 4 n UNION ALL SELECT 5 n UNION ALL SELECT 6 n UNION ALL SELECT 7 n UNION ALL SELECT 8 n UNION ALL SELECT 9 n UNION ALL SELECT 10 n UNION ALL SELECT 11 n UNION ALL SELECT 12 n UNION ALL SELECT 13 n UNION ALL SELECT 14 n UNION ALL SELECT 15 n UNION ALL SELECT 16 n) n
  ON n.n <= 1 + LENGTH(d.allowed_values_text) - LENGTH(REPLACE(d.allowed_values_text,'\n',''))
WHERE d.value_type='PRESET' AND d.allowed_values_text IS NOT NULL AND TRIM(d.allowed_values_text)<>''
  AND TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(d.allowed_values_text,'\n',n.n),'\n',-1))<>'';

-- 2. Effect set_values (option effects).
INSERT IGNORE INTO tmp_preset_vocabulary(plot_id,effect_scope,target_code,value_text)
SELECT t.plot_id,e.effect_scope,e.target_code,TRIM(e.set_value)
FROM plot_option_effects e
JOIN plot_decision_template_options o ON o.template_option_id=e.template_option_id
JOIN plot_decision_templates t ON t.template_id=o.template_id
WHERE e.set_value IS NOT NULL AND TRIM(e.set_value)<>'';

-- 3. Effect set_values (dynamic opportunity effects).
INSERT IGNORE INTO tmp_preset_vocabulary(plot_id,effect_scope,target_code,value_text)
SELECT o.plot_id,e.effect_scope,e.target_code,TRIM(e.set_value)
FROM plot_dynamic_option_effects e
JOIN plot_dynamic_opportunity_options x ON x.dynamic_option_id=e.dynamic_option_id
JOIN plot_dynamic_opportunities o ON o.dynamic_opportunity_id=x.dynamic_opportunity_id
WHERE e.set_value IS NOT NULL AND TRIM(e.set_value)<>'';

-- 4. Dynamic opportunity conditions, EQ/NEQ.
INSERT IGNORE INTO tmp_preset_vocabulary(plot_id,effect_scope,target_code,value_text)
SELECT o.plot_id,c.source_type,c.source_key,TRIM(c.compare_value)
FROM plot_dynamic_opportunity_conditions c
JOIN plot_dynamic_opportunities o ON o.dynamic_opportunity_id=c.dynamic_opportunity_id
WHERE c.operator IN ('EQ','NEQ') AND c.compare_value IS NOT NULL AND TRIM(c.compare_value)<>'';

-- 5. Dynamic opportunity conditions, IN/NOT_IN (split on commas).
INSERT IGNORE INTO tmp_preset_vocabulary(plot_id,effect_scope,target_code,value_text)
SELECT o.plot_id,c.source_type,c.source_key,
  TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(c.compare_value,',',n.n),',',-1))
FROM plot_dynamic_opportunity_conditions c
JOIN plot_dynamic_opportunities o ON o.dynamic_opportunity_id=c.dynamic_opportunity_id
JOIN (SELECT 1 n UNION ALL SELECT 2 n UNION ALL SELECT 3 n UNION ALL SELECT 4 n UNION ALL SELECT 5 n UNION ALL SELECT 6 n UNION ALL SELECT 7 n UNION ALL SELECT 8 n UNION ALL SELECT 9 n UNION ALL SELECT 10 n UNION ALL SELECT 11 n UNION ALL SELECT 12 n UNION ALL SELECT 13 n UNION ALL SELECT 14 n UNION ALL SELECT 15 n UNION ALL SELECT 16 n) n
  ON n.n <= 1 + LENGTH(c.compare_value) - LENGTH(REPLACE(c.compare_value,',',''))
WHERE c.operator IN ('IN','NOT_IN') AND c.compare_value IS NOT NULL AND TRIM(c.compare_value)<>''
  AND TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(c.compare_value,',',n.n),',',-1))<>'';

-- 6. Ending conditions, EQ/NEQ (attached to every PRESET definition with that code for the plot).
INSERT IGNORE INTO tmp_preset_vocabulary(plot_id,effect_scope,target_code,value_text)
SELECT d.plot_id,d.effect_scope,d.target_code,TRIM(c.compare_value)
FROM plot_resolution_conditions c
JOIN plot_resolution_fragments f ON f.resolution_fragment_id=c.resolution_fragment_id
JOIN plot_state_definitions d ON d.plot_id=f.plot_id AND d.target_code=c.state_key AND d.value_type='PRESET'
WHERE c.operator IN ('EQ','NEQ') AND c.compare_value IS NOT NULL AND TRIM(c.compare_value)<>'';

-- 7. Ending conditions, IN/NOT_IN (split on commas).
INSERT IGNORE INTO tmp_preset_vocabulary(plot_id,effect_scope,target_code,value_text)
SELECT d.plot_id,d.effect_scope,d.target_code,
  TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(c.compare_value,',',n.n),',',-1))
FROM plot_resolution_conditions c
JOIN plot_resolution_fragments f ON f.resolution_fragment_id=c.resolution_fragment_id
JOIN plot_state_definitions d ON d.plot_id=f.plot_id AND d.target_code=c.state_key AND d.value_type='PRESET'
JOIN (SELECT 1 n UNION ALL SELECT 2 n UNION ALL SELECT 3 n UNION ALL SELECT 4 n UNION ALL SELECT 5 n UNION ALL SELECT 6 n UNION ALL SELECT 7 n UNION ALL SELECT 8 n UNION ALL SELECT 9 n UNION ALL SELECT 10 n UNION ALL SELECT 11 n UNION ALL SELECT 12 n UNION ALL SELECT 13 n UNION ALL SELECT 14 n UNION ALL SELECT 15 n UNION ALL SELECT 16 n) n
  ON n.n <= 1 + LENGTH(c.compare_value) - LENGTH(REPLACE(c.compare_value,',',''))
WHERE c.operator IN ('IN','NOT_IN') AND c.compare_value IS NOT NULL AND TRIM(c.compare_value)<>''
  AND TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(c.compare_value,',',n.n),',',-1))<>'';

-- 8. Write the rebuilt vocabulary back.
UPDATE plot_state_definitions d
SET d.allowed_values_text=(
  SELECT GROUP_CONCAT(v.value_text ORDER BY v.value_text SEPARATOR '\n')
  FROM tmp_preset_vocabulary v
  WHERE v.plot_id=d.plot_id AND v.effect_scope=d.effect_scope AND v.target_code=d.target_code
)
WHERE d.value_type='PRESET'
  AND EXISTS (SELECT 1 FROM tmp_preset_vocabulary v WHERE v.plot_id=d.plot_id AND v.effect_scope=d.effect_scope AND v.target_code=d.target_code);

DROP TEMPORARY TABLE tmp_preset_vocabulary;

INSERT IGNORE INTO schema_migrations(migration) VALUES('135_rebuild_preset_vocabulary.sql');
