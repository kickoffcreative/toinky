-- Final continuity cleanup after a human-controlled Farah browser playthrough.
-- All Bellweather-specific behavior and prose remain database-configured.

SET @plot=(SELECT plot_id FROM plots WHERE code='CONCEALED_FAILURE' LIMIT 1);

-- Medical condition and evacuation location are intentionally independent.
-- Retire the obsolete rule that treated an evacuated but still-injured guest as contradictory.
UPDATE plot_integrity_rules
SET active=0
WHERE plot_id=@plot
  AND rule_code='EVACUATED_GUEST_STILL_DESCRIBED_BY_LEGACY_STATE';

-- Farah pressures Alden after Niko may already hold the report; do not imply Alden still grips it.
UPDATE plot_decision_template_options o
JOIN plot_decision_templates t ON t.template_id=o.template_id
SET o.result_text='You speak softly enough that only Alden hears. He looks toward the staff, then toward whoever now controls the report.'
WHERE t.plot_id=@plot
  AND t.event_code='PRESSURE_RISE'
  AND t.role_code='FARAH'
  AND o.option_code='PRESSURE_ALDEN';

-- World beats establish hazards and opportunities without forcing actions or locations
-- that a human-controlled character did not choose.
UPDATE plot_world_events
SET event_text='Emergency lights stain the lobby red. Muddy water pushes beneath the east doors while the radios answer in bursts of static. Leila calls for another pair of hands. The failed report remains at the center of the argument, and Farah keeps the records case close while watching the rear hall.'
WHERE plot_id=@plot AND event_code='PRESSURE_RISE';

UPDATE plot_world_events
SET event_text='The rear door slams open under a gust of rain. Beyond it, the service path is disappearing beneath brown water. The open threshold offers one last dangerous route out. Behind the confrontation, Leila shouts that the east stairwell is moving again.'
WHERE plot_id=@plot AND event_code='REAR_EXIT';

-- Niko can block access to the open route without the world event pre-positioning Farah.
UPDATE plot_decision_template_options o
JOIN plot_decision_templates t ON t.template_id=o.template_id
SET o.label='Take the doorway and order Farah to surrender the records case.',
    o.result_text='You put yourself between Farah and the open route. She says your name like a warning, but the case remains inside.'
WHERE t.plot_id=@plot
  AND t.event_code='REAR_EXIT'
  AND t.role_code='NIKO'
  AND o.option_code='BLOCK_FARAH';

-- Kellan's intervention likewise responds to the opening rather than asserting an unchosen escape attempt.
UPDATE plot_decision_template_options o
JOIN plot_decision_templates t ON t.template_id=o.template_id
SET o.label='Call out the alarm-bypass question before Farah can use the open exit.',
    o.result_text='Your words stop the room from treating the open door as an ordinary escape. Every face turns toward Farah and the records case.'
WHERE t.plot_id=@plot
  AND t.event_code='REAR_EXIT'
  AND t.role_code='KELLAN'
  AND o.option_code='BLOCK_WITH_INFO';

INSERT IGNORE INTO schema_migrations(migration)
VALUES('047_remove_residual_bellweather_state_assumptions.sql');
