-- Activate the approved Lantern character as the second selectable Inky type.

UPDATE inky_characters
SET label_text='Lantern',
    description_text='Lantern Inky companion.',
    artwork_path='assets/inky/lantern/source/lantern_master_v1.png',
    selectable=1,
    active=1
WHERE character_code='INKY_DIRECTION_D';

INSERT IGNORE INTO schema_migrations(migration)
VALUES('092_enable_lantern_inky.sql');
