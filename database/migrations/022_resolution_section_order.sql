ALTER TABLE plot_resolution_fragments ADD COLUMN IF NOT EXISTS section_order INT NOT NULL DEFAULT 100 AFTER section_code;

SET @plot_id=(SELECT plot_id FROM plots WHERE code='CONCEALED_FAILURE' LIMIT 1);
UPDATE plot_resolution_fragments SET section_order=10 WHERE plot_id=@plot_id AND section_code='PEOPLE';
UPDATE plot_resolution_fragments SET section_order=20 WHERE plot_id=@plot_id AND section_code='TRUTH';
UPDATE plot_resolution_fragments SET section_order=30 WHERE plot_id=@plot_id AND section_code='FARAH';

INSERT IGNORE INTO schema_migrations(migration) VALUES('022_resolution_section_order.sql');