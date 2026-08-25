CREATE TABLE IF NOT EXISTS story_correction_imports (
  story_correction_import_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  plot_id BIGINT UNSIGNED NOT NULL,
  source_plot_version_id BIGINT UNSIGNED NOT NULL,
  result_plot_version_id BIGINT UNSIGNED NOT NULL,
  imported_by_user_id BIGINT UNSIGNED NOT NULL,
  original_filename VARCHAR(255) NOT NULL,
  file_type ENUM('JSON','CSV') NOT NULL,
  change_count INT UNSIGNED NOT NULL,
  package_json LONGTEXT NOT NULL,
  created_at_utc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_story_correction_plot(plot_id,created_at_utc),
  CONSTRAINT fk_story_correction_plot FOREIGN KEY(plot_id) REFERENCES plots(plot_id) ON DELETE CASCADE,
  CONSTRAINT fk_story_correction_source FOREIGN KEY(source_plot_version_id) REFERENCES plot_versions(plot_version_id) ON DELETE CASCADE,
  CONSTRAINT fk_story_correction_result FOREIGN KEY(result_plot_version_id) REFERENCES plot_versions(plot_version_id) ON DELETE CASCADE,
  CONSTRAINT fk_story_correction_user FOREIGN KEY(imported_by_user_id) REFERENCES users(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO schema_migrations(migration) VALUES('070_story_correction_imports.sql');
