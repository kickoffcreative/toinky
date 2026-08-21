SET @plot_id=(SELECT plot_id FROM plots WHERE code='CONCEALED_FAILURE' LIMIT 1);

-- WALL SHIFT: securing the physical report creates shareable physical evidence.
SET @t=(SELECT template_id FROM plot_decision_templates WHERE plot_id=@plot_id AND event_code='WALL_SHIFT' AND role_code='ALDEN' LIMIT 1);
SET @o=(SELECT template_option_id FROM plot_decision_template_options WHERE template_id=@t AND label='Go immediately to the office and secure the copied failed report.' LIMIT 1);
INSERT INTO plot_option_effects(template_option_id,effect_scope,target_code,set_value,detail_text,sort_order)
SELECT @o,'WORLD_STATE','ALDEN_SHAREABLE_EVIDENCE','YES','Alden now controls a physical document that can be shown, copied or transferred.',30 FROM DUAL
WHERE @o IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_option_effects WHERE template_option_id=@o AND effect_scope='WORLD_STATE' AND target_code='ALDEN_SHAREABLE_EVIDENCE');

-- REPORT CONFLICT: explaining knowledge is allowed without physical possession.
SET @t=(SELECT template_id FROM plot_decision_templates WHERE plot_id=@plot_id AND event_code='REPORT_MISSING' AND role_code='ALDEN' LIMIT 1);
SET @show=(SELECT template_option_id FROM plot_decision_template_options WHERE template_id=@t AND label='Show Niko the report or explain exactly what the failed copy said.' LIMIT 1);
DELETE FROM plot_option_conditions WHERE template_option_id=@show AND state_key='REPORT_HOLDER';
INSERT INTO plot_option_conditions(template_option_id,state_key,operator,compare_value,reason_text,sort_order)
SELECT @show,'ALDEN_EVIDENCE_KNOWLEDGE','EQ','KNOWN','Alden must know what the failed report contained.',10 FROM DUAL
WHERE @show IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_option_conditions WHERE template_option_id=@show AND state_key='ALDEN_EVIDENCE_KNOWLEDGE');
INSERT INTO plot_option_effects(template_option_id,effect_scope,target_code,set_value,detail_text,sort_order)
SELECT @show,'WORLD_STATE','EVIDENCE_WITNESSES','NIKO_INFORMED','Niko now independently knows Alden’s account of the failed inspection.',30 FROM DUAL
WHERE @show IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_option_effects WHERE template_option_id=@show AND effect_scope='WORLD_STATE' AND target_code='EVIDENCE_WITNESSES');

SET @hide=(SELECT template_option_id FROM plot_decision_template_options WHERE template_id=@t AND label='Keep the report hidden until authorities can receive it.' LIMIT 1);
-- Existing REPORT_HOLDER=ALDEN prerequisite remains authoritative for physical hiding.

SET @accuse=(SELECT template_option_id FROM plot_decision_template_options WHERE template_id=@t AND label='Publicly accuse Farah of concealing the failed inspection.' LIMIT 1);
INSERT INTO plot_option_conditions(template_option_id,state_key,operator,compare_value,reason_text,sort_order)
SELECT @accuse,'ALDEN_EVIDENCE_KNOWLEDGE','EQ','KNOWN','Alden can accuse from direct knowledge even if the document is elsewhere.',10 FROM DUAL
WHERE @accuse IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_option_conditions WHERE template_option_id=@accuse AND state_key='ALDEN_EVIDENCE_KNOWLEDGE');

-- Farah may only conceal report material if she actually holds the physical report.
SET @t=(SELECT template_id FROM plot_decision_templates WHERE plot_id=@plot_id AND event_code='REPORT_MISSING' AND role_code='FARAH' LIMIT 1);
SET @conceal=(SELECT template_option_id FROM plot_decision_template_options WHERE template_id=@t AND label='Use the confusion to move or further conceal whatever report material you control.' LIMIT 1);
INSERT INTO plot_option_conditions(template_option_id,state_key,operator,compare_value,reason_text,sort_order)
SELECT @conceal,'REPORT_HOLDER','EQ','FARAH','Farah cannot conceal physical report material she does not possess.',10 FROM DUAL
WHERE @conceal IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_option_conditions WHERE template_option_id=@conceal AND state_key='REPORT_HOLDER');

-- RISING ACTION: copies require possession; witnesses require knowledge.
SET @t=(SELECT template_id FROM plot_decision_templates WHERE plot_id=@plot_id AND event_code='PRESSURE_RISE' AND role_code='ALDEN' LIMIT 1);
SET @copy=(SELECT template_option_id FROM plot_decision_template_options WHERE template_id=@t AND label='Create or separate another copy of the evidence.' LIMIT 1);
INSERT INTO plot_option_effects(template_option_id,effect_scope,target_code,set_value,detail_text,sort_order)
SELECT @copy,'WORLD_STATE','REPORT_COPY_STATE','ALDEN_COPY_EXISTS','A recoverable duplicate of the failed report now exists.',30 FROM DUAL
WHERE @copy IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_option_effects WHERE template_option_id=@copy AND effect_scope='WORLD_STATE' AND target_code='REPORT_COPY_STATE');
INSERT INTO plot_option_effects(template_option_id,effect_scope,target_code,set_value,detail_text,sort_order)
SELECT @copy,'WORLD_STATE','ALDEN_SHAREABLE_EVIDENCE','YES','Alden retains evidence that can be independently transferred.',31 FROM DUAL
WHERE @copy IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_option_effects WHERE template_option_id=@copy AND effect_scope='WORLD_STATE' AND target_code='ALDEN_SHAREABLE_EVIDENCE');

SET @witness=(SELECT template_option_id FROM plot_decision_template_options WHERE template_id=@t AND label='Find a second person willing to verify what the report says.' LIMIT 1);
INSERT INTO plot_option_conditions(template_option_id,state_key,operator,compare_value,reason_text,sort_order)
SELECT @witness,'ALDEN_EVIDENCE_KNOWLEDGE','EQ','KNOWN','Alden must know the evidence well enough to seek corroboration.',10 FROM DUAL
WHERE @witness IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_option_conditions WHERE template_option_id=@witness AND state_key='ALDEN_EVIDENCE_KNOWLEDGE');
INSERT INTO plot_option_effects(template_option_id,effect_scope,target_code,set_value,detail_text,sort_order)
SELECT @witness,'WORLD_STATE','EVIDENCE_WITNESSES','CORROBORATED','A second person can now corroborate Alden’s account of the evidence.',30 FROM DUAL
WHERE @witness IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_option_effects WHERE template_option_id=@witness AND effect_scope='WORLD_STATE' AND target_code='EVIDENCE_WITNESSES');

SET @confront=(SELECT template_option_id FROM plot_decision_template_options WHERE template_id=@t AND label='Force Farah into a direct public confrontation now.' LIMIT 1);
INSERT INTO plot_option_conditions(template_option_id,state_key,operator,compare_value,reason_text,sort_order)
SELECT @confront,'ALDEN_EVIDENCE_KNOWLEDGE','EQ','KNOWN','Alden must have substantive knowledge before forcing a public evidence confrontation.',10 FROM DUAL
WHERE @confront IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_option_conditions WHERE template_option_id=@confront AND state_key='ALDEN_EVIDENCE_KNOWLEDGE');

-- CLIMAX: transferring proof requires something transferable; sending a summary only requires knowledge.
SET @t=(SELECT template_id FROM plot_decision_templates WHERE plot_id=@plot_id AND event_code='REAR_EXIT' AND role_code='ALDEN' LIMIT 1);
SET @transfer=(SELECT template_option_id FROM plot_decision_template_options WHERE template_id=@t AND label='Give the strongest surviving evidence to another character before Farah can isolate it.' LIMIT 1);
INSERT INTO plot_option_conditions(template_option_id,state_key,operator,compare_value,reason_text,sort_order)
SELECT @transfer,'ALDEN_SHAREABLE_EVIDENCE','EQ','YES','Alden must actually possess transferable evidence before handing it to another person.',10 FROM DUAL
WHERE @transfer IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_option_conditions WHERE template_option_id=@transfer AND state_key='ALDEN_SHAREABLE_EVIDENCE');
INSERT INTO plot_option_effects(template_option_id,effect_scope,target_code,set_value,detail_text,sort_order)
SELECT @transfer,'WORLD_STATE','EVIDENCE_DISTRIBUTION','SHARED','Evidence is now physically distributed beyond Alden’s sole control.',30 FROM DUAL
WHERE @transfer IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_option_effects WHERE template_option_id=@transfer AND effect_scope='WORLD_STATE' AND target_code='EVIDENCE_DISTRIBUTION');

SET @exit_confront=(SELECT template_option_id FROM plot_decision_template_options WHERE template_id=@t AND label='Move to the rear exit and confront Farah directly.' LIMIT 1);
INSERT INTO plot_option_conditions(template_option_id,state_key,operator,compare_value,reason_text,sort_order)
SELECT @exit_confront,'ALDEN_EVIDENCE_KNOWLEDGE','EQ','KNOWN','Alden needs substantive knowledge to confront Farah over the evidence.',10 FROM DUAL
WHERE @exit_confront IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_option_conditions WHERE template_option_id=@exit_confront AND state_key='ALDEN_EVIDENCE_KNOWLEDGE');

SET @send=(SELECT template_option_id FROM plot_decision_template_options WHERE template_id=@t AND label='Use the remaining connection to send the evidence summary outside Bellweather.' LIMIT 1);
INSERT INTO plot_option_conditions(template_option_id,state_key,operator,compare_value,reason_text,sort_order)
SELECT @send,'ALDEN_EVIDENCE_KNOWLEDGE','EQ','KNOWN','Alden can transmit an evidence summary from what he knows even without holding the original report.',10 FROM DUAL
WHERE @send IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_option_conditions WHERE template_option_id=@send AND state_key='ALDEN_EVIDENCE_KNOWLEDGE');
INSERT INTO plot_option_effects(template_option_id,effect_scope,target_code,set_value,detail_text,sort_order)
SELECT @send,'WORLD_STATE','EVIDENCE_EXTERNALIZED','YES','Alden’s evidence summary now exists outside Bellweather.',30 FROM DUAL
WHERE @send IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_option_effects WHERE template_option_id=@send AND effect_scope='WORLD_STATE' AND target_code='EVIDENCE_EXTERNALIZED');

-- Farah cannot destroy or disable evidence unless she physically controls the report, and externalized evidence cannot be pulled back.
SET @t=(SELECT template_id FROM plot_decision_templates WHERE plot_id=@plot_id AND event_code='REAR_EXIT' AND role_code='FARAH' LIMIT 1);
SET @damage=(SELECT template_option_id FROM plot_decision_template_options WHERE template_id=@t AND label='Try to make the remaining evidence unusable before anyone can secure it.' LIMIT 1);
INSERT INTO plot_option_conditions(template_option_id,state_key,operator,compare_value,reason_text,sort_order)
SELECT @damage,'REPORT_HOLDER','EQ','FARAH','Farah must physically control the failed report to damage that evidence.',10 FROM DUAL
WHERE @damage IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_option_conditions WHERE template_option_id=@damage AND state_key='REPORT_HOLDER');
INSERT INTO plot_option_conditions(template_option_id,state_key,operator,compare_value,reason_text,sort_order)
SELECT @damage,'EVIDENCE_EXTERNALIZED','EQ','NO','Evidence already sent outside Bellweather cannot be made unusable locally.',11 FROM DUAL
WHERE @damage IS NOT NULL AND NOT EXISTS(SELECT 1 FROM plot_option_conditions WHERE template_option_id=@damage AND state_key='EVIDENCE_EXTERNALIZED');

INSERT IGNORE INTO schema_migrations(migration) VALUES('008_evidence_state_layer.sql');
