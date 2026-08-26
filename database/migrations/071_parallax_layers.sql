-- Database-managed homepage parallax layers.

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

INSERT INTO site_parallax_layers(label_text,image_path,mime_type,width_px,height_px,movement_percent,sort_order)
SELECT seed.label_text,seed.image_path,'image/png',1920,1080,seed.movement_percent,seed.sort_order
FROM (
  SELECT 'Parchment sky and sunset' label_text,'assets/parallax/toinky-bg-sky-sun.png' image_path,0.00 movement_percent,10 sort_order
  UNION ALL SELECT 'Far story worlds','assets/parallax/toinky-bg-far-worlds.png',8.00,20
  UNION ALL SELECT 'Middle story worlds','assets/parallax/toinky-bg-middle-worlds.png',16.00,30
  UNION ALL SELECT 'River and valley','assets/parallax/toinky-bg-river-valley.png',24.00,40
  UNION ALL SELECT 'Prince and eighties heroine','assets/parallax/toinky-characters-prince-eighties.png',30.00,50
  UNION ALL SELECT 'Manananggal and rapper','assets/parallax/toinky-characters-manananggal-rapper.png',34.00,60
  UNION ALL SELECT 'Steampunk robot','assets/parallax/toinky-character-robot.png',44.00,70
  UNION ALL SELECT 'Reporter and leprechaun','assets/parallax/toinky-characters-reporter-leprechaun.png',47.00,80
) seed
WHERE NOT EXISTS (SELECT 1 FROM site_parallax_layers LIMIT 1);

INSERT IGNORE INTO schema_migrations(migration)
VALUES('071_parallax_layers.sql');
