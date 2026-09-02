-- Seed future Inky character catalog entries without enabling selection.

INSERT INTO inky_characters(character_code,label_text,description_text,sort_order,selectable,active)
VALUES
('INKY_DIRECTION_F','Inky Direction F','Approved initial Inky character direction F. Artwork and personality remain coming soon.',20,0,1),
('INKY_DIRECTION_D','Inky Direction D','Approved initial Inky character direction D. Artwork and personality remain coming soon.',30,0,1),
('INKY_OCTOPUS','Octopus Inky','Future cute octopus-inspired Inky companion option.',80,0,1),
('INKY_SQUID','Squid Inky','Future cute squid-inspired Inky companion option.',90,0,1)
ON DUPLICATE KEY UPDATE
  label_text=VALUES(label_text),
  description_text=VALUES(description_text),
  sort_order=VALUES(sort_order),
  selectable=VALUES(selectable),
  active=VALUES(active);

INSERT IGNORE INTO schema_migrations(migration)
VALUES('085_inky_future_character_catalog.sql');
