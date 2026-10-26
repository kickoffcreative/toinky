-- 128: Super Admin accounts and two-factor sign-in codes.
-- Super Admin (is_super_admin=1) is the top-level administrator tier.
-- Rules: a Super Admin never needs occasion credits to host (CommerceService
-- already exempts administrators; a Super Admin is always an administrator),
-- a Super Admin account can never be deactivated or demoted, and a Super
-- Admin must pass a second-factor email code when signing in after two weeks
-- without a sign-in (last_login_at_utc older than 14 days).
ALTER TABLE users ADD COLUMN is_super_admin TINYINT(1) NOT NULL DEFAULT 0 AFTER is_admin;

-- goldwynlau@gmail.com is the Super Admin.
UPDATE users SET is_admin=1, is_super_admin=1 WHERE email='goldwynlau@gmail.com';

-- One-time sign-in codes for Super Admin second-factor verification.
-- A code is valid for 10 minutes and for a single successful verification.
CREATE TABLE IF NOT EXISTS user_two_factor_codes (
  user_two_factor_code_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  code_hash VARCHAR(64) NOT NULL,
  expires_at_utc DATETIME NOT NULL,
  used_at_utc DATETIME NULL DEFAULT NULL,
  created_at_utc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_two_factor_code_id),
  INDEX idx_two_factor_user_expires (user_id, expires_at_utc)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO schema_migrations(migration) VALUES('128_super_admin_two_factor.sql');
