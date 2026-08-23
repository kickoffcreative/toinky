-- Preserve meaningful Empty Throne branches after the all-AI convergence audit.
-- All behavior remains plot-authored data. Generic PHP continues to evaluate the
-- same opportunity, condition, transition, and effect vocabulary for every story.

SET @plot=(SELECT plot_id FROM plots WHERE code='EMPTY_THRONE' LIMIT 1);

-- Tomas choosing neutral archive custody must change Rowan's next situation.
-- Rowan may still contest that custody, but cannot use the private letter-control
-- dilemma as though the guard never intervened.
SET @letter_control=(SELECT dynamic_opportunity_id
  FROM plot_dynamic_opportunities
  WHERE plot_id=@plot AND opportunity_code='ROWAN_USE_LETTER_CONTROL'
  LIMIT 1);
INSERT INTO plot_dynamic_opportunity_conditions
(dynamic_opportunity_id,source_type,source_key,operator,compare_value,sort_order)
SELECT @letter_control,'OPTION_CHOICE_COUNT','LOCK_ARCHIVE','EQ','0',6
WHERE @letter_control IS NOT NULL
  AND NOT EXISTS(
    SELECT 1 FROM plot_dynamic_opportunity_conditions
    WHERE dynamic_opportunity_id=@letter_control
      AND source_type='OPTION_CHOICE_COUNT'
      AND source_key='LOCK_ARCHIVE'
  );

-- The initial simultaneous archive struggle may leave Rowan recorded as the holder
-- before the follow-up custody dispute resolves. Authorize the plot-defined return
-- to neutral guard custody.
SET @holder_property=(SELECT p.property_id
  FROM plot_state_aliases a
  JOIN plot_entities e ON e.plot_id=a.plot_id AND e.entity_code=a.entity_code
  JOIN plot_entity_properties p
    ON p.plot_entity_id=e.plot_entity_id AND p.property_code=a.property_code
  WHERE a.plot_id=@plot AND a.state_key='LETTER_HOLDER'
  LIMIT 1);
INSERT IGNORE INTO plot_property_transitions(property_id,from_value,to_value,active)
SELECT @holder_property,'ROWAN','TOMAS',1 WHERE @holder_property IS NOT NULL;

INSERT INTO plot_dynamic_opportunities
(plot_id,opportunity_code,role_code,prompt_text,window_seconds,once_only,priority,active,reconsideration_key,opportunity_kind)
SELECT @plot,'ROWAN_CONTEST_GUARD_ARCHIVE','ROWAN',
       'Captain Reed has sealed the archive under neutral guard custody. Your attendants cannot privately remove the succession letter without confronting the guard. Do you demand its release under presumed-heir authority, accept a supervised review, or yield custody while you build support elsewhere?',
       25,1,21,1,NULL,'REACTION'
WHERE @plot IS NOT NULL
ON DUPLICATE KEY UPDATE
  prompt_text=VALUES(prompt_text),
  window_seconds=VALUES(window_seconds),
  once_only=1,
  priority=VALUES(priority),
  active=1,
  opportunity_kind='REACTION';

SET @contest=(SELECT dynamic_opportunity_id
  FROM plot_dynamic_opportunities
  WHERE plot_id=@plot AND opportunity_code='ROWAN_CONTEST_GUARD_ARCHIVE'
  LIMIT 1);

DELETE FROM plot_dynamic_opportunity_conditions
WHERE dynamic_opportunity_id=@contest;
INSERT INTO plot_dynamic_opportunity_conditions
(dynamic_opportunity_id,source_type,source_key,operator,compare_value,sort_order)
SELECT @contest,'PHASE',NULL,'IN','INCITING_INCIDENT,RISING_ACTION',1 WHERE @contest IS NOT NULL
UNION ALL
SELECT @contest,'WORLD_STATE','CROWN_HOLDER','EQ','NONE',2 WHERE @contest IS NOT NULL
UNION ALL
SELECT @contest,'WORLD_STATE','LETTER_STATUS','IN','SEALED,FOUND',3 WHERE @contest IS NOT NULL
UNION ALL
SELECT @contest,'OPTION_CHOICE_COUNT','LOCK_ARCHIVE','GTE','1',4 WHERE @contest IS NOT NULL
UNION ALL
SELECT @contest,'ACTION_COUNT',NULL,'GTE','1',5 WHERE @contest IS NOT NULL;

DELETE FROM plot_dynamic_opportunity_options
WHERE dynamic_opportunity_id=@contest;
INSERT INTO plot_dynamic_opportunity_options
(dynamic_opportunity_id,option_code,label,result_text,npc_weight,rationale_text,sort_order)
SELECT @contest,'DEMAND_GUARD_RELEASE',
       'Demand that the guard release the letter to you as presumed heir.',
       'Rowan invokes presumed-heir authority and forces the archive confrontation into public view.',
       40,
       'Recovering direct control protects Rowan from the letter, but openly pressuring neutral guards makes the evidence dispute harder to conceal.',
       1
WHERE @contest IS NOT NULL
UNION ALL
SELECT @contest,'ACCEPT_SUPERVISED_REVIEW',
       'Accept a supervised review while insisting the abdication was withdrawn.',
       'Rowan permits the guard and council to examine the letter while preserving his formal claim.',
       35,
       'A supervised review may restore procedural legitimacy, but gives the document the authoritative hearing Rowan fears.',
       2
WHERE @contest IS NOT NULL
UNION ALL
SELECT @contest,'YIELD_NEUTRAL_CUSTODY',
       'Leave the letter with the guard and build political support elsewhere.',
       'Rowan yields the immediate archive contest and turns back toward the political struggle.',
       25,
       'Yielding avoids a confrontation with the guard, but leaves a recoverable threat in neutral custody while Rowan gives up direct control.',
       3
WHERE @contest IS NOT NULL;

SET @demand=(SELECT dynamic_option_id
  FROM plot_dynamic_opportunity_options
  WHERE dynamic_opportunity_id=@contest AND option_code='DEMAND_GUARD_RELEASE'
  LIMIT 1);
INSERT INTO plot_dynamic_option_effects
(dynamic_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order)
SELECT @demand,'WORLD_STATE','LETTER_HOLDER',NULL,'ROWAN',
       'Rowan reasserts direct custody after openly challenging the neutral guard.',1
WHERE @demand IS NOT NULL
UNION ALL
SELECT @demand,'FACTOR','COURT_TRUST',-5,NULL,
       'Pressuring neutral guards makes Rowan appear willing to override lawful custody.',2
WHERE @demand IS NOT NULL
UNION ALL
SELECT @demand,'FACTOR','UNREST',2,NULL,
       'The confrontation at the archive makes the succession struggle more visible.',3
WHERE @demand IS NOT NULL;

SET @review=(SELECT dynamic_option_id
  FROM plot_dynamic_opportunity_options
  WHERE dynamic_opportunity_id=@contest AND option_code='ACCEPT_SUPERVISED_REVIEW'
  LIMIT 1);
INSERT INTO plot_dynamic_option_effects
(dynamic_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order)
SELECT @review,'WORLD_STATE','LETTER_HOLDER',NULL,'TOMAS',
       'The succession letter remains in neutral guard custody during review.',1
WHERE @review IS NOT NULL
UNION ALL
SELECT @review,'WORLD_STATE','REVIEW_ORDERED',NULL,'YES',
       'Rowan accepts a formal examination of the disputed evidence.',2
WHERE @review IS NOT NULL
UNION ALL
SELECT @review,'WORLD_STATE','REVIEW_STATUS',NULL,'BLOCKED',
       'The immediate coronation route pauses while the secured original is examined.',3
WHERE @review IS NOT NULL
UNION ALL
SELECT @review,'WORLD_STATE','CORONATION_BLOCKED',NULL,'YES',
       'No coronation may proceed until the supervised review reaches a finding.',4
WHERE @review IS NOT NULL
UNION ALL
SELECT @review,'FACTOR','COURT_TRUST',5,NULL,
       'Accepting neutral examination restores confidence in lawful process.',5
WHERE @review IS NOT NULL;

SET @yield=(SELECT dynamic_option_id
  FROM plot_dynamic_opportunity_options
  WHERE dynamic_opportunity_id=@contest AND option_code='YIELD_NEUTRAL_CUSTODY'
  LIMIT 1);
INSERT INTO plot_dynamic_option_effects
(dynamic_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order)
SELECT @yield,'WORLD_STATE','LETTER_HOLDER',NULL,'TOMAS',
       'The guard retains neutral custody when Rowan leaves the archive contest.',1
WHERE @yield IS NOT NULL
UNION ALL
SELECT @yield,'FACTOR','STABILITY',3,NULL,
       'Avoiding a confrontation at the archive lowers immediate institutional tension.',2
WHERE @yield IS NOT NULL
UNION ALL
SELECT @yield,'FACTOR','LEGITIMACY',-2,NULL,
       'Yielding physical control weakens Rowan’s appearance of unquestioned authority.',3
WHERE @yield IS NOT NULL;

-- A formal compromise is a real procedural concession. Rowan cannot agree to a
-- controlled review and then complete an immediate coronation before that process.
SET @opposition=(SELECT dynamic_opportunity_id
  FROM plot_dynamic_opportunities
  WHERE plot_id=@plot AND opportunity_code='ROWAN_RESPOND_TO_OPPOSITION'
  LIMIT 1);
SET @compromise=(SELECT dynamic_option_id
  FROM plot_dynamic_opportunity_options
  WHERE dynamic_opportunity_id=@opposition
    AND option_code='OFFER_CONTROLLED_COMPROMISE'
  LIMIT 1);
INSERT INTO plot_dynamic_option_effects
(dynamic_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order)
SELECT @compromise,'WORLD_STATE','CORONATION_BLOCKED',NULL,'YES',
       'Rowan’s formal offer suspends immediate coronation while the controlled review is considered.',3
WHERE @compromise IS NOT NULL
  AND NOT EXISTS(
    SELECT 1 FROM plot_dynamic_option_effects
    WHERE dynamic_option_id=@compromise
      AND effect_scope='WORLD_STATE'
      AND target_code='CORONATION_BLOCKED'
  );

-- Previously opened dynamic opportunities have persistent templates in live data.
SET @template_compromise=(SELECT o.template_option_id
  FROM plot_decision_template_options o
  JOIN plot_decision_templates t ON t.template_id=o.template_id
  WHERE t.plot_id=@plot
    AND t.event_code='ROWAN_RESPOND_TO_OPPOSITION'
    AND t.role_code='ROWAN'
    AND o.option_code='OFFER_CONTROLLED_COMPROMISE'
  LIMIT 1);
INSERT INTO plot_option_effects
(template_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order)
SELECT @template_compromise,'WORLD_STATE','CORONATION_BLOCKED',NULL,'YES',
       'Rowan’s formal offer suspends immediate coronation while the controlled review is considered.',3
WHERE @template_compromise IS NOT NULL
  AND NOT EXISTS(
    SELECT 1 FROM plot_option_effects
    WHERE template_option_id=@template_compromise
      AND effect_scope='WORLD_STATE'
      AND target_code='CORONATION_BLOCKED'
  );

-- Keep the alternative climax truthful for every database-authored route that can
-- create the block; it must not assume that all institutions took the same actions.
UPDATE plot_dynamic_opportunities
SET prompt_text='Rowan’s immediate coronation has been blocked. Your active claim and the institutions resisting a rushed succession have created an opening, but the original evidence may still be disputed. Do you request lawful coronation, accept a temporary council regency, or withdraw your immediate claim to prevent conflict?'
WHERE plot_id=@plot AND opportunity_code='ELIAN_BLOCKED_CORONATION_CLIMAX';

UPDATE plot_decision_templates
SET prompt_text='Rowan’s immediate coronation has been blocked. Your active claim and the institutions resisting a rushed succession have created an opening, but the original evidence may still be disputed. Do you request lawful coronation, accept a temporary council regency, or withdraw your immediate claim to prevent conflict?'
WHERE plot_id=@plot
  AND event_code='ELIAN_BLOCKED_CORONATION_CLIMAX'
  AND role_code='ELIAN';

INSERT IGNORE INTO schema_migrations(migration)
VALUES('059_preserve_empty_throne_branch_consequences.sql');
