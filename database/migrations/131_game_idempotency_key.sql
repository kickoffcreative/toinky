-- Task 6 (2026-10-26 audit): idempotency key for lobby creation. A client
-- retry (double-submit, network retry) that resends the same key reuses the
-- existing lobby instead of creating a duplicate simulation + game. NULL =
-- no key supplied (legacy callers); NULLs never conflict under a UNIQUE key.
-- Rollback: drop the unique key and the column.
ALTER TABLE games ADD COLUMN idempotency_key VARCHAR(64) NULL, ADD UNIQUE KEY uniq_game_idempotency (idempotency_key);
INSERT IGNORE INTO schema_migrations(migration) VALUES('131_game_idempotency_key.sql');
