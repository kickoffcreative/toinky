SET @plot_id=(SELECT plot_id FROM plots WHERE code='CONCEALED_FAILURE' LIMIT 1);

-- Keep knowledge-based actions semantically valid even when Alden does not possess the physical report.
SET @t=(SELECT template_id FROM plot_decision_templates WHERE plot_id=@plot_id AND event_code='REPORT_MISSING' AND role_code='ALDEN' LIMIT 1);
UPDATE plot_decision_template_options
SET label='Explain exactly what the failed report said to Niko.',
    rationale_text='A credible insider is the best route to making Alden’s knowledge matter.',
    result_text='Alden brings Niko directly into the evidence dispute by explaining what he knows about the failed inspection.'
WHERE template_id=@t AND label='Show Niko the report or explain exactly what the failed copy said.';

SET @t=(SELECT template_id FROM plot_decision_templates WHERE plot_id=@plot_id AND event_code='PRESSURE_RISE' AND role_code='ALDEN' LIMIT 1);
UPDATE plot_decision_template_options
SET label='Find a second person willing to corroborate Alden’s account of the failed report.',
    rationale_text='A second informed person makes Alden’s account harder to dismiss.'
WHERE template_id=@t AND label='Find a second person willing to verify what the report says.';

INSERT IGNORE INTO schema_migrations(migration) VALUES('009_state_dependent_action_wording.sql');
