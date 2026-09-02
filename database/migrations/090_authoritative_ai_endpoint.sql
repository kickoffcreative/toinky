INSERT INTO ai_reasoner_settings(setting_key,setting_value)
VALUES
  ('provider','ollama'),
  ('ollama_endpoint','https://endurable-cleft-wildlife.ngrok-free.dev'),
  ('ollama_model','qwen3:8b'),
  ('timeout_seconds','45')
ON DUPLICATE KEY UPDATE setting_value = CASE
  WHEN setting_key='provider' AND (setting_value IS NULL OR setting_value='' OR LOWER(setting_value)='deterministic') THEN 'ollama'
  WHEN setting_key='ollama_endpoint' AND (setting_value IS NULL OR setting_value='' OR setting_value IN ('http://127.0.0.1:11434','http://localhost:11434','127.0.0.1:11434','localhost:11434')) THEN 'https://endurable-cleft-wildlife.ngrok-free.dev'
  ELSE setting_value
END;
