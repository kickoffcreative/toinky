-- Deprecate obsolete plot-authoring recommendation fields without removing data.
-- These values are retained for backward compatibility and must not be used by new runtime features.

ALTER TABLE plots
  MODIFY COLUMN recommended_factor_max TINYINT UNSIGNED NOT NULL DEFAULT 4
    COMMENT 'DEPRECATED after Milestone 2: no runtime consumer; retained for backward compatibility',
  MODIFY COLUMN recommended_stats_per_role TINYINT UNSIGNED NOT NULL DEFAULT 2
    COMMENT 'DEPRECATED after Milestone 2: no runtime consumer; retained for backward compatibility';

INSERT IGNORE INTO schema_migrations(migration)
VALUES('067_deprecate_story_budget_hints.sql');
