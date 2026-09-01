-- Per-user Inky companion preferences. These settings are available to every
-- authenticated user and are intentionally independent of administrator roles.

CREATE TABLE IF NOT EXISTS user_inky_preferences (
  user_id BIGINT UNSIGNED NOT NULL,
  enabled TINYINT(1) NOT NULL DEFAULT 1,
  size_code ENUM('SMALL','STANDARD','LARGE') NOT NULL DEFAULT 'STANDARD',
  dock_position ENUM('AUTO','LEFT','RIGHT') NOT NULL DEFAULT 'AUTO',
  anchor_position ENUM('LOWER_LEFT','MIDDLE_LEFT','LOWER_RIGHT','MIDDLE_RIGHT') NOT NULL DEFAULT 'LOWER_LEFT',
  sounds_enabled TINYINT(1) NOT NULL DEFAULT 1,
  animation_level ENUM('FULL','REDUCED','OFF') NOT NULL DEFAULT 'FULL',
  proactive_help ENUM('SUGGESTIONS','CLICK_ONLY') NOT NULL DEFAULT 'CLICK_ONLY',
  updated_at_utc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY(user_id),
  CONSTRAINT fk_inky_preferences_user FOREIGN KEY(user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO schema_migrations(migration)
VALUES('083_inky_user_preferences.sql');

