-- Engine quality pass: one autonomous actor cannot chain independent decisions at the same instant,
-- and final resolution text must respect authoritative entity state.

ALTER TABLE plots
  ADD COLUMN IF NOT EXISTS npc_action_cooldown_seconds SMALLINT UNSIGNED NOT NULL DEFAULT 15 AFTER recommended_stats_per_role;

UPDATE plots SET npc_action_cooldown_seconds=15 WHERE npc_action_cooldown_seconds=0;

SET @plot=(SELECT plot_id FROM plots WHERE code='EMPTY_THRONE' LIMIT 1);

-- Authoritative ending: destruction outranks possession/custody.
INSERT INTO plot_resolution_fragments(plot_id,section_code,section_order,priority,fragment_text,active)
SELECT @plot,'LETTER',20,5,'The original abdication letter has been destroyed. Its destruction now fuels suspicion about the legitimacy of Rowan’s claim.',1
WHERE NOT EXISTS(
  SELECT 1 FROM plot_resolution_fragments
  WHERE plot_id=@plot AND section_code='LETTER' AND priority=5
);
SET @f=(SELECT resolution_fragment_id FROM plot_resolution_fragments WHERE plot_id=@plot AND section_code='LETTER' AND priority=5 LIMIT 1);
DELETE FROM plot_resolution_conditions WHERE resolution_fragment_id=@f;
INSERT INTO plot_resolution_conditions(resolution_fragment_id,state_key,operator,compare_value,sort_order)
VALUES(@f,'LETTER_STATUS','EQ','DESTROYED',1);

-- Tighten the generic identified-custody fragment so it can never describe destroyed evidence as surviving.
SET @f=(SELECT resolution_fragment_id FROM plot_resolution_fragments WHERE plot_id=@plot AND section_code='LETTER' AND priority=20 LIMIT 1);
INSERT INTO plot_resolution_conditions(resolution_fragment_id,state_key,operator,compare_value,sort_order)
SELECT @f,'LETTER_STATUS','NOT_IN','DESTROYED',2
WHERE @f IS NOT NULL
  AND NOT EXISTS(
    SELECT 1 FROM plot_resolution_conditions
    WHERE resolution_fragment_id=@f AND state_key='LETTER_STATUS' AND operator='NOT_IN'
  );

INSERT IGNORE INTO schema_migrations(migration) VALUES('029_action_arbitration_and_resolution_integrity.sql');
