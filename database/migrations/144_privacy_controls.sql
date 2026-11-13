ALTER TABLE users
 ADD COLUMN terms_version VARCHAR(40) NULL,
 ADD COLUMN privacy_version VARCHAR(40) NULL,
 ADD COLUMN policy_accepted_at_utc DATETIME NULL,
 ADD COLUMN privacy_erasure_hold_until_utc DATETIME NULL,
 ADD COLUMN privacy_erasure_hold_note VARCHAR(190) NULL,
 ADD COLUMN system_identity_key VARCHAR(40) NULL UNIQUE;

-- One disabled, non-personal identity preserves shared fiction after erasure.
INSERT INTO users(email,password_hash,display_name,status,system_identity_key)
VALUES('privacy-anonymous@toinky.invalid','!','Deleted player','DISABLED','PRIVACY_ANONYMOUS');

CREATE TABLE user_policy_acceptances (
 policy_acceptance_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 terms_version VARCHAR(40) NOT NULL,
 privacy_version VARCHAR(40) NOT NULL,
 accepted_at_utc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
 INDEX policy_owner (user_id,accepted_at_utc)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_email_history (
 email_history_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 email VARCHAR(190) NOT NULL,
 valid_from_utc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 valid_until_utc DATETIME NULL,
 INDEX email_owner_history(user_id,email,valid_from_utc),
 FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Legacy address acquisition dates are unknown. Never assume an address
-- belonged to its current owner since account creation.
INSERT INTO user_email_history(user_id,email) SELECT user_id,email FROM users WHERE system_identity_key IS NULL;

ALTER TABLE ai_reasoner_interactions
 ADD COLUMN actor_user_id BIGINT UNSIGNED NULL,
 ADD COLUMN actor_attribution_version TINYINT UNSIGNED NOT NULL DEFAULT 0,
 ADD INDEX ai_privacy_actor (actor_user_id,request_started_at_utc),
 ADD FOREIGN KEY (actor_user_id) REFERENCES users(user_id) ON DELETE SET NULL;

INSERT IGNORE INTO schema_migrations(migration) VALUES('144_privacy_controls.sql');
