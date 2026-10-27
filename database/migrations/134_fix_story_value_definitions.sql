-- Repair the Story Framework value definitions imported by migration 077.
-- 077 derived each PRESET definition's allowed values only from effect
-- set_values, never from condition compare_values or starting states, so
-- the story immersion audit flags legitimate conditions as invalid:
--  * CROWN_HOLDER (Empty Throne): 'NONE' is the starting state (nobody
--    holds the crown) and is used by dozens of conditions, but no effect
--    ever sets it, so the import produced allowed values 'ELIAN,ROWAN'.
--  * GUEST_HELPERS (Bellweather): a count of helpers (0-2). Effects set
--    '2' only, so the import produced allowed values '2' only, and the
--    PRESET type rejects the GTE/LT comparisons the story uses.
-- The fixes below only widen validation to values the story already uses;
-- they change no story logic. Helpers max out at 2 (Leila plus one more),
-- so GTE '2' is exactly EQ '2' and LT '2' is exactly IN '0,1'.

-- 1. Crown holder: admit the starting state.
INSERT INTO plot_state_definitions(plot_id,effect_scope,target_code,label_text,value_type,allowed_values_text,description,sort_order)
SELECT plot_id,'WORLD_STATE','CROWN_HOLDER','Crown Holder','PRESET','ELIAN\nNONE\nROWAN','Crown holder; NONE means nobody holds the crown yet.',100
FROM plots WHERE code='EMPTY_THRONE'
ON DUPLICATE KEY UPDATE value_type=VALUES(value_type),allowed_values_text=VALUES(allowed_values_text);

-- 2. Guest helpers: admit the full count range.
INSERT INTO plot_state_definitions(plot_id,effect_scope,target_code,label_text,value_type,allowed_values_text,description,sort_order)
SELECT plot_id,'WORLD_STATE','GUEST_HELPERS','Guest Helpers','PRESET','0\n1\n2','Number of helpers with the injured guest.',100
FROM plots WHERE code='CONCEALED_FAILURE'
ON DUPLICATE KEY UPDATE value_type=VALUES(value_type),allowed_values_text=VALUES(allowed_values_text);

-- 3. Rewrite the numeric-style comparisons as preset comparisons.
--    Evacuation options require two helpers.
UPDATE plot_option_conditions
SET operator='EQ'
WHERE state_key='GUEST_HELPERS' AND operator='GTE' AND compare_value='2';

--    Rescue-climax opportunities require two helpers.
UPDATE plot_dynamic_opportunity_conditions
SET operator='EQ'
WHERE source_type='WORLD_STATE' AND source_key='GUEST_HELPERS' AND operator='GTE' AND compare_value='2';

--    The evacuated-without-helpers integrity rule fires when fewer than
--    two helpers are present.
UPDATE plot_integrity_rule_conditions
SET operator='IN',compare_value='0,1'
WHERE source_key='GUEST_HELPERS' AND operator='LT' AND compare_value='2';

INSERT IGNORE INTO schema_migrations(migration) VALUES('134_fix_story_value_definitions.sql');
