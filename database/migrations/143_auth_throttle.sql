CREATE TABLE auth_throttle (
 throttle_key CHAR(64) PRIMARY KEY,
 action VARCHAR(64) NOT NULL,
 window_start_utc DATETIME NOT NULL,
 attempts INT UNSIGNED NOT NULL DEFAULT 0,
 locked_until_utc DATETIME NULL,
 penalty_level TINYINT UNSIGNED NOT NULL DEFAULT 0,
 last_lockout_utc DATETIME NULL,
 INDEX throttle_prune (window_start_utc,locked_until_utc)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE user_two_factor_codes ADD COLUMN failed_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0;
INSERT IGNORE INTO schema_migrations(migration) VALUES('143_auth_throttle.sql');
