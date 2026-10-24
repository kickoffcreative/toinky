-- 125: Host commerce — occasion purchases and the append-only occasion ledger.
-- Occasions are granted (free first, purchase, admin), held while a game sits
-- in the lobby (RESERVE), spent when the game starts (CONSUME), and returned
-- when an unstarted lobby is deleted (RELEASE). Balances are always derived
-- from the ledger, never stored as a counter.
CREATE TABLE IF NOT EXISTS host_purchases (
  purchase_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  stripe_checkout_session_id VARCHAR(255) NULL,
  stripe_payment_intent_id VARCHAR(255) NULL,
  bundle_key VARCHAR(40) NOT NULL DEFAULT 'single',
  occasions_granted INT NOT NULL DEFAULT 0,
  amount_cents INT NOT NULL DEFAULT 0,
  currency CHAR(3) NOT NULL DEFAULT 'USD',
  status VARCHAR(20) NOT NULL DEFAULT 'PENDING',
  created_at_utc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  paid_at_utc DATETIME NULL,
  KEY idx_host_purchases_user (user_id),
  KEY idx_host_purchases_session (stripe_checkout_session_id),
  CONSTRAINT fk_host_purchases_user FOREIGN KEY(user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS host_occasion_ledger (
  ledger_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  entry_type VARCHAR(24) NOT NULL,
  occasions INT NOT NULL DEFAULT 0,
  game_id BIGINT UNSIGNED NULL,
  purchase_id BIGINT UNSIGNED NULL,
  note VARCHAR(255) NOT NULL DEFAULT '',
  created_by_user_id BIGINT UNSIGNED NULL,
  created_at_utc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  free_first_key BIGINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN entry_type='FREE_FIRST' THEN user_id ELSE NULL END) STORED,
  UNIQUE KEY uniq_ledger_free_first (free_first_key),
  UNIQUE KEY uniq_ledger_game_type (game_id, entry_type),
  KEY idx_ledger_user (user_id),
  KEY idx_ledger_game (game_id),
  CONSTRAINT fk_ledger_user FOREIGN KEY(user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  CONSTRAINT fk_ledger_game FOREIGN KEY(game_id) REFERENCES games(game_id) ON DELETE SET NULL,
  CONSTRAINT fk_ledger_purchase FOREIGN KEY(purchase_id) REFERENCES host_purchases(purchase_id) ON DELETE SET NULL,
  CONSTRAINT fk_ledger_actor FOREIGN KEY(created_by_user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO platform_settings(setting_key,setting_value) VALUES
('COMMERCE_ENABLED','0'),
('COMMERCE_FREE_FIRST','1'),
('COMMERCE_BUNDLES','{"currency":"USD","bundles":[{"key":"single","occasions":1,"price_cents":500},{"key":"trio","occasions":3,"price_cents":1200},{"key":"five","occasions":5,"price_cents":1900}]}');

INSERT IGNORE INTO schema_migrations(migration) VALUES('125_host_commerce.sql');
