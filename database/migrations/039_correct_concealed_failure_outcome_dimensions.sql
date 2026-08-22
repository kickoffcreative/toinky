-- Correct Bellweather outcome dimensions to its authoritative world-state keys.
SET @plot=(SELECT plot_id FROM plots WHERE code='CONCEALED_FAILURE' LIMIT 1);

UPDATE plot_outcome_dimensions
SET dimension_code='EVIDENCE_STATE',label_text='EVIDENCE',source_key='FAILED_REPORT_STATE',sort_order=10
WHERE plot_id=@plot AND dimension_code='FAILURE';

UPDATE plot_outcome_dimensions
SET dimension_code='REPORT_HOLDER',label_text='REPORT HOLDER',source_key='REPORT_HOLDER',sort_order=20
WHERE plot_id=@plot AND dimension_code='EVACUATION';

UPDATE plot_outcome_dimensions
SET label_text='GUEST',source_key='GUEST_STATUS',sort_order=30
WHERE plot_id=@plot AND dimension_code='GUESTS';

UPDATE plot_outcome_dimensions
SET dimension_code='FARAH_EXIT',label_text='FARAH EXIT',source_key='FARAH_EXIT_STATUS',sort_order=40
WHERE plot_id=@plot AND dimension_code='OVERRIDE';

INSERT IGNORE INTO schema_migrations(migration)
VALUES('039_correct_concealed_failure_outcome_dimensions.sql');
