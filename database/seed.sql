INSERT INTO plots(code,title,archetype,genre,setting_name,max_players,max_npcs,status)
VALUES('CONCEALED_FAILURE','The Storm at Bellweather Resort','Concealed Failure','Contemporary Thriller','Mountain Resort',3,3,'active');

SET @plot_id=(SELECT plot_id FROM plots WHERE code='CONCEALED_FAILURE');

INSERT INTO plot_factors(plot_id,factor_code,label,initial_value,min_value,max_value,description) VALUES
(@plot_id,'SAFETY','Safety',85,0,100,'How protected people are from the physical emergency.'),
(@plot_id,'EVIDENCE','Evidence',50,0,100,'How much usable proof survives.'),
(@plot_id,'CONTROL','Farah Control',70,0,100,'How much Farah can still shape events and the public explanation.'),
(@plot_id,'SUSPICION','Public Suspicion',10,0,100,'How strongly the group suspects deliberate concealment.'),
(@plot_id,'TRUST','Group Trust',55,0,100,'How willing the cast is to believe and coordinate with one another.');

INSERT INTO plot_roles(plot_id,role_code,role_type,slot_no,name,public_description,private_knowledge,starting_location,goal_text,fear_text) VALUES
(@plot_id,'KELLAN','PLAYER',1,'Kellan West','A regular guest who notices disruptions in routines and staff behavior.','Yesterday you heard Farah ask maintenance whether the east service door could be opened without logging an alarm.','Lobby','Understand why Farah asked about the alarm.','Another slope failure.'),
(@plot_id,'NIKO','PLAYER',2,'Niko Lang','The resort operations manager, trusted by Farah and relied on by guests.','Farah personally showed you a passing structural certificate yesterday. You believed it was genuine.','Lobby','Protect guests and preserve order.','Discovering Farah used your trust.'),
(@plot_id,'WREN','PLAYER',3,'Wren Harker','A practical guest with emergency-response experience who watches hazards before rumors.','Before dinner you saw Alden photocopying a report marked with a red engineering stamp.','Lobby','Keep people safe while understanding the warning signs.','Choosing wrongly between rescue and evidence.'),
(@plot_id,'FARAH','NPC',1,'Farah Xu','Owner of Bellweather Resort. Calm, persuasive and protective of the business.','You knowingly kept the resort open after the wall failed inspection and paid Martin to replace the report.','Lobby','Prevent proof of deliberate negligence.','Criminal exposure and losing Bellweather.'),
(@plot_id,'ALDEN','NPC',2,'Alden Rowan','The resort accountant, methodical and increasingly unwilling to ignore what he found.','You copied the failed inspection report and traced a suspicious $40,000 payment.','Lobby','Preserve enough evidence to expose what happened.','Farah destroying the remaining proof.'),
(@plot_id,'LEILA','NPC',3,'Leila Torres','A guest with first-aid experience who prioritizes immediate human safety.','You know the injured guest will need two people to move safely if the east wing becomes unstable.','East Stairwell','Protect the injured guest.','The group chasing evidence while someone gets hurt.');

INSERT INTO plot_role_stats(plot_role_id,stat_code,label,initial_value)
SELECT plot_role_id,'RISK','Risk Tolerance',45 FROM plot_roles WHERE plot_id=@plot_id AND role_code='KELLAN';
INSERT INTO plot_role_stats(plot_role_id,stat_code,label,initial_value)
SELECT plot_role_id,'CURIOSITY','Curiosity',80 FROM plot_roles WHERE plot_id=@plot_id AND role_code='KELLAN';
INSERT INTO plot_role_stats(plot_role_id,stat_code,label,initial_value)
SELECT plot_role_id,'LOYALTY','Loyalty',75 FROM plot_roles WHERE plot_id=@plot_id AND role_code='NIKO';
INSERT INTO plot_role_stats(plot_role_id,stat_code,label,initial_value)
SELECT plot_role_id,'DUTY','Sense of Duty',85 FROM plot_roles WHERE plot_id=@plot_id AND role_code='NIKO';
INSERT INTO plot_role_stats(plot_role_id,stat_code,label,initial_value)
SELECT plot_role_id,'SAFETY_FOCUS','Safety Focus',90 FROM plot_roles WHERE plot_id=@plot_id AND role_code='WREN';
INSERT INTO plot_role_stats(plot_role_id,stat_code,label,initial_value)
SELECT plot_role_id,'RISK','Risk Tolerance',60 FROM plot_roles WHERE plot_id=@plot_id AND role_code='WREN';
INSERT INTO plot_role_stats(plot_role_id,stat_code,label,initial_value)
SELECT plot_role_id,'SELF_PRESERVATION','Self Preservation',90 FROM plot_roles WHERE plot_id=@plot_id AND role_code='FARAH';
INSERT INTO plot_role_stats(plot_role_id,stat_code,label,initial_value)
SELECT plot_role_id,'MANIPULATION','Manipulation',75 FROM plot_roles WHERE plot_id=@plot_id AND role_code='FARAH';
INSERT INTO plot_role_stats(plot_role_id,stat_code,label,initial_value)
SELECT plot_role_id,'EVIDENCE_FOCUS','Evidence Focus',90 FROM plot_roles WHERE plot_id=@plot_id AND role_code='ALDEN';
INSERT INTO plot_role_stats(plot_role_id,stat_code,label,initial_value)
SELECT plot_role_id,'CAUTION','Caution',60 FROM plot_roles WHERE plot_id=@plot_id AND role_code='ALDEN';
INSERT INTO plot_role_stats(plot_role_id,stat_code,label,initial_value)
SELECT plot_role_id,'EMPATHY','Empathy',95 FROM plot_roles WHERE plot_id=@plot_id AND role_code='LEILA';
INSERT INTO plot_role_stats(plot_role_id,stat_code,label,initial_value)
SELECT plot_role_id,'URGENCY','Urgency',80 FROM plot_roles WHERE plot_id=@plot_id AND role_code='LEILA';
