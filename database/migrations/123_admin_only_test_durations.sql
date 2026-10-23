-- 123: The 3, 5 and 15 minute lengths are admin test durations, not public
-- host options. Deactivate them in the catalog so they leave every
-- writer-facing list; admins still see them in the host form and can
-- still create games at those lengths. Reversible: set active=1 to undo.

UPDATE platform_game_durations SET active=0 WHERE duration_seconds IN (180,300,900);

INSERT IGNORE INTO schema_migrations(migration)
VALUES('123_admin_only_test_durations.sql');
