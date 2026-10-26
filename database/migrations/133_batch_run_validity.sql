-- Task 15 part (3) (2026-10-26 audit): per-run validity annotation for
-- review batches. A run is valid evidence only when its frozen result shows
-- a non-empty cast with at least one player character, a completed
-- simulation, no cast/resolution failure warning, and -- for organic runs --
-- at least one recorded decision. Validity is computed from the frozen
-- result at record time (StorySimulationBatchService::assessValidity) and
-- stored here alongside result_json.
-- is_valid NULL = recorded before this change; summarize() assesses such
-- legacy rows at read time from their frozen result_json. 1 = valid,
-- 0 = invalid with invalid_reason naming why.
-- Historical rows are never rewritten: result_json and the run's COMPLETE
-- status stay exactly as recorded; the annotation only flags them.
-- Rollback: drop the two columns.
ALTER TABLE simulation_review_batch_runs ADD COLUMN is_valid TINYINT(1) NULL, ADD COLUMN invalid_reason VARCHAR(255) NULL;
INSERT IGNORE INTO schema_migrations(migration) VALUES('133_batch_run_validity.sql');
