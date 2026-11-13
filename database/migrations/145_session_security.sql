CREATE TABLE user_sessions (
 user_session_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 session_hash CHAR(64) NOT NULL UNIQUE,
 user_id BIGINT UNSIGNED NOT NULL,
 created_at_utc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 last_seen_at_utc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 absolute_expires_at_utc DATETIME NOT NULL,
 revoked_at_utc DATETIME NULL,
 mfa_verified_at_utc DATETIME NULL,
 password_confirmed_at_utc DATETIME NULL,
 user_agent VARCHAR(190) NOT NULL DEFAULT '',
 INDEX session_owner(user_id,revoked_at_utc),
 INDEX session_expiry(absolute_expires_at_utc),
 FOREIGN KEY(user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_totp_credentials (
 user_id BIGINT UNSIGNED PRIMARY KEY,
 encrypted_secret TEXT NOT NULL,
 enabled_at_utc DATETIME NULL,
 enrollment_expires_at_utc DATETIME NULL,
 last_used_step BIGINT UNSIGNED NULL,
 recovery_hashes_json TEXT NULL,
 FOREIGN KEY(user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE user_two_factor_codes ADD COLUMN email_hash CHAR(64) NULL;

INSERT IGNORE INTO schema_migrations(migration) VALUES('145_session_security.sql');
