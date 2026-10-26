-- Task 5 Phase 1 (2026-10-26 audit): pin the content version at simulation creation.
-- simulations.plot_version_id records which published/draft snapshot a game or
-- review run was created from; content_checksum is SHA-256 of that version's
-- snapshot workspace (content only — the snapshot's plot row is volatile
-- through publish and is excluded). NULL = legacy/unpinned (created before
-- pinning; runs live).
-- plots.published_plot_version_id is the version hosted games must use; it is
-- set by the publish action and backfilled here to each active story's latest
-- PUBLISHED version. Rollback: drop the two FKs and the three columns.
ALTER TABLE simulations ADD COLUMN plot_version_id BIGINT UNSIGNED NULL,
  ADD COLUMN content_checksum CHAR(64) NULL,
  ADD COLUMN drift_checked_at_utc DATETIME NULL,
  ADD CONSTRAINT fk_sim_version FOREIGN KEY (plot_version_id) REFERENCES plot_versions(plot_version_id) ON DELETE SET NULL;
ALTER TABLE plots ADD COLUMN published_plot_version_id BIGINT UNSIGNED NULL,
  ADD CONSTRAINT fk_plot_pub_version FOREIGN KEY (published_plot_version_id) REFERENCES plot_versions(plot_version_id) ON DELETE SET NULL;
UPDATE plots p SET p.published_plot_version_id=(
  SELECT v.plot_version_id FROM plot_versions v
  WHERE v.plot_id=p.plot_id AND v.workflow_status='PUBLISHED'
  ORDER BY v.version_no DESC LIMIT 1
) WHERE p.status='active';
INSERT IGNORE INTO schema_migrations(migration) VALUES('130_version_pinning.sql');
