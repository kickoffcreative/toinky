-- Optional solo fast-forward keeps the real start timestamp intact.
ALTER TABLE games ADD COLUMN solo_time_offset_seconds INT UNSIGNED NOT NULL DEFAULT 0;

INSERT IGNORE INTO schema_migrations(migration) VALUES('141_solo_time_offset.sql');
