-- Resumable Inky authoring state. Story entities remain generic database-authored data.

CREATE TABLE IF NOT EXISTS inky_story_guide_drafts (
  guide_draft_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  plot_id BIGINT UNSIGNED NOT NULL,
  author_user_id BIGINT UNSIGNED NOT NULL,
  state_json LONGTEXT NOT NULL,
  created_at_utc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at_utc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_inky_guide_plot(plot_id),
  KEY idx_inky_guide_author(author_user_id,updated_at_utc),
  CONSTRAINT fk_inky_guide_plot FOREIGN KEY(plot_id) REFERENCES plots(plot_id) ON DELETE CASCADE,
  CONSTRAINT fk_inky_guide_author FOREIGN KEY(author_user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
