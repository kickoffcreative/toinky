-- Player profiles, verified email changes, and consent-based friendships.

SET @has_profile_image=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='profile_image_path');
SET @sql=IF(@has_profile_image=0,'ALTER TABLE users ADD COLUMN profile_image_path VARCHAR(500) NULL AFTER display_name','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS user_email_change_tokens (
  email_change_token_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  new_email VARCHAR(255) NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at_utc DATETIME NOT NULL,
  verified_at_utc DATETIME NULL,
  created_at_utc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_email_change_token(token_hash),
  KEY idx_email_change_user(user_id,verified_at_utc,expires_at_utc),
  CONSTRAINT fk_email_change_user FOREIGN KEY(user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_friendships (
  friendship_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_low_id BIGINT UNSIGNED NOT NULL,
  user_high_id BIGINT UNSIGNED NOT NULL,
  requested_by_user_id BIGINT UNSIGNED NOT NULL,
  status ENUM('PENDING','ACCEPTED','DECLINED') NOT NULL DEFAULT 'PENDING',
  created_at_utc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  responded_at_utc DATETIME NULL,
  UNIQUE KEY uq_friend_pair(user_low_id,user_high_id),
  KEY idx_friend_requested(requested_by_user_id,status),
  CONSTRAINT fk_friend_low FOREIGN KEY(user_low_id) REFERENCES users(user_id) ON DELETE CASCADE,
  CONSTRAINT fk_friend_high FOREIGN KEY(user_high_id) REFERENCES users(user_id) ON DELETE CASCADE,
  CONSTRAINT fk_friend_requester FOREIGN KEY(requested_by_user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_friend_labels (
  friendship_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  label_text VARCHAR(80) NULL,
  updated_at_utc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY(friendship_id,user_id),
  CONSTRAINT fk_friend_label_friendship FOREIGN KEY(friendship_id) REFERENCES user_friendships(friendship_id) ON DELETE CASCADE,
  CONSTRAINT fk_friend_label_user FOREIGN KEY(user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO schema_migrations(migration) VALUES('072_profiles_friends_and_media.sql');
