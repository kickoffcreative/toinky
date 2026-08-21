CREATE TABLE IF NOT EXISTS simulation_state (
  simulation_state_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  simulation_id BIGINT UNSIGNED NOT NULL,
  state_key VARCHAR(190) NOT NULL,
  state_value VARCHAR(500) NOT NULL,
  updated_at_second INT UNSIGNED NOT NULL DEFAULT 0,
  UNIQUE KEY uq_sim_state(simulation_id,state_key),
  CONSTRAINT fk_sstate_sim FOREIGN KEY(simulation_id) REFERENCES simulations(simulation_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE plot_option_effects MODIFY effect_scope ENUM('FACTOR','STAT','LOCATION','KNOWLEDGE','RELATIONSHIP','WORLD_STATE') NOT NULL;
UPDATE plot_option_effects SET effect_scope='WORLD_STATE' WHERE target_code='REPORT_HOLDER';
INSERT IGNORE INTO schema_migrations(migration) VALUES('002_world_state.sql');
