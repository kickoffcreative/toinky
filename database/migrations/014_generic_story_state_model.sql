-- Reusable story-state model.
-- Story plots provide concrete labels/content, while the engine works with a small vocabulary:
-- WORLD FACTORS: SAFETY, INFORMATION, CONTROL, TENSION, COHESION
-- CHARACTER STATE: location, goal, fear, stats, knowledge, relationships
-- OBJECTIVE STATE: plot-specific finite state machines stored in simulation_state.

CREATE TABLE IF NOT EXISTS story_factor_types (
  factor_type_code VARCHAR(40) PRIMARY KEY,
  label VARCHAR(120) NOT NULL,
  description TEXT NOT NULL,
  character_effect TEXT NOT NULL,
  world_effect TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO story_factor_types(factor_type_code,label,description,character_effect,world_effect) VALUES
('SAFETY','Safety / Stability','Physical, operational or environmental stability.','Changes urgency, risk-taking, rescue/protection priorities and fear pressure.','Controls danger, loss, access, evacuation and whether hazardous actions remain viable.'),
('INFORMATION','Information / Evidence','How much reliable, usable truth is available.','Changes beliefs, confidence, goals, accusations and willingness to act.','Controls what can be proved, revealed, hidden, shared or resolved.'),
('CONTROL','Control / Power','How much practical influence an actor or side has over events.','Changes confidence, self-preservation, resistance and available leverage.','Controls access, authority, possession, movement and who can impose outcomes.'),
('TENSION','Tension / Suspicion','Social or dramatic pressure caused by uncertainty, conflict or exposure.','Changes caution, fear, confrontation and investigative behavior.','Controls escalation, conflict intensity, attention and likelihood of confrontation.'),
('COHESION','Cohesion / Trust','Ability of people to coordinate and rely on one another.','Changes cooperation, loyalty, disclosure and willingness to help.','Controls coordination, group effectiveness, fragmentation and collective outcomes.')
ON DUPLICATE KEY UPDATE label=VALUES(label),description=VALUES(description),character_effect=VALUES(character_effect),world_effect=VALUES(world_effect);

ALTER TABLE plot_factors ADD COLUMN IF NOT EXISTS factor_type_code VARCHAR(40) NULL AFTER factor_code;

CREATE TABLE IF NOT EXISTS character_state_history (
  character_state_history_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  simulation_id BIGINT UNSIGNED NOT NULL,
  character_id BIGINT UNSIGNED NOT NULL,
  event_id BIGINT UNSIGNED NULL,
  event_second INT UNSIGNED NOT NULL,
  state_type ENUM('GOAL','FEAR') NOT NULL,
  old_value TEXT NULL,
  new_value TEXT NOT NULL,
  reason_text TEXT NULL,
  created_at_utc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_character_state_history(character_id,event_second,character_state_history_id),
  CONSTRAINT fk_csh_sim FOREIGN KEY(simulation_id) REFERENCES simulations(simulation_id) ON DELETE CASCADE,
  CONSTRAINT fk_csh_char FOREIGN KEY(character_id) REFERENCES characters(character_id) ON DELETE CASCADE,
  CONSTRAINT fk_csh_event FOREIGN KEY(event_id) REFERENCES simulation_events(event_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Map the Bellweather labels to reusable semantic factor types. Future stories should do the same
-- even when their visible labels are entirely different.
SET @plot_id=(SELECT plot_id FROM plots WHERE code='CONCEALED_FAILURE' LIMIT 1);
UPDATE plot_factors SET factor_type_code='SAFETY' WHERE plot_id=@plot_id AND factor_code='SAFETY';
UPDATE plot_factors SET factor_type_code='INFORMATION' WHERE plot_id=@plot_id AND factor_code='EVIDENCE';
UPDATE plot_factors SET factor_type_code='CONTROL' WHERE plot_id=@plot_id AND factor_code='CONTROL';
UPDATE plot_factors SET factor_type_code='TENSION' WHERE plot_id=@plot_id AND factor_code='SUSPICION';
UPDATE plot_factors SET factor_type_code='COHESION' WHERE plot_id=@plot_id AND factor_code='TRUST';

-- Fix the rescue progression exposed by the latest simulations: once a second helper exists,
-- the west route is usable and the guest is stabilized, movement is a deterministic valid next step.
SET @t=(SELECT template_id FROM plot_decision_templates WHERE plot_id=@plot_id AND event_code='PRESSURE_RISE' AND role_code='LEILA' LIMIT 1);
SET @move=(SELECT template_option_id FROM plot_decision_template_options WHERE template_id=@t AND option_code='BEGIN_EVACUATION' LIMIT 1);
SET @move=COALESCE(@move,(SELECT template_option_id FROM plot_decision_template_options WHERE template_id=@t AND label='Begin moving the injured guest if enough help is present.' LIMIT 1));
UPDATE plot_decision_template_options SET npc_weight=100 WHERE template_option_id=@move;
INSERT INTO plot_option_conditions(template_option_id,state_key,operator,compare_value,reason_text,sort_order)
SELECT @move,'GUEST_HELPERS','EQ','2','Two committed helpers are available.',30 FROM DUAL
WHERE @move IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_option_conditions WHERE template_option_id=@move AND state_key='GUEST_HELPERS');
INSERT INTO plot_option_conditions(template_option_id,state_key,operator,compare_value,reason_text,sort_order)
SELECT @move,'WEST_ROUTE','EQ','USABLE','The evacuation route must be confirmed usable.',31 FROM DUAL
WHERE @move IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_option_conditions WHERE template_option_id=@move AND state_key='WEST_ROUTE');
INSERT INTO plot_option_conditions(template_option_id,state_key,operator,compare_value,reason_text,sort_order)
SELECT @move,'GUEST_STATUS','EQ','STABILIZED','The guest must be stabilized before movement begins.',32 FROM DUAL
WHERE @move IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_option_conditions WHERE template_option_id=@move AND state_key='GUEST_STATUS');

-- Ensure route-preparation actions actually leave the guest in the stabilized state needed by the generic progression.
SET @rt=(SELECT template_id FROM plot_decision_templates WHERE plot_id=@plot_id AND event_code='REPORT_MISSING' AND role_code='LEILA' LIMIT 1);
SET @ro=(SELECT template_option_id FROM plot_decision_template_options WHERE template_id=@rt AND option_code='USE_SECOND_HELPER' LIMIT 1);
SET @ro=COALESCE(@ro,(SELECT template_option_id FROM plot_decision_template_options WHERE template_id=@rt AND label='Use the available second helper to verify the west route and prepare evacuation.' LIMIT 1));
INSERT INTO plot_option_effects(template_option_id,effect_scope,target_code,set_value,detail_text,sort_order)
SELECT @ro,'WORLD_STATE','GUEST_STATUS','STABILIZED','The injured guest remains stabilized while the route is prepared.',40 FROM DUAL
WHERE @ro IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_option_effects WHERE template_option_id=@ro AND effect_scope='WORLD_STATE' AND target_code='GUEST_STATUS');

INSERT IGNORE INTO schema_migrations(migration) VALUES('014_generic_story_state_model.sql');