-- Keep Farah's climax location consistent with choosing to remain and deny the accusation.
SET @plot=(SELECT plot_id FROM plots WHERE code='CONCEALED_FAILURE' LIMIT 1);
SET @stay=(SELECT o.template_option_id
  FROM plot_decision_template_options o
  JOIN plot_decision_templates t ON t.template_id=o.template_id
  WHERE t.plot_id=@plot AND t.event_code='REAR_EXIT' AND t.role_code='FARAH'
    AND o.option_code='STAY_AND_DENY'
  LIMIT 1);

UPDATE plot_decision_template_options
SET label='Return to the lobby and call the accusation panic.',
    result_text='You turn back toward the lobby and face the room. Your voice remains calm even as fewer people meet your eyes.'
WHERE template_option_id=@stay;

INSERT INTO plot_option_effects
(template_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order)
SELECT @stay,'LOCATION','LOCATION',NULL,'Lobby',
       'Farah abandons the open exit and returns to the lobby to continue her denial.',20
WHERE @stay IS NOT NULL
  AND NOT EXISTS(
    SELECT 1 FROM plot_option_effects
    WHERE template_option_id=@stay AND effect_scope='LOCATION'
  );

INSERT IGNORE INTO schema_migrations(migration)
VALUES('051_align_farah_stay_location.sql');
