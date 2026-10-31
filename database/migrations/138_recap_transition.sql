-- 138: Recap transition line on world events — an optional authored easing
-- line shown at the start of the event in the play-page story recap, instead
-- of the automatically composed transition. Lets writers repair the jumps
-- where event prose changes place without a recorded move (e.g. Whispers
-- 00:03 Tree Hut doorway). Display-only: read live from plot_world_events
-- by (plot_id, event_code) at render time, so it also repairs recaps of
-- already-finished games; it never changes deciding content.
ALTER TABLE plot_world_events ADD COLUMN recap_transition TEXT NULL DEFAULT NULL AFTER event_text;

INSERT IGNORE INTO schema_migrations(migration) VALUES('138_recap_transition.sql');
