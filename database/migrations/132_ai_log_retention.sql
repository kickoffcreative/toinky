-- Task 17 (2026-10-26 audit): stop storing raw AI payloads going forward.
-- AiInteractionLogger::storeRaw() reads store_raw_payloads from
-- ai_reasoner_settings (default '1'). Setting it to '0' stops growth in
-- the three MEDIUMTEXT payload columns while keeping parsed_response_json,
-- metrics, and the aiUsage() aggregates. Historical rows are handled by
-- tools/prune_ai_logs.php (payload nulling), never by this migration.
-- Rollback: set store_raw_payloads back to '1'.
INSERT INTO ai_reasoner_settings(setting_key,setting_value) VALUES('store_raw_payloads','0') ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value);
INSERT IGNORE INTO schema_migrations(migration) VALUES('132_ai_log_retention.sql');
