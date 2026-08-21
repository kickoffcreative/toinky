-- Local/open-source AI reasoner audit foundation.
-- Every request/response is retained so reasoning quality, transfer size, latency,
-- token usage and later hardware telemetry can be analyzed per transaction.

CREATE TABLE IF NOT EXISTS ai_reasoner_interactions (
  ai_interaction_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  simulation_id BIGINT UNSIGNED NULL,
  character_id BIGINT UNSIGNED NULL,
  decision_id BIGINT UNSIGNED NULL,
  purpose VARCHAR(100) NOT NULL DEFAULT 'NPC_OPTION_SELECTION',
  provider VARCHAR(60) NOT NULL,
  model_name VARCHAR(190) NOT NULL,
  endpoint VARCHAR(500) NULL,
  request_started_at_utc DATETIME(6) NOT NULL,
  request_completed_at_utc DATETIME(6) NULL,
  success TINYINT(1) NOT NULL DEFAULT 0,
  fallback_used TINYINT(1) NOT NULL DEFAULT 0,
  http_status SMALLINT UNSIGNED NULL,
  latency_ms DECIMAL(12,3) NULL,
  request_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
  response_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
  prompt_chars BIGINT UNSIGNED NOT NULL DEFAULT 0,
  response_chars BIGINT UNSIGNED NOT NULL DEFAULT 0,
  prompt_eval_count BIGINT UNSIGNED NULL,
  eval_count BIGINT UNSIGNED NULL,
  total_token_count BIGINT UNSIGNED NULL,
  total_duration_ns BIGINT UNSIGNED NULL,
  load_duration_ns BIGINT UNSIGNED NULL,
  prompt_eval_duration_ns BIGINT UNSIGNED NULL,
  eval_duration_ns BIGINT UNSIGNED NULL,
  selected_action_code VARCHAR(190) NULL,
  confidence DECIMAL(5,2) NULL,
  reason_text TEXT NULL,
  request_json MEDIUMTEXT NULL,
  response_json MEDIUMTEXT NULL,
  parsed_response_json MEDIUMTEXT NULL,
  error_text TEXT NULL,
  host_cpu_percent DECIMAL(6,2) NULL,
  host_ram_used_mb DECIMAL(12,2) NULL,
  host_gpu_percent DECIMAL(6,2) NULL,
  host_vram_used_mb DECIMAL(12,2) NULL,
  host_gpu_power_watts DECIMAL(10,3) NULL,
  created_at_utc TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ai_interaction_simulation(simulation_id,ai_interaction_id),
  KEY idx_ai_interaction_decision(decision_id),
  KEY idx_ai_interaction_model(model_name,created_at_utc),
  KEY idx_ai_interaction_success(success,created_at_utc),
  CONSTRAINT fk_ai_interaction_simulation FOREIGN KEY(simulation_id) REFERENCES simulations(simulation_id) ON DELETE SET NULL,
  CONSTRAINT fk_ai_interaction_character FOREIGN KEY(character_id) REFERENCES characters(character_id) ON DELETE SET NULL,
  CONSTRAINT fk_ai_interaction_decision FOREIGN KEY(decision_id) REFERENCES decisions(decision_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_reasoner_settings (
  setting_key VARCHAR(120) PRIMARY KEY,
  setting_value TEXT NULL,
  updated_at_utc TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO ai_reasoner_settings(setting_key,setting_value) VALUES
('provider','deterministic'),
('ollama_endpoint','http://127.0.0.1:11434'),
('ollama_model','qwen3:8b'),
('timeout_seconds','20'),
('fallback_provider','deterministic'),
('store_raw_payloads','1')
ON DUPLICATE KEY UPDATE setting_value=setting_value;

INSERT IGNORE INTO schema_migrations(migration) VALUES('032_local_ai_reasoner_and_usage_audit.sql');
