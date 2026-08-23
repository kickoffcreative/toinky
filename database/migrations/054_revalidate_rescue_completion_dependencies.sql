-- Revalidate the full rescue prerequisites when the Bellweather climax transition completes.
-- TriggeredTransitionService is generic; all story-specific dependencies remain in plot data.

SET @plot=(SELECT plot_id FROM plots WHERE code='CONCEALED_FAILURE' LIMIT 1);
SET @transition=(SELECT transition_id
  FROM plot_triggered_transitions
  WHERE plot_id=@plot AND transition_code='RESCUE_COMPLETE_AT_CLIMAX'
  LIMIT 1);

INSERT INTO plot_triggered_transition_conditions
(transition_id,state_key,operator,compare_value,sort_order)
SELECT @transition,'GUEST_HELPERS','EQ','2',3
WHERE @transition IS NOT NULL
  AND NOT EXISTS(
    SELECT 1
    FROM plot_triggered_transition_conditions
    WHERE transition_id=@transition AND state_key='GUEST_HELPERS'
  );

INSERT INTO plot_triggered_transition_conditions
(transition_id,state_key,operator,compare_value,sort_order)
SELECT @transition,'WEST_ROUTE','EQ','USABLE',4
WHERE @transition IS NOT NULL
  AND NOT EXISTS(
    SELECT 1
    FROM plot_triggered_transition_conditions
    WHERE transition_id=@transition AND state_key='WEST_ROUTE'
  );

INSERT IGNORE INTO schema_migrations(migration)
VALUES('054_revalidate_rescue_completion_dependencies.sql');
