-- Keep internal artwork-direction language out of user-facing Inky settings.

UPDATE inky_characters
SET description_text='Future lantern-inspired Inky companion.'
WHERE character_code='INKY_DIRECTION_D';

INSERT IGNORE INTO schema_migrations(migration)
VALUES('091_clean_inky_character_copy.sql');
