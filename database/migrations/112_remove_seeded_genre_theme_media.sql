-- Remove old seeded genre-theme image records so themes depend only on chosen media.

UPDATE genre_themes t
LEFT JOIN writer_media_assets bg ON bg.media_id=t.background_media_id
LEFT JOIN writer_media_assets dv ON dv.media_id=t.divider_media_id
LEFT JOIN writer_media_assets cd ON cd.media_id=t.card_media_id
SET
  t.background_media_id=IF(t.background_image_path LIKE 'public/assets/genre-themes/%' OR bg.file_path LIKE 'public/assets/genre-themes/%',NULL,t.background_media_id),
  t.background_image_path=IF(t.background_image_path LIKE 'public/assets/genre-themes/%' OR bg.file_path LIKE 'public/assets/genre-themes/%',NULL,t.background_image_path),
  t.divider_media_id=IF(t.divider_image_path LIKE 'public/assets/genre-themes/%' OR dv.file_path LIKE 'public/assets/genre-themes/%',NULL,t.divider_media_id),
  t.divider_image_path=IF(t.divider_image_path LIKE 'public/assets/genre-themes/%' OR dv.file_path LIKE 'public/assets/genre-themes/%',NULL,t.divider_image_path),
  t.card_media_id=IF(t.card_image_path LIKE 'public/assets/genre-themes/%' OR cd.file_path LIKE 'public/assets/genre-themes/%',NULL,t.card_media_id),
  t.card_image_path=IF(t.card_image_path LIKE 'public/assets/genre-themes/%' OR cd.file_path LIKE 'public/assets/genre-themes/%',NULL,t.card_image_path);

DELETE FROM writer_media_assets
WHERE file_path LIKE 'public/assets/genre-themes/%';

INSERT IGNORE INTO schema_migrations(migration)
VALUES('112_remove_seeded_genre_theme_media.sql');
