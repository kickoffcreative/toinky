SET @plot_id=(SELECT plot_id FROM plots WHERE code='CONCEALED_FAILURE' LIMIT 1);

SET @o=(SELECT pdto.template_option_id FROM plot_decision_template_options pdto JOIN plot_decision_templates pdt ON pdt.template_id=pdto.template_id WHERE pdt.plot_id=@plot_id AND pdt.role_code='WREN' AND pdt.event_code='WALL_SHIFT' AND pdto.option_code='CHECK_WALL' LIMIT 1);
INSERT INTO plot_option_effects(template_option_id,effect_scope,target_code,delta_value,detail_text,sort_order)
SELECT @o,'STAT','RISK',4,'Choosing to inspect an unstable structure increases Wren’s demonstrated willingness to accept risk.',10 WHERE @o IS NOT NULL;

SET @o=(SELECT pdto.template_option_id FROM plot_decision_template_options pdto JOIN plot_decision_templates pdt ON pdt.template_id=pdto.template_id WHERE pdt.plot_id=@plot_id AND pdt.role_code='KELLAN' AND pdt.event_code='WALL_SHIFT' AND pdto.option_code='FOLLOW_FARAH' LIMIT 1);
INSERT INTO plot_option_effects(template_option_id,effect_scope,target_code,delta_value,detail_text,sort_order)
SELECT @o,'STAT','RISK',5,'Following Farah into the uncertain office corridor increases Kellan’s demonstrated risk tolerance.',10 WHERE @o IS NOT NULL;

SET @o=(SELECT pdto.template_option_id FROM plot_decision_template_options pdto JOIN plot_decision_templates pdt ON pdt.template_id=pdto.template_id WHERE pdt.plot_id=@plot_id AND pdt.role_code='NIKO' AND pdt.event_code='WALL_SHIFT' AND pdto.option_code='CHECK_FARAH' LIMIT 1);
INSERT INTO plot_option_effects(template_option_id,effect_scope,target_code,delta_value,detail_text,sort_order)
SELECT @o,'STAT','LOYALTY',-8,'Questioning Farah during the emergency weakens Niko’s automatic loyalty to her.',10 WHERE @o IS NOT NULL;

SET @o=(SELECT pdto.template_option_id FROM plot_decision_template_options pdto JOIN plot_decision_templates pdt ON pdt.template_id=pdto.template_id WHERE pdt.plot_id=@plot_id AND pdt.role_code='FARAH' AND pdt.event_code='WALL_SHIFT' AND pdto.option_code='SECURE_REPORT' LIMIT 1);
INSERT INTO plot_option_effects(template_option_id,effect_scope,target_code,delta_value,detail_text,sort_order)
SELECT @o,'STAT','SELF_PRESERVATION',3,'Using the emergency to protect herself reinforces Farah’s self-preservation behavior.',10 WHERE @o IS NOT NULL;

SET @o=(SELECT pdto.template_option_id FROM plot_decision_template_options pdto JOIN plot_decision_templates pdt ON pdt.template_id=pdto.template_id WHERE pdt.plot_id=@plot_id AND pdt.role_code='ALDEN' AND pdt.event_code='WALL_SHIFT' AND pdto.option_code='PROTECT_REPORT' LIMIT 1);
INSERT INTO plot_option_effects(template_option_id,effect_scope,target_code,delta_value,detail_text,sort_order)
SELECT @o,'STAT','EVIDENCE_FOCUS',2,'Alden immediately prioritizes documentary proof under pressure.',10 WHERE @o IS NOT NULL;

SET @o=(SELECT pdto.template_option_id FROM plot_decision_template_options pdto JOIN plot_decision_templates pdt ON pdt.template_id=pdto.template_id WHERE pdt.plot_id=@plot_id AND pdt.role_code='LEILA' AND pdt.event_code='WALL_SHIFT' AND pdto.option_code='STABILIZE_GUEST' LIMIT 1);
INSERT INTO plot_option_effects(template_option_id,effect_scope,target_code,delta_value,detail_text,sort_order)
SELECT @o,'STAT','URGENCY',4,'Leila commits immediately to hands-on care as the danger escalates.',10 WHERE @o IS NOT NULL;

INSERT IGNORE INTO schema_migrations(migration) VALUES('003_character_stat_effects.sql');
