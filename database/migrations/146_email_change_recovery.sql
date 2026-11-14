-- Pending legacy changes have no old-mailbox binding; require a fresh request.
ALTER TABLE user_email_change_tokens
 ADD COLUMN old_email VARCHAR(255) NULL,
 ADD COLUMN undo_hash CHAR(64) NULL,
 ADD COLUMN undo_expires_at_utc DATETIME NULL,
 ADD COLUMN undo_delivered_at_utc DATETIME NULL,
 ADD COLUMN undone_at_utc DATETIME NULL,
 ADD UNIQUE KEY uq_email_change_undo(undo_hash);
UPDATE user_email_change_tokens SET expires_at_utc=UTC_TIMESTAMP() WHERE verified_at_utc IS NULL;
INSERT IGNORE INTO schema_migrations(migration) VALUES('146_email_change_recovery.sql');
