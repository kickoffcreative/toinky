SET @has_deleted_at := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='games' AND column_name='deleted_at_utc');
SET @sql := IF(@has_deleted_at=0,'ALTER TABLE games ADD COLUMN deleted_at_utc DATETIME NULL, ADD COLUMN deleted_by_user_id BIGINT UNSIGNED NULL, ADD INDEX idx_games_visible (deleted_at_utc,status)','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE plot_roles pr
JOIN plots p ON p.plot_id=pr.plot_id
SET pr.simulation_active=1
WHERE p.code='EMPTY_THRONE' AND pr.role_code='ELIAN';

INSERT IGNORE INTO schema_migrations(migration) VALUES('074_lobby_deletion_and_elian.sql');
