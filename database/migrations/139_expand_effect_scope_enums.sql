-- 139: Expand effect_scope ENUMs to match the scopes the save handlers
-- and the writer UI offer. plot_world_event_effects was created in
-- migration 040 with ENUM('WORLD_STATE','FACTOR') only, while
-- saveWorldEventEffect and the studio form accept LOCATION, STAT,
-- KNOWLEDGE, GOAL and FEAR too. On a non-strict server an out-of-range
-- ENUM value truncates silently to '' with no error, so a LOCATION
-- world-event effect (Whispers FINAL_STATE -> At Sea) saved with
-- "Event effect saved." yet reloaded as WORLD_STATE — confirmed live
-- with SHOW CREATE TABLE. The same gap existed in plot_option_effects
-- (missing WORLD_STATE, GOAL, FEAR) and plot_no_action_effects
-- (missing GOAL, FEAR). Appending values keeps every existing value's
-- ENUM index unchanged. This does not repair rows already truncated to
-- ''; those keep their intended scope only when re-saved in the studio.
ALTER TABLE plot_world_event_effects MODIFY effect_scope ENUM('WORLD_STATE','FACTOR','LOCATION','STAT','KNOWLEDGE','GOAL','FEAR') NOT NULL;
ALTER TABLE plot_option_effects MODIFY effect_scope ENUM('FACTOR','STAT','LOCATION','KNOWLEDGE','RELATIONSHIP','WORLD_STATE','GOAL','FEAR') NOT NULL;
ALTER TABLE plot_no_action_effects MODIFY effect_scope ENUM('FACTOR','STAT','LOCATION','KNOWLEDGE','WORLD_STATE','GOAL','FEAR') NOT NULL;

INSERT IGNORE INTO schema_migrations(migration) VALUES('139_expand_effect_scope_enums.sql');
