-- Empty Throne story-data repair: preserve the established archive-to-Rowan
-- evidence transfer after explicit LETTER_HOLDER transitions were introduced.
-- Generic PHP continues to validate database-defined transitions.

SET @plot=(
  SELECT plot_id
  FROM plots
  WHERE code='EMPTY_THRONE'
  LIMIT 1
);

SET @property=(
  SELECT p.property_id
  FROM plot_state_aliases a
  JOIN plot_entities e
    ON e.plot_id=a.plot_id
   AND e.entity_code=a.entity_code
  JOIN plot_entity_properties p
    ON p.plot_entity_id=e.plot_entity_id
   AND p.property_code=a.property_code
  WHERE a.plot_id=@plot
    AND a.state_key='LETTER_HOLDER'
  LIMIT 1
);

INSERT IGNORE INTO plot_property_transitions
(property_id,from_value,to_value,active)
SELECT @property,'ARCHIVE','ROWAN',1
WHERE @property IS NOT NULL;

INSERT IGNORE INTO schema_migrations(migration)
VALUES('033_restore_letter_holder_transition.sql');
