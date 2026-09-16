-- Database-managed homepage parallax layers. Layers are intentionally user-managed.

CREATE TABLE IF NOT EXISTS site_parallax_layers (
  parallax_layer_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  label_text VARCHAR(120) NOT NULL,
  image_path VARCHAR(500) NOT NULL,
  mime_type VARCHAR(80) NOT NULL,
  width_px INT UNSIGNED NOT NULL DEFAULT 1920,
  height_px INT UNSIGNED NOT NULL DEFAULT 1080,
  file_size_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
  offset_left_px INT NOT NULL DEFAULT 0,
  offset_right_px INT NOT NULL DEFAULT 0,
  offset_top_px INT NOT NULL DEFAULT 0,
  offset_bottom_px INT NOT NULL DEFAULT 0,
  movement_percent DECIMAL(6,2) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 100,
  updated_by_user_id BIGINT UNSIGNED NULL,
  created_at_utc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at_utc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (parallax_layer_id),
  KEY idx_site_parallax_order (sort_order,parallax_layer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO schema_migrations(migration)
VALUES('071_parallax_layers.sql');
