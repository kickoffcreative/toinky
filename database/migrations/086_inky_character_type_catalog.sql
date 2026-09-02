-- Correct the initial Inky character mapping and keep character labels generic.

INSERT INTO inky_characters(character_code,label_text,description_text,sort_order,selectable,active)
VALUES
('INKY_OCTOPUS','Octopus','The default octopus-inspired Inky companion from approved character direction F.',10,1,1),
('INKY_DIRECTION_D','Lantern','Future lantern-inspired Inky companion option from approved character direction D.',30,0,1),
('INKY_SQUID','Squid','Future squid-inspired Inky companion option from approved character direction G.',90,0,1)
ON DUPLICATE KEY UPDATE
  label_text=VALUES(label_text),
  description_text=VALUES(description_text),
  sort_order=VALUES(sort_order),
  selectable=VALUES(selectable),
  active=VALUES(active);

UPDATE user_inky_preferences
SET character_code='INKY_OCTOPUS'
WHERE character_code IN('INKY_CLASSIC','INKY_DIRECTION_F','INKY_TOINKY');

UPDATE inky_characters
SET selectable=0,active=0
WHERE character_code IN('INKY_CLASSIC','INKY_DIRECTION_F','INKY_TOINKY','INKY_SQUINKY');

ALTER TABLE user_inky_preferences
  ALTER character_code SET DEFAULT 'INKY_OCTOPUS';

INSERT IGNORE INTO schema_migrations(migration)
VALUES('086_inky_character_type_catalog.sql');
