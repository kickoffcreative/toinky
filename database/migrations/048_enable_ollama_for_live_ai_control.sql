-- Make browser AI Control use the configured local language model.
-- Deterministic reasoning remains the automatic fallback when Ollama is unavailable.
INSERT INTO ai_reasoner_settings(setting_key,setting_value) VALUES
('provider','ollama'),
('fallback_provider','deterministic')
ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value);

INSERT IGNORE INTO schema_migrations(migration)
VALUES('048_enable_ollama_for_live_ai_control.sql');
