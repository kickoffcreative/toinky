-- Admin game deactivation: completed games can be deactivated (hidden from
-- player game lists) instead of deleted, keeping the record for audit.
ALTER TABLE games
  ADD COLUMN deactivated_at_utc DATETIME NULL,
  ADD COLUMN deactivated_by_user_id BIGINT UNSIGNED NULL;
