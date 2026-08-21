CREATE TABLE IF NOT EXISTS schema_migrations (
  migration VARCHAR(190) PRIMARY KEY,
  applied_at_utc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plot_event_perceptions (
  perception_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  plot_id BIGINT UNSIGNED NOT NULL,
  event_code VARCHAR(120) NOT NULL,
  role_code VARCHAR(100) NOT NULL,
  history_type ENUM('EXPERIENCED','OBSERVED','INTERACTION','KNOWLEDGE') NOT NULL DEFAULT 'OBSERVED',
  title VARCHAR(255) NOT NULL,
  perception_text TEXT NOT NULL,
  location_after VARCHAR(180) NULL,
  UNIQUE KEY uq_perception(plot_id,event_code,role_code),
  CONSTRAINT fk_pep_plot FOREIGN KEY(plot_id) REFERENCES plots(plot_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plot_decision_templates (
  template_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  plot_id BIGINT UNSIGNED NOT NULL,
  event_code VARCHAR(120) NOT NULL,
  role_code VARCHAR(100) NOT NULL,
  actor_type ENUM('PLAYER','NPC') NOT NULL,
  prompt_text TEXT NOT NULL,
  decision_window_seconds INT UNSIGNED NOT NULL DEFAULT 30,
  UNIQUE KEY uq_decision_template(plot_id,event_code,role_code),
  CONSTRAINT fk_pdt_plot FOREIGN KEY(plot_id) REFERENCES plots(plot_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plot_decision_template_options (
  template_option_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  template_id BIGINT UNSIGNED NOT NULL,
  option_code VARCHAR(100) NOT NULL,
  label VARCHAR(500) NOT NULL,
  npc_weight INT NOT NULL DEFAULT 0,
  rationale_text TEXT NULL,
  result_text TEXT NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  UNIQUE KEY uq_template_option(template_id,option_code),
  CONSTRAINT fk_pdto_template FOREIGN KEY(template_id) REFERENCES plot_decision_templates(template_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plot_option_effects (
  effect_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  template_option_id BIGINT UNSIGNED NOT NULL,
  effect_scope ENUM('FACTOR','STAT','LOCATION','KNOWLEDGE','RELATIONSHIP') NOT NULL,
  target_code VARCHAR(190) NOT NULL,
  delta_value INT NULL,
  set_value VARCHAR(500) NULL,
  detail_text TEXT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_poe_option FOREIGN KEY(template_option_id) REFERENCES plot_decision_template_options(template_option_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @plot_id=(SELECT plot_id FROM plots WHERE code='CONCEALED_FAILURE' LIMIT 1);

INSERT IGNORE INTO plot_event_perceptions(plot_id,event_code,role_code,history_type,title,perception_text,location_after) VALUES
(@plot_id,'WALL_SHIFT','KELLAN','OBSERVED','Farah Moves Before Anyone Else','The wall gives way. While most people turn toward the noise, you notice Farah immediately glance toward the office corridor. Yesterday’s question about bypassing the service-door alarm comes back to you.',NULL),
(@plot_id,'WALL_SHIFT','NIKO','EXPERIENCED','The Resort Is Suddenly Your Responsibility','Guests look to you when the wall moves. Farah is still in the lobby, but the east side may be unstable and someone is calling for help near the stairwell.',NULL),
(@plot_id,'WALL_SHIFT','WREN','OBSERVED','A Cry From The East Stairwell','You hear a guest cry out near the east stairwell. Leila is already moving toward the sound. The grinding outside suggests the wall may not be finished moving.','East Stairwell'),
(@plot_id,'WALL_SHIFT','FARAH','OBSERVED','Alden Looks Toward His Office','The wall fails. Alden’s first reaction is not toward the injured guest but toward his office. You know exactly what he may be worried about.',NULL),
(@plot_id,'WALL_SHIFT','ALDEN','EXPERIENCED','The Copy Is Suddenly Vulnerable','The wall collapses and the lobby erupts. Your copied failed report is still in the office, and Farah knows you have been questioning the payment trail.',NULL),
(@plot_id,'WALL_SHIFT','LEILA','EXPERIENCED','Someone Is Hurt','A guest near the east stairwell is struck by falling plaster. You are the closest person with first-aid experience.','East Stairwell');

INSERT IGNORE INTO plot_decision_templates(plot_id,event_code,role_code,actor_type,prompt_text,decision_window_seconds) VALUES
(@plot_id,'WALL_SHIFT','KELLAN','PLAYER','Farah reacted to the office corridor before anyone understood what happened. What do you do?',30),
(@plot_id,'WALL_SHIFT','NIKO','PLAYER','People are looking to you for direction while the east side may still be unstable. What do you prioritize?',30),
(@plot_id,'WALL_SHIFT','WREN','PLAYER','Leila needs help near the injured guest, but the wall itself may still be moving. What do you do?',30),
(@plot_id,'WALL_SHIFT','FARAH','NPC','The collapse creates confusion and Alden may still have the failed report. Choose the action that best protects your goal.',30),
(@plot_id,'WALL_SHIFT','ALDEN','NPC','The collapse creates a brief window where the evidence could be lost. What do you do?',30),
(@plot_id,'WALL_SHIFT','LEILA','NPC','An injured guest needs immediate help while the building may still be unstable. What do you do?',30);

SET @t=(SELECT template_id FROM plot_decision_templates WHERE plot_id=@plot_id AND event_code='WALL_SHIFT' AND role_code='KELLAN');
INSERT IGNORE INTO plot_decision_template_options(template_id,option_code,label,npc_weight,rationale_text,result_text,sort_order) VALUES
(@t,'FOLLOW_FARAH','Follow Farah toward the office corridor.',0,NULL,'You move after Farah instead of joining the first response to the injured guest.',1),
(@t,'HELP_GUESTS','Go toward the east stairwell and help the injured guest.',0,NULL,'You leave Farah unwatched and move toward the injured guest.',2),
(@t,'STAY_WATCH','Stay in the lobby and watch who moves where.',0,NULL,'You remain in the lobby, preserving your view of the group but giving up the chance to intervene immediately.',3);

SET @o=(SELECT template_option_id FROM plot_decision_template_options WHERE template_id=@t AND option_code='FOLLOW_FARAH');
INSERT INTO plot_option_effects(template_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order) VALUES
(@o,'FACTOR','SUSPICION',8,NULL,'Kellan’s attention to Farah raises the chance that suspicious movement will be noticed.',1),
(@o,'LOCATION','SELF',NULL,'Office Corridor','Kellan follows Farah toward the office corridor.',2);
SET @o=(SELECT template_option_id FROM plot_decision_template_options WHERE template_id=@t AND option_code='HELP_GUESTS');
INSERT INTO plot_option_effects(template_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order) VALUES
(@o,'FACTOR','SAFETY',6,NULL,'Another person moves toward the injured guest.',1),
(@o,'LOCATION','SELF',NULL,'East Stairwell','Kellan moves toward the east stairwell.',2);

SET @t=(SELECT template_id FROM plot_decision_templates WHERE plot_id=@plot_id AND event_code='WALL_SHIFT' AND role_code='NIKO');
INSERT IGNORE INTO plot_decision_template_options(template_id,option_code,label,npc_weight,rationale_text,result_text,sort_order) VALUES
(@t,'EVACUATE_EAST','Take control of the east-wing evacuation.',0,NULL,'You move toward the east side and begin directing guests away from the damaged area.',1),
(@t,'CHECK_FARAH','Stay with Farah and ask what she knows about the wall.',0,NULL,'You keep Farah close and ask for an explanation before committing the group to a response.',2),
(@t,'FIND_ALDEN','Find Alden and ask why he reacted toward the office.',0,NULL,'You seek Alden out, prioritizing the unexplained office reaction over immediate command of the evacuation.',3);
SET @o=(SELECT template_option_id FROM plot_decision_template_options WHERE template_id=@t AND option_code='EVACUATE_EAST');
INSERT INTO plot_option_effects(template_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order) VALUES
(@o,'FACTOR','SAFETY',10,NULL,'Niko organizes people away from the east side.',1),
(@o,'LOCATION','SELF',NULL,'East Wing','Niko moves toward the east wing.',2);
SET @o=(SELECT template_option_id FROM plot_decision_template_options WHERE template_id=@t AND option_code='CHECK_FARAH');
INSERT INTO plot_option_effects(template_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order) VALUES
(@o,'FACTOR','SUSPICION',5,NULL,'Niko begins questioning Farah’s certainty.',1);

SET @t=(SELECT template_id FROM plot_decision_templates WHERE plot_id=@plot_id AND event_code='WALL_SHIFT' AND role_code='WREN');
INSERT IGNORE INTO plot_decision_template_options(template_id,option_code,label,npc_weight,rationale_text,result_text,sort_order) VALUES
(@t,'HELP_LEILA','Help Leila assess and stabilize the injured guest.',0,NULL,'You join Leila at the injured guest and begin stabilizing them.',1),
(@t,'CHECK_WALL','Inspect the damaged east side for signs of another failure.',0,NULL,'You focus on the physical hazard and look for signs that the east side may move again.',2),
(@t,'FIND_NIKO','Find Niko and warn him that the east side may still be unstable.',0,NULL,'You leave the immediate scene long enough to bring the operations manager into the hazard response.',3);
SET @o=(SELECT template_option_id FROM plot_decision_template_options WHERE template_id=@t AND option_code='HELP_LEILA');
INSERT INTO plot_option_effects(template_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order) VALUES
(@o,'FACTOR','SAFETY',12,NULL,'Wren’s emergency-response experience improves the injured guest’s immediate safety.',1),
(@o,'LOCATION','SELF',NULL,'East Stairwell','Wren stays with Leila and the injured guest.',2);
SET @o=(SELECT template_option_id FROM plot_decision_template_options WHERE template_id=@t AND option_code='CHECK_WALL');
INSERT INTO plot_option_effects(template_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order) VALUES
(@o,'FACTOR','SAFETY',5,NULL,'Wren identifies hazards before another movement.',1),
(@o,'KNOWLEDGE','EAST_SIDE_UNSTABLE',NULL,'The damaged east side shows signs that another slope movement is possible.','Wren learns the physical danger is continuing.',2);

SET @t=(SELECT template_id FROM plot_decision_templates WHERE plot_id=@plot_id AND event_code='WALL_SHIFT' AND role_code='FARAH');
INSERT IGNORE INTO plot_decision_template_options(template_id,option_code,label,npc_weight,rationale_text,result_text,sort_order) VALUES
(@t,'SECURE_REPORT','Use the confusion to get to Alden’s office and secure the failed report.',55,'High self-preservation makes removal of direct evidence attractive.','Farah moves toward Alden’s office, using the emergency as cover.',1),
(@t,'CONTROL_ROOM','Stay visible and take control of the evacuation narrative.',30,'Manipulation is strongest when Farah remains publicly authoritative.','Farah stays in the lobby and directs attention toward evacuation and away from Alden’s concerns.',2),
(@t,'CONFRONT_ALDEN','Intercept Alden before he can retrieve or discuss the report.',15,'Direct confrontation risks attention but may stop Alden acting first.','Farah moves to intercept Alden and demands that he focus on the guests.',3);
SET @o=(SELECT template_option_id FROM plot_decision_template_options WHERE template_id=@t AND option_code='SECURE_REPORT');
INSERT INTO plot_option_effects(template_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order) VALUES
(@o,'FACTOR','EVIDENCE',-12,NULL,'Farah’s move threatens the physical evidence.',1),
(@o,'FACTOR','CONTROL',8,NULL,'Possessing the report increases Farah’s control.',2),
(@o,'LOCATION','SELF',NULL,'Office Corridor','Farah heads toward Alden’s office.',3),
(@o,'KNOWLEDGE','REPORT_HOLDER',NULL,'FARAH','The world records that Farah has moved to secure the report.',4);
SET @o=(SELECT template_option_id FROM plot_decision_template_options WHERE template_id=@t AND option_code='CONTROL_ROOM');
INSERT INTO plot_option_effects(template_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order) VALUES
(@o,'FACTOR','CONTROL',5,NULL,'Farah remains publicly authoritative during the first minutes of the emergency.',1),
(@o,'FACTOR','SUSPICION',-3,NULL,'Visible crisis leadership temporarily lowers suspicion.',2);

SET @t=(SELECT template_id FROM plot_decision_templates WHERE plot_id=@plot_id AND event_code='WALL_SHIFT' AND role_code='ALDEN');
INSERT IGNORE INTO plot_decision_template_options(template_id,option_code,label,npc_weight,rationale_text,result_text,sort_order) VALUES
(@t,'PROTECT_REPORT','Go immediately to the office and secure the copied failed report.',65,'Alden’s evidence focus strongly favors protecting the document.','Alden heads toward the office to secure the copied report.',1),
(@t,'HELP_RESPONSE','Stay and help organize the emergency response.',20,'Immediate safety competes with Alden’s evidence priority.','Alden stays in the lobby long enough to help direct people away from the damaged side.',2),
(@t,'WATCH_FARAH','Watch Farah before moving, to see whether she reveals what she fears.',15,'Caution can outweigh urgency if Alden thinks Farah will expose herself.','Alden holds back and watches Farah’s first move.',3);
SET @o=(SELECT template_option_id FROM plot_decision_template_options WHERE template_id=@t AND option_code='PROTECT_REPORT');
INSERT INTO plot_option_effects(template_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order) VALUES
(@o,'FACTOR','EVIDENCE',8,NULL,'Alden moves to preserve the report.',1),
(@o,'LOCATION','SELF',NULL,'Office','Alden heads for his office.',2),
(@o,'KNOWLEDGE','REPORT_HOLDER',NULL,'ALDEN','The world records that Alden is actively securing the report.',3);

SET @t=(SELECT template_id FROM plot_decision_templates WHERE plot_id=@plot_id AND event_code='WALL_SHIFT' AND role_code='LEILA');
INSERT IGNORE INTO plot_decision_template_options(template_id,option_code,label,npc_weight,rationale_text,result_text,sort_order) VALUES
(@t,'STABILIZE_GUEST','Stay with the injured guest and begin first aid.',80,'Leila’s empathy and urgency strongly favor immediate care.','Leila kneels beside the injured guest and begins first aid while calling for another helper.',1),
(@t,'GET_HELP','Leave briefly to pull another person into the rescue.',15,'The guest needs more than one helper if evacuation becomes necessary.','Leila moves toward the lobby entrance and calls for someone to help her move the injured guest.',2),
(@t,'CHECK_EXIT','Check whether the west route is still usable before attempting movement.',5,'Route safety matters, but leaving the injured guest is contrary to Leila’s strongest tendency.','Leila checks the nearest safe route before committing to a move.',3);
SET @o=(SELECT template_option_id FROM plot_decision_template_options WHERE template_id=@t AND option_code='STABILIZE_GUEST');
INSERT INTO plot_option_effects(template_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order) VALUES
(@o,'FACTOR','SAFETY',8,NULL,'Immediate first aid improves the injured guest’s condition.',1),
(@o,'LOCATION','SELF',NULL,'East Stairwell','Leila remains with the injured guest.',2);

INSERT IGNORE INTO schema_migrations(migration) VALUES('001_behavior_layer.sql');
