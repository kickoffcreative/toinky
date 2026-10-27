-- Widen site_page_sections.layout_type to every layout PageContentService
-- allows (LAYOUTS). Migration 075 created the column with only 5 values, but
-- the service validates 12; saving a section with CONTAINER (or TWO_COLUMN,
-- FOUR_COLUMN, TEXT_TOP, CARDS_ONLY, TEXT_BULLETS, STORY_CARDS) failed with
-- "Data truncated for column 'layout_type'" under strict SQL mode (CI) and
-- would silently store '' on a lenient server. No existing rows change: the
-- stored values are all within the new set.
ALTER TABLE site_page_sections MODIFY COLUMN layout_type ENUM('IMAGE_RIGHT','IMAGE_LEFT','TWO_COLUMN','THREE_COLUMN','FOUR_COLUMN','SOCIALS','TEXT_CENTER','TEXT_TOP','CARDS_ONLY','TEXT_BULLETS','CONTAINER','STORY_CARDS') NOT NULL DEFAULT 'IMAGE_RIGHT';

INSERT IGNORE INTO schema_migrations(migration) VALUES('136_widen_section_layout_enum.sql');
