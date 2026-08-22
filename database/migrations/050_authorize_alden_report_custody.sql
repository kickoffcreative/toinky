-- Authorize the report custody transition used when Alden publicly produces his copied report.
SET @plot=(SELECT plot_id FROM plots WHERE code='CONCEALED_FAILURE' LIMIT 1);
SET @report=(SELECT plot_entity_id FROM plot_entities
  WHERE plot_id=@plot AND entity_code='FAILED_REPORT' LIMIT 1);
SET @holder=(SELECT property_id FROM plot_entity_properties
  WHERE plot_entity_id=@report AND property_code='holder' LIMIT 1);

INSERT IGNORE INTO plot_property_transitions(property_id,from_value,to_value,active) VALUES
(@holder,'NONE','ALDEN',1),
(@holder,'NONE','FARAH',1),
(@holder,'ALDEN','FARAH',1),
(@holder,'FARAH','ALDEN',1),
(@holder,'NIKO','ALDEN',1);

-- Farah's escape concerns her separate records case when Alden or Niko controls the copied report.
UPDATE plot_decision_template_options o
JOIN plot_decision_templates t ON t.template_id=o.template_id
SET o.rationale_text='Keeping the evacuation moving may save lives, but Farah can still escape with the inspection records she controls.'
WHERE t.plot_id=@plot
  AND t.event_code='REAR_EXIT'
  AND t.role_code='NIKO'
  AND o.option_code='LET_FARAH_GO';

UPDATE plot_resolution_fragments
SET fragment_text='Farah disappears down the flooded service path with her remaining records case beneath her coat. By the time anyone follows, the mountain and rain have erased her footprints.'
WHERE plot_id=@plot
  AND section_code='FARAH'
  AND priority=20;

INSERT IGNORE INTO schema_migrations(migration)
VALUES('050_authorize_alden_report_custody.sql');
