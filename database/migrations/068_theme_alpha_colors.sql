-- Allow opaque and alpha-enabled hexadecimal colors in genre themes.
-- Supported values: #RRGGBB and #RRGGBBAA.

ALTER TABLE genre_themes
  MODIFY COLUMN primary_color VARCHAR(9) NOT NULL DEFAULT '#3d345f',
  MODIFY COLUMN secondary_color VARCHAR(9) NOT NULL DEFAULT '#6d5aae',
  MODIFY COLUMN accent_color VARCHAR(9) NOT NULL DEFAULT '#b8913e',
  MODIFY COLUMN background_color VARCHAR(9) NOT NULL DEFAULT '#f2efe8',
  MODIFY COLUMN surface_color VARCHAR(9) NOT NULL DEFAULT '#ffffff',
  MODIFY COLUMN text_color VARCHAR(9) NOT NULL DEFAULT '#252739',
  MODIFY COLUMN heading_color VARCHAR(9) NOT NULL DEFAULT '#3d345f';

INSERT IGNORE INTO schema_migrations(migration)
VALUES('068_theme_alpha_colors.sql');
