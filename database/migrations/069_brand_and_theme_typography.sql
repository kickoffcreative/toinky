-- Complete typography controls for site branding and genre themes.

ALTER TABLE genre_themes
  ADD COLUMN title_font VARCHAR(180) NOT NULL DEFAULT 'Georgia, Times New Roman, serif' AFTER heading_color,
  ADD COLUMN title_color VARCHAR(9) NOT NULL DEFAULT '#3d345f' AFTER title_font,
  ADD COLUMN title_size VARCHAR(20) NOT NULL DEFAULT '38px' AFTER title_color,
  ADD COLUMN title_weight VARCHAR(3) NOT NULL DEFAULT '700' AFTER title_size,
  ADD COLUMN title_decoration VARCHAR(20) NOT NULL DEFAULT 'none' AFTER title_weight,
  ADD COLUMN heading_size VARCHAR(20) NOT NULL DEFAULT '24px' AFTER heading_font,
  ADD COLUMN heading_weight VARCHAR(3) NOT NULL DEFAULT '700' AFTER heading_size,
  ADD COLUMN heading_decoration VARCHAR(20) NOT NULL DEFAULT 'none' AFTER heading_weight,
  ADD COLUMN body_size VARCHAR(20) NOT NULL DEFAULT '16px' AFTER body_font,
  ADD COLUMN body_weight VARCHAR(3) NOT NULL DEFAULT '400' AFTER body_size,
  ADD COLUMN body_decoration VARCHAR(20) NOT NULL DEFAULT 'none' AFTER body_weight,
  ADD COLUMN meta_font VARCHAR(180) NOT NULL DEFAULT 'Inter, system-ui, sans-serif' AFTER body_decoration,
  ADD COLUMN meta_color VARCHAR(9) NOT NULL DEFAULT '#6b6c78' AFTER meta_font,
  ADD COLUMN meta_size VARCHAR(20) NOT NULL DEFAULT '12px' AFTER meta_color,
  ADD COLUMN meta_weight VARCHAR(3) NOT NULL DEFAULT '600' AFTER meta_size,
  ADD COLUMN meta_decoration VARCHAR(20) NOT NULL DEFAULT 'none' AFTER meta_weight,
  ADD COLUMN button_font VARCHAR(180) NOT NULL DEFAULT 'Inter, system-ui, sans-serif' AFTER meta_decoration,
  ADD COLUMN button_color VARCHAR(9) NOT NULL DEFAULT '#ffffff' AFTER button_font,
  ADD COLUMN button_size VARCHAR(20) NOT NULL DEFAULT '13px' AFTER button_color,
  ADD COLUMN button_weight VARCHAR(3) NOT NULL DEFAULT '800' AFTER button_size,
  ADD COLUMN button_decoration VARCHAR(20) NOT NULL DEFAULT 'none' AFTER button_weight;

UPDATE genre_themes
SET title_font=heading_font,
    title_color=heading_color,
    meta_font=body_font,
    button_font=body_font;

INSERT IGNORE INTO schema_migrations(migration)
VALUES('069_brand_and_theme_typography.sql');
