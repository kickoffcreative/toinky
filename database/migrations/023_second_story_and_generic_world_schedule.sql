-- Story #2 proves the engine can run a non-Bellweather plot without story-specific PHP.
-- It also moves the authored world clock out of SimulationService and into plot data.

CREATE TABLE IF NOT EXISTS plot_world_events (
  plot_world_event_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  plot_id BIGINT UNSIGNED NOT NULL,
  event_second INT UNSIGNED NOT NULL,
  phase ENUM('EXPOSITION','INCITING_INCIDENT','RISING_ACTION','CLIMAX','RESOLUTION') NOT NULL,
  event_code VARCHAR(120) NOT NULL,
  headline VARCHAR(255) NOT NULL,
  event_text TEXT NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_plot_world_event(plot_id,event_code),
  KEY idx_plot_world_event_due(plot_id,event_second,sort_order),
  CONSTRAINT fk_pwe_plot FOREIGN KEY(plot_id) REFERENCES plots(plot_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Existing Bellweather clock, now data-driven.
SET @bell=(SELECT plot_id FROM plots WHERE code='CONCEALED_FAILURE' LIMIT 1);
INSERT IGNORE INTO plot_world_events(plot_id,event_second,phase,event_code,headline,event_text,sort_order) VALUES
(@bell,0,'EXPOSITION','STORY_START','Story Begins','Bellweather Resort settles into a rain-heavy evening. The world is now active.',0),
(@bell,45,'INCITING_INCIDENT','WALL_SHIFT','The East Wall Moves','A section of the east retaining wall gives way. The service road is damaged and a guest near the east stairwell is injured.',10),
(@bell,90,'INCITING_INCIDENT','REPORT_MISSING','The Report Becomes The Conflict','The copied failed inspection report becomes the center of the conflict. Who controls it now depends on earlier choices.',20),
(@bell,150,'RISING_ACTION','PRESSURE_RISE','Pressure Builds','The storm worsens. What happened during the first minutes now shapes who has evidence, who is helping the injured guest, and who controls the group’s attention.',30),
(@bell,230,'CLIMAX','REAR_EXIT','The Rear Exit','The east side becomes increasingly unsafe. The group must act with the evidence, relationships and positions created by their earlier choices.',40),
(@bell,285,'RESOLUTION','FINAL_STATE','What Remains','The simulation reaches its final minutes. The ending is determined by the world factors and unresolved consequences created during play.',50);

-- Completely different test plot: dynastic succession, no disaster/evidence-report mechanics.
INSERT INTO plots(code,title,archetype,genre,setting_name,max_players,max_npcs,status)
SELECT 'EMPTY_THRONE','The Empty Throne','Contested Succession','Political Drama','Kingdom of Valedorn',3,3,'active'
WHERE NOT EXISTS(SELECT 1 FROM plots WHERE code='EMPTY_THRONE');
SET @plot=(SELECT plot_id FROM plots WHERE code='EMPTY_THRONE' LIMIT 1);

INSERT IGNORE INTO plot_factors(plot_id,factor_code,label,initial_value,min_value,max_value,description) VALUES
(@plot,'STABILITY','Kingdom Stability',62,0,100,'How close the kingdom is to orderly succession or civil conflict.'),
(@plot,'LEGITIMACY','Crown Legitimacy',55,0,100,'How strongly court and public accept the emerging succession.'),
(@plot,'COURT_TRUST','Court Trust',48,0,100,'How willing the major actors are to coordinate and believe one another.'),
(@plot,'UNREST','Public Unrest',28,0,100,'How close the capital is to disorder.'),
(@plot,'SECRECY','Secret Control',72,0,100,'How contained the late king’s succession secret remains.');

INSERT IGNORE INTO plot_roles(plot_id,role_code,role_type,slot_no,name,public_description,private_knowledge,starting_location,goal_text,fear_text) VALUES
(@plot,'ELIAN','PLAYER',1,'Prince Elian Vale','The king’s younger son: respected by soldiers, but not first in line.','Your father privately told you that Rowan’s claim could be void if the sealed abdication letter is genuine.','Council Chamber','Secure a legitimate path to the crown without breaking the kingdom.','Winning the crown by causing a civil war.'),
(@plot,'MARA','PLAYER',2,'Lady Mara Vey','A senior councillor whose family controls grain routes into the capital.','The Queen told you she would leave Valedorn rather than endorse a succession she believes was manipulated.','Council Chamber','Keep the succession peaceful and the capital supplied.','The court splitting into armed factions.'),
(@plot,'TOMAS','PLAYER',3,'Captain Tomas Reed','Commander of the palace guard, formally sworn to the lawful sovereign.','Two guard officers report that the sealed royal archive was opened during the king’s final night.','Guard Hall','Keep the palace secure until lawful authority is clear.','Being used to enforce an illegitimate succession.'),
(@plot,'ROWAN','NPC',1,'Prince Rowan Vale','The king’s eldest son and presumed heir, confident that tradition favors him.','You secretly signed an abdication letter months ago, then tried to withdraw it after your father became ill.','Royal Apartments','Be crowned before the court can challenge your claim.','The abdication letter becoming authoritative.'),
(@plot,'QUEEN','NPC',2,'Queen Celene','The late king’s widow, politically cautious and deeply concerned about dynastic violence.','You witnessed Rowan sign the abdication letter and know the King kept the original sealed in the royal archive.','Queen’s Gallery','Prevent a disputed coronation from destroying the dynasty.','Either prince using force against the other.'),
(@plot,'HAND','NPC',3,'Lord Soren Hale','The King’s Hand, administrator of the realm and controller of the council agenda.','The King instructed you to delay any coronation if the succession documents were disputed.','Council Chamber','Preserve continuity of government while choosing which claim to support.','Backing the losing claimant and collapsing the government.');

INSERT IGNORE INTO plot_role_stats(plot_role_id,stat_code,label,initial_value)
SELECT plot_role_id,'AMBITION','Ambition',78 FROM plot_roles WHERE plot_id=@plot AND role_code='ELIAN';
INSERT IGNORE INTO plot_role_stats(plot_role_id,stat_code,label,initial_value)
SELECT plot_role_id,'RESTRAINT','Restraint',68 FROM plot_roles WHERE plot_id=@plot AND role_code='ELIAN';
INSERT IGNORE INTO plot_role_stats(plot_role_id,stat_code,label,initial_value)
SELECT plot_role_id,'DIPLOMACY','Diplomacy',88 FROM plot_roles WHERE plot_id=@plot AND role_code='MARA';
INSERT IGNORE INTO plot_role_stats(plot_role_id,stat_code,label,initial_value)
SELECT plot_role_id,'PRAGMATISM','Pragmatism',82 FROM plot_roles WHERE plot_id=@plot AND role_code='MARA';
INSERT IGNORE INTO plot_role_stats(plot_role_id,stat_code,label,initial_value)
SELECT plot_role_id,'DUTY','Sense of Duty',92 FROM plot_roles WHERE plot_id=@plot AND role_code='TOMAS';
INSERT IGNORE INTO plot_role_stats(plot_role_id,stat_code,label,initial_value)
SELECT plot_role_id,'CAUTION','Caution',70 FROM plot_roles WHERE plot_id=@plot AND role_code='TOMAS';
INSERT IGNORE INTO plot_role_stats(plot_role_id,stat_code,label,initial_value)
SELECT plot_role_id,'AMBITION','Ambition',94 FROM plot_roles WHERE plot_id=@plot AND role_code='ROWAN';
INSERT IGNORE INTO plot_role_stats(plot_role_id,stat_code,label,initial_value)
SELECT plot_role_id,'PRIDE','Pride',86 FROM plot_roles WHERE plot_id=@plot AND role_code='ROWAN';
INSERT IGNORE INTO plot_role_stats(plot_role_id,stat_code,label,initial_value)
SELECT plot_role_id,'CAUTION','Caution',84 FROM plot_roles WHERE plot_id=@plot AND role_code='QUEEN';
INSERT IGNORE INTO plot_role_stats(plot_role_id,stat_code,label,initial_value)
SELECT plot_role_id,'DYNASTY','Dynastic Loyalty',93 FROM plot_roles WHERE plot_id=@plot AND role_code='QUEEN';
INSERT IGNORE INTO plot_role_stats(plot_role_id,stat_code,label,initial_value)
SELECT plot_role_id,'PRAGMATISM','Pragmatism',90 FROM plot_roles WHERE plot_id=@plot AND role_code='HAND';
INSERT IGNORE INTO plot_role_stats(plot_role_id,stat_code,label,initial_value)
SELECT plot_role_id,'LOYALTY','Institutional Loyalty',85 FROM plot_roles WHERE plot_id=@plot AND role_code='HAND';

-- Generic entities/properties used by this story.
INSERT IGNORE INTO plot_entities(plot_id,entity_code,label,entity_type) VALUES
(@plot,'KING','Late King','PERSON'),(@plot,'ROWAN','Prince Rowan','PERSON'),(@plot,'ELIAN','Prince Elian','PERSON'),(@plot,'QUEEN','Queen Celene','PERSON'),(@plot,'HAND','King''s Hand','PERSON'),(@plot,'ABDICATION','Abdication Letter','OBJECT'),(@plot,'CROWN','Crown','OBJECT'),(@plot,'CAPITAL','Capital','PLACE');

SET @e=(SELECT plot_entity_id FROM plot_entities WHERE plot_id=@plot AND entity_code='KING');
INSERT IGNORE INTO plot_entity_properties(plot_entity_id,property_code,label,value_type,initial_value,allowed_values) VALUES(@e,'status','King Status','TEXT','DEAD','ALIVE,INCAPACITATED,DEAD');
SET @e=(SELECT plot_entity_id FROM plot_entities WHERE plot_id=@plot AND entity_code='ROWAN');
INSERT IGNORE INTO plot_entity_properties(plot_entity_id,property_code,label,value_type,initial_value,allowed_values) VALUES(@e,'claim','Rowan Claim','TEXT','PRESUMED_HEIR','PRESUMED_HEIR,DISPUTED,RENOUNCED,DISQUALIFIED,CROWNED');
SET @e=(SELECT plot_entity_id FROM plot_entities WHERE plot_id=@plot AND entity_code='ELIAN');
INSERT IGNORE INTO plot_entity_properties(plot_entity_id,property_code,label,value_type,initial_value,allowed_values) VALUES(@e,'claim','Elian Claim','TEXT','SECONDARY','SECONDARY,ACTIVE,SUPPORTED,CROWNED');
SET @e=(SELECT plot_entity_id FROM plot_entities WHERE plot_id=@plot AND entity_code='QUEEN');
INSERT IGNORE INTO plot_entity_properties(plot_entity_id,property_code,label,value_type,initial_value,allowed_values) VALUES(@e,'position','Queen Position','TEXT','IN_KINGDOM','IN_KINGDOM,WITHDRAWN,LEFT_KINGDOM');
SET @e=(SELECT plot_entity_id FROM plot_entities WHERE plot_id=@plot AND entity_code='HAND');
INSERT IGNORE INTO plot_entity_properties(plot_entity_id,property_code,label,value_type,initial_value,allowed_values) VALUES(@e,'loyalty','Hand Loyalty','TEXT','CROWN','CROWN,ROWAN,ELIAN,NEUTRAL');
SET @e=(SELECT plot_entity_id FROM plot_entities WHERE plot_id=@plot AND entity_code='ABDICATION');
INSERT IGNORE INTO plot_entity_properties(plot_entity_id,property_code,label,value_type,initial_value,allowed_values) VALUES(@e,'status','Letter Status','TEXT','SEALED','SEALED,FOUND,VERIFIED,DESTROYED,PUBLIC'),(@e,'holder','Letter Holder','TEXT','ARCHIVE','ARCHIVE,ROWAN,ELIAN,QUEEN,HAND,TOMAS');
SET @e=(SELECT plot_entity_id FROM plot_entities WHERE plot_id=@plot AND entity_code='CROWN');
INSERT IGNORE INTO plot_entity_properties(plot_entity_id,property_code,label,value_type,initial_value,allowed_values) VALUES(@e,'holder','Crown Holder','TEXT','NONE','NONE,ROWAN,ELIAN');
SET @e=(SELECT plot_entity_id FROM plot_entities WHERE plot_id=@plot AND entity_code='CAPITAL');
INSERT IGNORE INTO plot_entity_properties(plot_entity_id,property_code,label,value_type,initial_value,allowed_values) VALUES(@e,'security','Capital Security','TEXT','TENSE','CALM,TENSE,RIOTING,LOCKDOWN');

INSERT IGNORE INTO plot_state_aliases(plot_id,state_key,entity_code,property_code) VALUES
(@plot,'KING_STATUS','KING','status'),(@plot,'ROWAN_CLAIM','ROWAN','claim'),(@plot,'ELIAN_CLAIM','ELIAN','claim'),(@plot,'QUEEN_POSITION','QUEEN','position'),(@plot,'HAND_LOYALTY','HAND','loyalty'),(@plot,'LETTER_STATUS','ABDICATION','status'),(@plot,'LETTER_HOLDER','ABDICATION','holder'),(@plot,'CROWN_HOLDER','CROWN','holder'),(@plot,'CAPITAL_SECURITY','CAPITAL','security');

-- Authored world clock. Same PHP will execute these as Bellweather.
INSERT IGNORE INTO plot_world_events(plot_id,event_second,phase,event_code,headline,event_text,sort_order) VALUES
(@plot,0,'EXPOSITION','STORY_START','The King Is Dead','King Edric dies before dawn. By tradition Rowan is next in line, but the council has not yet proclaimed a successor.',0),
(@plot,45,'INCITING_INCIDENT','SEALED_ARCHIVE','The Archive Seal Is Broken','A palace clerk reports that the royal archive seal was disturbed during the king’s final night. The succession documents may no longer be secure.',10),
(@plot,100,'INCITING_INCIDENT','LETTER_RUMOR','A Letter Is Mentioned','Word reaches the council that the King kept a sealed document concerning Rowan’s succession. Nobody yet agrees what it says or who should control it.',20),
(@plot,165,'RISING_ACTION','COUNCIL_DIVIDES','The Council Divides','Nobles begin declaring preferences. Delay now affects legitimacy, public order and the loyalty of the institutions around the throne.',30),
(@plot,235,'CLIMAX','CORONATION_HOUR','The Coronation Hour','The bells are prepared. Whoever controls the claim, the court and the surviving succession evidence can now force or prevent a coronation.',40),
(@plot,285,'RESOLUTION','FINAL_STATE','What The Kingdom Inherits','The immediate succession crisis closes. The final state of the crown, the letter, institutional loyalty and public order determines what follows.',50);

-- Perceptions for the first crisis prove the same behavior engine can frame a different world.
INSERT IGNORE INTO plot_event_perceptions(plot_id,event_code,role_code,history_type,title,perception_text,location_after) VALUES
(@plot,'SEALED_ARCHIVE','ELIAN','OBSERVED','A Legal Path May Exist','The broken seal makes your father’s private warning about Rowan’s abdication letter suddenly actionable.',NULL),
(@plot,'SEALED_ARCHIVE','MARA','EXPERIENCED','The Succession Can Break The City','If the archive becomes a battlefield, grain deliveries and public order may collapse before anyone is crowned.',NULL),
(@plot,'SEALED_ARCHIVE','TOMAS','EXPERIENCED','The Palace Guard Is Now Part Of The Succession','A compromised royal archive turns palace security into a constitutional question, not merely a guard matter.',NULL),
(@plot,'SEALED_ARCHIVE','ROWAN','EXPERIENCED','The Letter Is A Threat','If the signed abdication letter survives and is accepted, your presumed succession can be challenged.',NULL),
(@plot,'SEALED_ARCHIVE','QUEEN','OBSERVED','The King Kept His Safeguard','The disturbed seal confirms someone may be searching for the document you witnessed Rowan sign.',NULL),
(@plot,'SEALED_ARCHIVE','HAND','EXPERIENCED','Continuity Is No Longer Simple','The king’s instruction to delay a disputed coronation now conflicts with pressure to proclaim Rowan immediately.',NULL);

INSERT IGNORE INTO plot_decision_templates(plot_id,event_code,role_code,actor_type,prompt_text,decision_window_seconds) VALUES
(@plot,'SEALED_ARCHIVE','ELIAN','PLAYER','The archive may contain the document that changes the succession. What do you do?',30),
(@plot,'SEALED_ARCHIVE','MARA','PLAYER','The archive dispute could destabilize the capital. What do you prioritize?',30),
(@plot,'SEALED_ARCHIVE','TOMAS','PLAYER','The royal archive has been compromised. How do you use the guard?',30),
(@plot,'SEALED_ARCHIVE','ROWAN','NPC','The archive may contain your signed abdication. Protect your claim.',30),
(@plot,'SEALED_ARCHIVE','QUEEN','NPC','The document you witnessed may be in danger. Protect the dynasty.',30),
(@plot,'SEALED_ARCHIVE','HAND','NPC','The succession is becoming disputed. Preserve government continuity.',30);

SET @t=(SELECT template_id FROM plot_decision_templates WHERE plot_id=@plot AND event_code='SEALED_ARCHIVE' AND role_code='ELIAN');
INSERT IGNORE INTO plot_decision_template_options(template_id,option_code,label,npc_weight,rationale_text,result_text,sort_order) VALUES
(@t,'SEEK_LETTER','Go to the archive and seek the sealed succession letter.',0,NULL,'You pursue documentary proof before openly challenging Rowan.',1),
(@t,'BUILD_SUPPORT','Stay in council and quietly test support for your claim.',0,NULL,'You build political support without making the letter your only path.',2),
(@t,'BACK_ORDER','Publicly support an orderly delay and avoid declaring a claim.',0,NULL,'You put stability ahead of immediate ambition.',3);
SET @o=(SELECT template_option_id FROM plot_decision_template_options WHERE template_id=@t AND option_code='SEEK_LETTER'); INSERT INTO plot_option_effects(template_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order) VALUES(@o,'LOCATION','SELF',NULL,'Royal Archive','Elian goes to the archive.',1),(@o,'WORLD_STATE','ELIAN_CLAIM',NULL,'ACTIVE','Elian begins actively pursuing a lawful alternative claim.',2);
SET @o=(SELECT template_option_id FROM plot_decision_template_options WHERE template_id=@t AND option_code='BUILD_SUPPORT'); INSERT INTO plot_option_effects(template_option_id,effect_scope,target_code,delta_value,detail_text,sort_order) VALUES(@o,'FACTOR','LEGITIMACY',5,'Elian tests whether the court sees him as a viable alternative.',1);
SET @o=(SELECT template_option_id FROM plot_decision_template_options WHERE template_id=@t AND option_code='BACK_ORDER'); INSERT INTO plot_option_effects(template_option_id,effect_scope,target_code,delta_value,detail_text,sort_order) VALUES(@o,'FACTOR','STABILITY',6,'Elian lowers the temperature by accepting a lawful delay.',1);

SET @t=(SELECT template_id FROM plot_decision_templates WHERE plot_id=@plot AND event_code='SEALED_ARCHIVE' AND role_code='MARA');
INSERT IGNORE INTO plot_decision_template_options(template_id,option_code,label,npc_weight,rationale_text,result_text,sort_order) VALUES(@t,'DELAY_PROCLAMATION','Demand that no heir be proclaimed until the archive is secured.',0,NULL,'You make procedural legitimacy the condition for succession.',1),(@t,'SECURE_GRAIN','Leave the claim dispute and secure grain routes into the capital.',0,NULL,'You protect the city against political disruption.',2),(@t,'ASK_QUEEN','Privately ask the Queen what she knows about the archive.',0,NULL,'You seek a credible witness before choosing a side.',3);
SET @o=(SELECT template_option_id FROM plot_decision_template_options WHERE template_id=@t AND option_code='DELAY_PROCLAMATION'); INSERT INTO plot_option_effects(template_option_id,effect_scope,target_code,delta_value,detail_text,sort_order) VALUES(@o,'FACTOR','LEGITIMACY',6,'A formal delay makes the eventual outcome more defensible.',1),(@o,'FACTOR','UNREST',3,'Delay also frustrates factions demanding certainty.',2);
SET @o=(SELECT template_option_id FROM plot_decision_template_options WHERE template_id=@t AND option_code='SECURE_GRAIN'); INSERT INTO plot_option_effects(template_option_id,effect_scope,target_code,delta_value,detail_text,sort_order) VALUES(@o,'FACTOR','STABILITY',7,'Food supply is insulated from the court crisis.',1);
SET @o=(SELECT template_option_id FROM plot_decision_template_options WHERE template_id=@t AND option_code='ASK_QUEEN'); INSERT INTO plot_option_effects(template_option_id,effect_scope,target_code,set_value,detail_text,sort_order) VALUES(@o,'KNOWLEDGE','QUEEN_WITNESS','The Queen witnessed Rowan sign an abdication letter.','Mara learns that the Queen can corroborate the letter.',1);

SET @t=(SELECT template_id FROM plot_decision_templates WHERE plot_id=@plot AND event_code='SEALED_ARCHIVE' AND role_code='TOMAS');
INSERT IGNORE INTO plot_decision_template_options(template_id,option_code,label,npc_weight,rationale_text,result_text,sort_order) VALUES(@t,'LOCK_ARCHIVE','Seal the archive under neutral guard control.',0,NULL,'You prevent either claimant from privately controlling the documents.',1),(@t,'GUARD_ROWAN','Put the guard visibly behind Rowan as presumed heir.',0,NULL,'You prioritize traditional succession and immediate order.',2),(@t,'GUARD_COUNCIL','Secure the council and refuse to enforce either claim yet.',0,NULL,'You keep armed force institutionally neutral.',3);
SET @o=(SELECT template_option_id FROM plot_decision_template_options WHERE template_id=@t AND option_code='LOCK_ARCHIVE'); INSERT INTO plot_option_effects(template_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order) VALUES(@o,'FACTOR','STABILITY',5,NULL,'Neutral custody reduces the chance of a violent scramble.',1),(@o,'WORLD_STATE','LETTER_HOLDER',NULL,'TOMAS','The palace guard takes neutral custody of the succession document.',2);
SET @o=(SELECT template_option_id FROM plot_decision_template_options WHERE template_id=@t AND option_code='GUARD_ROWAN'); INSERT INTO plot_option_effects(template_option_id,effect_scope,target_code,delta_value,detail_text,sort_order) VALUES(@o,'FACTOR','LEGITIMACY',4,'Guard support strengthens Rowan’s visible claim.',1),(@o,'FACTOR','COURT_TRUST',-6,'Neutral councillors fear the succession is being militarized.',2);
SET @o=(SELECT template_option_id FROM plot_decision_template_options WHERE template_id=@t AND option_code='GUARD_COUNCIL'); INSERT INTO plot_option_effects(template_option_id,effect_scope,target_code,delta_value,detail_text,sort_order) VALUES(@o,'FACTOR','COURT_TRUST',6,'The guard’s neutrality reassures the council.',1);

SET @t=(SELECT template_id FROM plot_decision_templates WHERE plot_id=@plot AND event_code='SEALED_ARCHIVE' AND role_code='ROWAN');
INSERT IGNORE INTO plot_decision_template_options(template_id,option_code,label,npc_weight,rationale_text,result_text,sort_order) VALUES(@t,'SEIZE_ARCHIVE','Use your presumed-heir authority to take control of the archive.',50,'Ambition favors controlling the document before it controls Rowan.','Rowan orders loyal attendants to secure the royal archive.',1),(@t,'DEMAND_CROWN','Demand immediate proclamation before rumors spread.',30,'Speed can turn presumption into political fact.','Rowan presses the council for immediate recognition.',2),(@t,'DISMISS_RUMOR','Stay composed and dismiss the broken seal as irrelevant.',20,'Pride favors acting as though the claim is unquestioned.','Rowan refuses to dignify the archive rumor.',3);
SET @o=(SELECT template_option_id FROM plot_decision_template_options WHERE template_id=@t AND option_code='SEIZE_ARCHIVE'); INSERT INTO plot_option_effects(template_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order) VALUES(@o,'FACTOR','COURT_TRUST',-7,NULL,'Rowan’s attempt to control the archive alarms the council.',1),(@o,'WORLD_STATE','LETTER_HOLDER',NULL,'ROWAN','Rowan takes control of the succession letter if it remains recoverable.',2);
SET @o=(SELECT template_option_id FROM plot_decision_template_options WHERE template_id=@t AND option_code='DEMAND_CROWN'); INSERT INTO plot_option_effects(template_option_id,effect_scope,target_code,delta_value,detail_text,sort_order) VALUES(@o,'FACTOR','UNREST',5,'Competing factions react to Rowan’s demand.',1);

SET @t=(SELECT template_id FROM plot_decision_templates WHERE plot_id=@plot AND event_code='SEALED_ARCHIVE' AND role_code='QUEEN');
INSERT IGNORE INTO plot_decision_template_options(template_id,option_code,label,npc_weight,rationale_text,result_text,sort_order) VALUES(@t,'REVEAL_WITNESS','Tell the Hand that you witnessed Rowan sign an abdication.',45,'Dynastic loyalty favors preventing a false certainty.','The Queen privately confirms that the letter is real.',1),(@t,'WITHDRAW','Withdraw from the council until neither prince threatens force.',35,'Caution favors refusing to legitimize escalation.','The Queen removes herself from the immediate succession fight.',2),(@t,'BACK_DELAY','Publicly support a temporary delay without revealing the letter.',20,'A procedural delay protects the dynasty without exposing everything.','The Queen calls for mourning and lawful review before coronation.',3);
SET @o=(SELECT template_option_id FROM plot_decision_template_options WHERE template_id=@t AND option_code='REVEAL_WITNESS'); INSERT INTO plot_option_effects(template_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order) VALUES(@o,'FACTOR','SECRECY',-18,NULL,'The succession secret is now shared with the Hand.',1),(@o,'WORLD_STATE','LETTER_STATUS',NULL,'FOUND','The Queen’s testimony turns the rumored letter into a concrete object of inquiry.',2);
SET @o=(SELECT template_option_id FROM plot_decision_template_options WHERE template_id=@t AND option_code='WITHDRAW'); INSERT INTO plot_option_effects(template_option_id,effect_scope,target_code,set_value,detail_text,sort_order) VALUES(@o,'WORLD_STATE','QUEEN_POSITION','WITHDRAWN','The Queen withdraws from the active council struggle.',1);

SET @t=(SELECT template_id FROM plot_decision_templates WHERE plot_id=@plot AND event_code='SEALED_ARCHIVE' AND role_code='HAND');
INSERT IGNORE INTO plot_decision_template_options(template_id,option_code,label,npc_weight,rationale_text,result_text,sort_order) VALUES(@t,'ORDER_DELAY','Invoke the King’s instruction and delay proclamation.',50,'Institutional loyalty favors process over speed.','The Hand orders that no coronation proceed until the dispute is reviewed.',1),(@t,'BACK_ROWAN','Treat Rowan as lawful heir and move government behind him.',30,'Pragmatism may favor the obvious claimant before uncertainty spreads.','The Hand begins aligning the government with Rowan.',2),(@t,'INVESTIGATE','Personally take custody of the archive inquiry.',20,'Control of process can preserve both government and options.','The Hand takes responsibility for the succession investigation.',3);
SET @o=(SELECT template_option_id FROM plot_decision_template_options WHERE template_id=@t AND option_code='ORDER_DELAY'); INSERT INTO plot_option_effects(template_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order) VALUES(@o,'FACTOR','LEGITIMACY',7,NULL,'Following the late king’s procedure strengthens institutional legitimacy.',1),(@o,'WORLD_STATE','HAND_LOYALTY',NULL,'NEUTRAL','The Hand remains institutionally neutral while the claim is reviewed.',2);
SET @o=(SELECT template_option_id FROM plot_decision_template_options WHERE template_id=@t AND option_code='BACK_ROWAN'); INSERT INTO plot_option_effects(template_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order) VALUES(@o,'FACTOR','STABILITY',4,NULL,'A clear government position creates short-term continuity.',1),(@o,'WORLD_STATE','HAND_LOYALTY',NULL,'ROWAN','The Hand commits the government to Rowan.',2);
SET @o=(SELECT template_option_id FROM plot_decision_template_options WHERE template_id=@t AND option_code='INVESTIGATE'); INSERT INTO plot_option_effects(template_option_id,effect_scope,target_code,set_value,detail_text,sort_order) VALUES(@o,'WORLD_STATE','LETTER_HOLDER','HAND','The Hand takes institutional custody of the succession inquiry.',1);

-- Resolution is entirely plot data.
DELETE c FROM plot_resolution_conditions c JOIN plot_resolution_fragments f ON f.resolution_fragment_id=c.resolution_fragment_id WHERE f.plot_id=@plot;
DELETE FROM plot_resolution_fragments WHERE plot_id=@plot;
INSERT INTO plot_resolution_fragments(plot_id,section_code,priority,fragment_text) VALUES
(@plot,'CROWN',10,'Prince Elian holds the crown at the close of the immediate succession crisis.'),
(@plot,'CROWN',20,'Prince Rowan holds the crown at the close of the immediate succession crisis.'),
(@plot,'CROWN',100,'No claimant has securely taken the crown; the succession remains open.'),
(@plot,'LETTER',10,'The abdication letter is public and can no longer be quietly erased from the succession.'),
(@plot,'LETTER',20,'The abdication letter survives in identified custody, but its authority is still contested.'),
(@plot,'LETTER',100,'The succession document remains uncertain, leaving rumor and private testimony to shape the next phase.'),
(@plot,'GOVERNMENT',10,'The King’s Hand has committed the machinery of government to Elian.'),
(@plot,'GOVERNMENT',20,'The King’s Hand has committed the machinery of government to Rowan.'),
(@plot,'GOVERNMENT',30,'The King’s Hand remains neutral, forcing the claimants to win legitimacy rather than simply inherit government control.'),
(@plot,'GOVERNMENT',100,'The government remains formally attached to the Crown while the personal succession is unresolved.');
SET @f=(SELECT resolution_fragment_id FROM plot_resolution_fragments WHERE plot_id=@plot AND section_code='CROWN' AND priority=10 LIMIT 1); INSERT INTO plot_resolution_conditions(resolution_fragment_id,state_key,operator,compare_value) VALUES(@f,'CROWN_HOLDER','EQ','ELIAN');
SET @f=(SELECT resolution_fragment_id FROM plot_resolution_fragments WHERE plot_id=@plot AND section_code='CROWN' AND priority=20 LIMIT 1); INSERT INTO plot_resolution_conditions(resolution_fragment_id,state_key,operator,compare_value) VALUES(@f,'CROWN_HOLDER','EQ','ROWAN');
SET @f=(SELECT resolution_fragment_id FROM plot_resolution_fragments WHERE plot_id=@plot AND section_code='LETTER' AND priority=10 LIMIT 1); INSERT INTO plot_resolution_conditions(resolution_fragment_id,state_key,operator,compare_value) VALUES(@f,'LETTER_STATUS','EQ','PUBLIC');
SET @f=(SELECT resolution_fragment_id FROM plot_resolution_fragments WHERE plot_id=@plot AND section_code='LETTER' AND priority=20 LIMIT 1); INSERT INTO plot_resolution_conditions(resolution_fragment_id,state_key,operator,compare_value) VALUES(@f,'LETTER_HOLDER','NOT_IN','ARCHIVE,NONE');
SET @f=(SELECT resolution_fragment_id FROM plot_resolution_fragments WHERE plot_id=@plot AND section_code='GOVERNMENT' AND priority=10 LIMIT 1); INSERT INTO plot_resolution_conditions(resolution_fragment_id,state_key,operator,compare_value) VALUES(@f,'HAND_LOYALTY','EQ','ELIAN');
SET @f=(SELECT resolution_fragment_id FROM plot_resolution_fragments WHERE plot_id=@plot AND section_code='GOVERNMENT' AND priority=20 LIMIT 1); INSERT INTO plot_resolution_conditions(resolution_fragment_id,state_key,operator,compare_value) VALUES(@f,'HAND_LOYALTY','EQ','ROWAN');
SET @f=(SELECT resolution_fragment_id FROM plot_resolution_fragments WHERE plot_id=@plot AND section_code='GOVERNMENT' AND priority=30 LIMIT 1); INSERT INTO plot_resolution_conditions(resolution_fragment_id,state_key,operator,compare_value) VALUES(@f,'HAND_LOYALTY','EQ','NEUTRAL');

INSERT IGNORE INTO schema_migrations(migration) VALUES('023_second_story_and_generic_world_schedule.sql');