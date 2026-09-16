-- Remove old seeded homepage parallax layers. New layers are uploaded through Media.

DELETE FROM site_parallax_layers
WHERE image_path LIKE 'assets/parallax/%'
   OR image_path LIKE 'public/assets/parallax/%';

INSERT IGNORE INTO schema_migrations(migration)
VALUES('113_remove_seeded_parallax_layers.sql');
