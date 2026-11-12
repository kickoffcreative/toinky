-- Previously displayed pending links cannot prove mailbox ownership. Reissue them.
ALTER TABLE users ADD COLUMN session_version INT UNSIGNED NOT NULL DEFAULT 1;
ALTER TABLE user_invitations ADD COLUMN delivered_at_utc DATETIME NULL;
ALTER TABLE game_invitations ADD COLUMN delivered_at_utc DATETIME NULL;
ALTER TABLE password_reset_tokens ADD COLUMN delivered_at_utc DATETIME NULL;
ALTER TABLE user_email_change_tokens ADD COLUMN delivered_at_utc DATETIME NULL;

-- Keep revocation effective even if older application code is restored.
UPDATE user_invitations SET expires_at_utc=UTC_TIMESTAMP() WHERE accepted_at_utc IS NULL;
UPDATE game_invitations SET expires_at_utc=UTC_TIMESTAMP() WHERE accepted_at_utc IS NULL;
UPDATE password_reset_tokens SET expires_at_utc=UTC_TIMESTAMP() WHERE used_at_utc IS NULL;
UPDATE user_email_change_tokens SET expires_at_utc=UTC_TIMESTAMP() WHERE verified_at_utc IS NULL;

CREATE TABLE user_email_verification_tokens (
 verification_token_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 email_hash CHAR(64) NOT NULL,
 token_hash CHAR(64) NOT NULL UNIQUE,
 expires_at_utc DATETIME NOT NULL,
 delivered_at_utc DATETIME NULL,
 used_at_utc DATETIME NULL,
 created_at_utc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX verification_user (user_id),
 FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE account_security_audit (
 audit_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 actor_user_id BIGINT UNSIGNED NOT NULL,
 target_user_id BIGINT UNSIGNED NOT NULL,
 event_code VARCHAR(64) NOT NULL,
 outcome VARCHAR(24) NOT NULL,
 correlation_id VARCHAR(64) NOT NULL,
 created_at_utc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX security_target (target_user_id,created_at_utc)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO schema_migrations(migration) VALUES('142_account_link_security.sql');
