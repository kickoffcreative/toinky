-- Generic account, multiplayer lobby, and game-history foundation.
-- Story rules remain database-authored; this migration adds platform ownership only.

CREATE TABLE IF NOT EXISTS users (
  user_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  display_name VARCHAR(120) NOT NULL,
  is_admin TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('ACTIVE','DISABLED') NOT NULL DEFAULT 'ACTIVE',
  email_verified_at_utc DATETIME NULL,
  last_login_at_utc DATETIME NULL,
  created_at_utc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at_utc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_users_email(email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_invitations (
  invitation_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL,
  token_hash CHAR(64) NOT NULL,
  invited_by_user_id BIGINT UNSIGNED NOT NULL,
  expires_at_utc DATETIME NOT NULL,
  accepted_at_utc DATETIME NULL,
  created_at_utc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_user_invitation_token(token_hash),
  KEY idx_user_invitation_email(email),
  CONSTRAINT fk_invitation_user FOREIGN KEY(invited_by_user_id) REFERENCES users(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_reset_tokens (
  password_reset_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at_utc DATETIME NOT NULL,
  used_at_utc DATETIME NULL,
  created_at_utc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_password_reset_token(token_hash),
  KEY idx_password_reset_user(user_id,expires_at_utc),
  CONSTRAINT fk_password_reset_user FOREIGN KEY(user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS games (
  game_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  simulation_id BIGINT UNSIGNED NOT NULL,
  host_user_id BIGINT UNSIGNED NOT NULL,
  join_code VARCHAR(12) NOT NULL,
  status ENUM('LOBBY','RUNNING','COMPLETE','CANCELLED') NOT NULL DEFAULT 'LOBBY',
  started_at_utc DATETIME NULL,
  completed_at_utc DATETIME NULL,
  created_at_utc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at_utc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_games_simulation(simulation_id),
  UNIQUE KEY uq_games_join_code(join_code),
  KEY idx_games_host(host_user_id,created_at_utc),
  KEY idx_games_status(status,updated_at_utc),
  CONSTRAINT fk_games_simulation FOREIGN KEY(simulation_id) REFERENCES simulations(simulation_id) ON DELETE CASCADE,
  CONSTRAINT fk_games_host FOREIGN KEY(host_user_id) REFERENCES users(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS game_players (
  game_player_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  game_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  character_id BIGINT UNSIGNED NULL,
  joined_at_utc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_seen_at_utc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_game_player(game_id,user_id),
  UNIQUE KEY uq_game_character(game_id,character_id),
  KEY idx_game_player_user(user_id,joined_at_utc),
  CONSTRAINT fk_game_player_game FOREIGN KEY(game_id) REFERENCES games(game_id) ON DELETE CASCADE,
  CONSTRAINT fk_game_player_user FOREIGN KEY(user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  CONSTRAINT fk_game_player_character FOREIGN KEY(character_id) REFERENCES characters(character_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS story_ratings (
  story_rating_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  game_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  rating TINYINT UNSIGNED NOT NULL,
  review_text TEXT NULL,
  created_at_utc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at_utc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_story_rating(game_id,user_id),
  KEY idx_story_rating_user(user_id,created_at_utc),
  CONSTRAINT fk_story_rating_game FOREIGN KEY(game_id) REFERENCES games(game_id) ON DELETE CASCADE,
  CONSTRAINT fk_story_rating_user FOREIGN KEY(user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO schema_migrations(migration)
VALUES('060_multiplayer_lobby_and_admin.sql');
