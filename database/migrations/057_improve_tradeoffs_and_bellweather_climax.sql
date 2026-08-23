-- Improve decision trade-offs and close Bellweather's earned rescue arc in plot data.
-- Generic PHP remains story-neutral: it evaluates these database-authored opportunities,
-- prerequisites, effects, focus weights, and option rationales for any plot.

SET @bell=(SELECT plot_id FROM plots WHERE code='CONCEALED_FAILURE' LIMIT 1);

-- A rescue that has already reached PREPARING or MOVING, has two helpers, and has a
-- usable west route earns one final Wren decision during the climax. The available
-- choices make the cost explicit: finish the individual rescue, shelter in place,
-- or leave the stretcher to warn the wider building.
INSERT INTO plot_dynamic_opportunities
(plot_id,opportunity_code,role_code,prompt_text,window_seconds,once_only,priority,active)
SELECT @bell,'WREN_FINAL_RESCUE_CHOICE','WREN',
       'The stretcher has reached the west corridor, but the floor moves again beneath your feet. Leila can finish the carry with you now. You could instead shelter the guest at the fire door, or leave the stretcher long enough to drive everyone else farther from the east wing.',
       25,1,4,1
WHERE @bell IS NOT NULL
ON DUPLICATE KEY UPDATE
  prompt_text=VALUES(prompt_text),
  window_seconds=VALUES(window_seconds),
  once_only=1,
  priority=VALUES(priority),
  active=1;

SET @wren_final=(SELECT dynamic_opportunity_id
  FROM plot_dynamic_opportunities
  WHERE plot_id=@bell AND opportunity_code='WREN_FINAL_RESCUE_CHOICE'
  LIMIT 1);

DELETE FROM plot_dynamic_opportunity_conditions
WHERE dynamic_opportunity_id=@wren_final;
INSERT INTO plot_dynamic_opportunity_conditions
(dynamic_opportunity_id,source_type,source_key,operator,compare_value,sort_order)
SELECT @wren_final,'PHASE',NULL,'EQ','CLIMAX',1 WHERE @wren_final IS NOT NULL
UNION ALL
SELECT @wren_final,'WORLD_STATE','GUEST_EVACUATION_STATUS','IN','PREPARING,MOVING',2 WHERE @wren_final IS NOT NULL
UNION ALL
SELECT @wren_final,'WORLD_STATE','GUEST_HELPERS','GTE','2',3 WHERE @wren_final IS NOT NULL
UNION ALL
SELECT @wren_final,'WORLD_STATE','WEST_ROUTE','EQ','USABLE',4 WHERE @wren_final IS NOT NULL
UNION ALL
SELECT @wren_final,'ACTION_COUNT',NULL,'GTE','4',5 WHERE @wren_final IS NOT NULL;

DELETE FROM plot_dynamic_opportunity_options
WHERE dynamic_opportunity_id=@wren_final;
INSERT INTO plot_dynamic_opportunity_options
(dynamic_opportunity_id,option_code,label,result_text,sort_order)
SELECT @wren_final,'COMPLETE_WEST_CARRY',
       'Stay on the stretcher and finish the carry to the west evacuation point.',
       'You keep the stretcher level while the corridor bucks beneath you. Leila counts the final steps, and together you carry the injured guest beyond the failing east wing.',1
WHERE @wren_final IS NOT NULL
UNION ALL
SELECT @wren_final,'SHELTER_AT_FIRE_DOOR',
       'Stop at the fire door and shelter the guest beneath its reinforced frame.',
       'You and Leila lower the stretcher beneath the reinforced doorway. The guest remains inside Bellweather, protected for the moment but still short of the west evacuation point.',2
WHERE @wren_final IS NOT NULL
UNION ALL
SELECT @wren_final,'BREAK_FOR_WIDER_WARNING',
       'Leave the stretcher with Leila and force everyone else farther west.',
       'You surrender your side of the stretcher and run toward the lobby, shouting until the remaining guests move. Behind you, Leila braces the injured guest alone beneath the fire door.',3
WHERE @wren_final IS NOT NULL;

UPDATE plot_dynamic_opportunity_options
SET rationale_text=CASE option_code
  WHEN 'COMPLETE_WEST_CARRY' THEN 'Completing the rescue fulfills Wren’s immediate duty, but costs the last chance to influence the evidence dispute or warn the wider room personally.'
  WHEN 'SHELTER_AT_FIRE_DOOR' THEN 'Shelter reduces the immediate exposure, but leaves the injured guest inside the resort and the evacuation unfinished.'
  WHEN 'BREAK_FOR_WIDER_WARNING' THEN 'A wider warning may protect more people, but abandons Leila and the injured guest at the most dangerous point of the carry.'
  ELSE rationale_text END
WHERE dynamic_opportunity_id=@wren_final;

SET @finish=(SELECT dynamic_option_id FROM plot_dynamic_opportunity_options
  WHERE dynamic_opportunity_id=@wren_final AND option_code='COMPLETE_WEST_CARRY' LIMIT 1);
INSERT INTO plot_dynamic_option_effects
(dynamic_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order)
SELECT @finish,'WORLD_STATE','GUEST_EVACUATION_STATUS',NULL,'EVACUATED',
       'The injured guest reaches the west evacuation point with both required helpers.',1
WHERE @finish IS NOT NULL
UNION ALL
SELECT @finish,'LOCATION','SELF',NULL,'West Evacuation Point',
       'Wren completes the carry beside Leila.',2
WHERE @finish IS NOT NULL
UNION ALL
SELECT @finish,'FACTOR','SAFETY',4,NULL,
       'Finishing the prepared rescue gets the injured guest beyond the failing east wing.',3
WHERE @finish IS NOT NULL;

SET @shelter=(SELECT dynamic_option_id FROM plot_dynamic_opportunity_options
  WHERE dynamic_opportunity_id=@wren_final AND option_code='SHELTER_AT_FIRE_DOOR' LIMIT 1);
INSERT INTO plot_dynamic_option_effects
(dynamic_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order)
SELECT @shelter,'WORLD_STATE','GUEST_EVACUATION_STATUS',NULL,'AT_RISK',
       'The guest is sheltered temporarily but remains inside the threatened building.',1
WHERE @shelter IS NOT NULL
UNION ALL
SELECT @shelter,'LOCATION','SELF',NULL,'West Corridor',
       'Wren remains with Leila and the sheltered guest.',2
WHERE @shelter IS NOT NULL
UNION ALL
SELECT @shelter,'FACTOR','SAFETY',1,NULL,
       'The reinforced doorway offers temporary protection without completing the evacuation.',3
WHERE @shelter IS NOT NULL;

SET @warning=(SELECT dynamic_option_id FROM plot_dynamic_opportunity_options
  WHERE dynamic_opportunity_id=@wren_final AND option_code='BREAK_FOR_WIDER_WARNING' LIMIT 1);
INSERT INTO plot_dynamic_option_effects
(dynamic_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order)
SELECT @warning,'WORLD_STATE','GUEST_EVACUATION_STATUS',NULL,'AT_RISK',
       'Without Wren’s hands, the injured guest cannot complete the carry and remains at risk.',1
WHERE @warning IS NOT NULL
UNION ALL
SELECT @warning,'LOCATION','SELF',NULL,'Lobby',
       'Wren leaves the stretcher to warn the wider building.',2
WHERE @warning IS NOT NULL
UNION ALL
SELECT @warning,'FACTOR','SAFETY',5,NULL,
       'Wren’s warning moves more people away from the east side, at the cost of the unfinished individual rescue.',3
WHERE @warning IS NOT NULL;

-- Make the new climax choice participate in the existing database focus profiles.
SET @documents=(SELECT focus_profile_id FROM plot_focus_profiles
  WHERE plot_id=@bell AND focus_code='DOCUMENTS' LIMIT 1);
SET @rescue=(SELECT focus_profile_id FROM plot_focus_profiles
  WHERE plot_id=@bell AND focus_code='RESCUE' LIMIT 1);
INSERT INTO plot_focus_option_weights
(focus_profile_id,event_code,role_code,option_code,focus_weight)
SELECT @documents,'WREN_FINAL_RESCUE_CHOICE','WREN','COMPLETE_WEST_CARRY',10 WHERE @documents IS NOT NULL
UNION ALL SELECT @documents,'WREN_FINAL_RESCUE_CHOICE','WREN','SHELTER_AT_FIRE_DOOR',10 WHERE @documents IS NOT NULL
UNION ALL SELECT @documents,'WREN_FINAL_RESCUE_CHOICE','WREN','BREAK_FOR_WIDER_WARNING',35 WHERE @documents IS NOT NULL
ON DUPLICATE KEY UPDATE focus_weight=VALUES(focus_weight);
INSERT INTO plot_focus_option_weights
(focus_profile_id,event_code,role_code,option_code,focus_weight)
SELECT @rescue,'WREN_FINAL_RESCUE_CHOICE','WREN','COMPLETE_WEST_CARRY',100 WHERE @rescue IS NOT NULL
UNION ALL SELECT @rescue,'WREN_FINAL_RESCUE_CHOICE','WREN','SHELTER_AT_FIRE_DOOR',75 WHERE @rescue IS NOT NULL
UNION ALL SELECT @rescue,'WREN_FINAL_RESCUE_CHOICE','WREN','BREAK_FOR_WIDER_WARNING',55 WHERE @rescue IS NOT NULL
ON DUPLICATE KEY UPDATE focus_weight=VALUES(focus_weight);

-- Empty Throne's choices remain unchanged, but their authored rationales now expose
-- genuine opportunity costs. This gives the generic reasoner balanced evidence instead
-- of making institutional coordination appear cost-free.
SET @throne=(SELECT plot_id FROM plots WHERE code='EMPTY_THRONE' LIMIT 1);

UPDATE plot_decision_template_options o
JOIN plot_decision_templates t ON t.template_id=o.template_id
SET o.rationale_text=CASE
  WHEN t.event_code='SEALED_ARCHIVE' AND t.role_code='ELIAN' AND o.option_code='SEEK_LETTER'
    THEN 'Documentary proof could legitimize Elian’s claim, but racing Rowan to the archive may turn a legal dispute into an immediate confrontation.'
  WHEN t.event_code='SEALED_ARCHIVE' AND t.role_code='ELIAN' AND o.option_code='BUILD_SUPPORT'
    THEN 'Quiet support can strengthen Elian without forcing the evidence dispute, but leaves Rowan time to control whatever the archive contains.'
  WHEN t.event_code='SEALED_ARCHIVE' AND t.role_code='ELIAN' AND o.option_code='BACK_ORDER'
    THEN 'Supporting delay protects peace and process, but may sacrifice Elian’s best chance to establish a claim before Rowan becomes political fact.'
  WHEN t.event_code='SEALED_ARCHIVE' AND t.role_code='MARA' AND o.option_code='DELAY_PROCLAMATION'
    THEN 'A formal delay protects legitimacy, but prolonged uncertainty can harden factions and disrupt the capital.'
  WHEN t.event_code='SEALED_ARCHIVE' AND t.role_code='MARA' AND o.option_code='SECURE_GRAIN'
    THEN 'Protecting supply routes serves Mara’s practical duty and lowers civilian risk, but leaves the succession dispute to less cautious actors.'
  WHEN t.event_code='SEALED_ARCHIVE' AND t.role_code='MARA' AND o.option_code='ASK_QUEEN'
    THEN 'The Queen may supply decisive private knowledge, but a secret inquiry delays public stabilization and may look like palace maneuvering.'
  WHEN t.event_code='SEALED_ARCHIVE' AND t.role_code='TOMAS' AND o.option_code='LOCK_ARCHIVE'
    THEN 'Neutral custody protects the disputed evidence, but committing guards to the archive leaves the council less protected during a volatile hour.'
  WHEN t.event_code='SEALED_ARCHIVE' AND t.role_code='TOMAS' AND o.option_code='GUARD_ROWAN'
    THEN 'Backing the presumed heir creates immediate command, but risks turning the guard into the instrument of an illegitimate succession.'
  WHEN t.event_code='SEALED_ARCHIVE' AND t.role_code='TOMAS' AND o.option_code='GUARD_COUNCIL'
    THEN 'Protecting the council preserves institutional neutrality, but leaves Rowan and the archive beyond direct guard control.'
  WHEN t.event_code='ROWAN_USE_LETTER_CONTROL' AND t.role_code='ROWAN' AND o.option_code='KEEP_CONTAINED'
    THEN 'Containment preserves the document and Rowan’s deniability, but leaves a recoverable threat that opponents may eventually reach.'
  WHEN t.event_code='ROWAN_USE_LETTER_CONTROL' AND t.role_code='ROWAN' AND o.option_code='CONTROLLED_REVIEW'
    THEN 'A narrow review may preserve Rowan’s legitimacy, but risks making the abdication authoritative in the very forum he permits.'
  WHEN t.event_code='ROWAN_USE_LETTER_CONTROL' AND t.role_code='ROWAN' AND o.option_code='DESTROY_LETTER'
    THEN 'Destruction removes the original threat, but makes concealment itself visible and gives opponents a basis for investigation.'
  WHEN t.event_code='ELIAN_BLOCKED_CORONATION_CLIMAX' AND t.role_code='ELIAN' AND o.option_code='REQUEST_LAWFUL_CORONATION'
    THEN 'Council recognition can convert Elian’s coalition into lawful authority, but coronation without the letter may look like exploiting suspicion before judgment is complete.'
  WHEN t.event_code='ELIAN_BLOCKED_CORONATION_CLIMAX' AND t.role_code='ELIAN' AND o.option_code='ACCEPT_COUNCIL_REGENCY'
    THEN 'A temporary regency protects peace and legal process, but gives Rowan’s supporters time to recover and costs Elian immediate momentum.'
  WHEN t.event_code='ELIAN_BLOCKED_CORONATION_CLIMAX' AND t.role_code='ELIAN' AND o.option_code='WITHDRAW_IMMEDIATE_CLAIM'
    THEN 'Withdrawal most directly avoids civil conflict, but abandons Elian’s present claim and may leave the kingdom without decisive lawful authority.'
  ELSE o.rationale_text END
WHERE t.plot_id=@throne
  AND t.event_code IN ('SEALED_ARCHIVE','ROWAN_USE_LETTER_CONTROL','ELIAN_BLOCKED_CORONATION_CLIMAX');

UPDATE plot_dynamic_opportunity_options o
JOIN plot_dynamic_opportunities d ON d.dynamic_opportunity_id=o.dynamic_opportunity_id
SET o.rationale_text=CASE
  WHEN d.opportunity_code='ROWAN_RESPOND_TO_OPPOSITION' AND o.option_code='COUNTER_OPPOSITION'
    THEN 'Counter-mobilization protects Rowan’s momentum, but turns institutional resistance into a more dangerous public contest.'
  WHEN d.opportunity_code='ROWAN_RESPOND_TO_OPPOSITION' AND o.option_code='OFFER_CONTROLLED_COMPROMISE'
    THEN 'A controlled review may contain resistance and preserve standing, but creates a process that could expose the destroyed evidence.'
  WHEN d.opportunity_code='ROWAN_RESPOND_TO_OPPOSITION' AND o.option_code='WAIT_FOR_LIMITS'
    THEN 'Waiting avoids an immediate overreach, but permits opponents to organize while Rowan’s claim remains vulnerable.'
  ELSE o.rationale_text END
WHERE d.plot_id=@throne AND d.opportunity_code='ROWAN_RESPOND_TO_OPPOSITION';

-- A dynamic opportunity may already have created its persistent behavior template.
UPDATE plot_decision_template_options o
JOIN plot_decision_templates t ON t.template_id=o.template_id
SET o.rationale_text=CASE
  WHEN o.option_code='COUNTER_OPPOSITION'
    THEN 'Counter-mobilization protects Rowan’s momentum, but turns institutional resistance into a more dangerous public contest.'
  WHEN o.option_code='OFFER_CONTROLLED_COMPROMISE'
    THEN 'A controlled review may contain resistance and preserve standing, but creates a process that could expose the destroyed evidence.'
  WHEN o.option_code='WAIT_FOR_LIMITS'
    THEN 'Waiting avoids an immediate overreach, but permits opponents to organize while Rowan’s claim remains vulnerable.'
  ELSE o.rationale_text END
WHERE t.plot_id=@throne
  AND t.event_code='ROWAN_RESPOND_TO_OPPOSITION'
  AND t.role_code='ROWAN';

INSERT IGNORE INTO schema_migrations(migration)
VALUES('057_improve_tradeoffs_and_bellweather_climax.sql');
