-- 122: Writers choose which platform host durations their story offers.
-- No rows for a story = every active platform duration is allowed (the
-- long-standing behaviour). Once a writer saves a choice in the studio,
-- the rows are the exact allow-list; the story's own Duration is always
-- added on save so the tested configuration stays hostable.

CREATE TABLE IF NOT EXISTS plot_allowed_durations (
  plot_id INT NOT NULL,
  duration_seconds INT UNSIGNED NOT NULL,
  PRIMARY KEY (plot_id, duration_seconds),
  KEY idx_plot_allowed_duration (duration_seconds)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO schema_migrations(migration)
VALUES('122_plot_allowed_durations.sql');
