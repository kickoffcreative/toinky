-- Expandable Inky character catalog and per-user companion identity.

CREATE TABLE IF NOT EXISTS inky_characters (
  character_code VARCHAR(60) NOT NULL,
  label_text VARCHAR(120) NOT NULL,
  description_text VARCHAR(500) NULL,
  artwork_path VARCHAR(500) NULL,
  sort_order INT NOT NULL DEFAULT 100,
  selectable TINYINT(1) NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY(character_code),
  KEY idx_inky_characters_available(active,selectable,sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO inky_characters(character_code,label_text,description_text,sort_order,selectable,active)
VALUES('INKY_CLASSIC','Classic Inky','The original warm, curious Toinky co-writer.',10,1,1)
ON DUPLICATE KEY UPDATE label_text=VALUES(label_text),description_text=VALUES(description_text),sort_order=VALUES(sort_order),selectable=VALUES(selectable),active=VALUES(active);

ALTER TABLE user_inky_preferences
  ADD COLUMN companion_name_root VARCHAR(32) NULL AFTER enabled,
  ADD COLUMN character_code VARCHAR(60) NOT NULL DEFAULT 'INKY_CLASSIC' AFTER companion_name_root,
  ADD CONSTRAINT fk_inky_preferences_character FOREIGN KEY(character_code) REFERENCES inky_characters(character_code);

INSERT IGNORE INTO schema_migrations(migration)
VALUES('084_inky_character_identity.sql');
