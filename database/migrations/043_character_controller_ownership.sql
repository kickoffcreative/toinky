-- Generic per-character controller ownership for browser simulations.
ALTER TABLE characters
  ADD COLUMN IF NOT EXISTS controller_type ENUM('HUMAN','AI') NOT NULL DEFAULT 'HUMAN' AFTER actor_type;

UPDATE characters SET controller_type='AI' WHERE actor_type='NPC' AND controller_type='HUMAN';

INSERT IGNORE INTO schema_migrations(migration)
VALUES('043_character_controller_ownership.sql');
