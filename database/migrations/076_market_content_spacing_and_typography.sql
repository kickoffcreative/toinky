-- Market-facing public copy, section spacing, and typography inheritance.

SET @has_padding_top=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='site_page_sections' AND COLUMN_NAME='padding_top_px');
SET @sql=IF(@has_padding_top=0,'ALTER TABLE site_page_sections ADD COLUMN padding_top_px SMALLINT UNSIGNED NULL AFTER overlay_opacity, ADD COLUMN padding_right_px SMALLINT UNSIGNED NULL AFTER padding_top_px, ADD COLUMN padding_bottom_px SMALLINT UNSIGNED NULL AFTER padding_right_px, ADD COLUMN padding_left_px SMALLINT UNSIGNED NULL AFTER padding_bottom_px, ADD COLUMN mobile_padding_top_px SMALLINT UNSIGNED NULL AFTER padding_left_px, ADD COLUMN mobile_padding_right_px SMALLINT UNSIGNED NULL AFTER mobile_padding_top_px, ADD COLUMN mobile_padding_bottom_px SMALLINT UNSIGNED NULL AFTER mobile_padding_right_px, ADD COLUMN mobile_padding_left_px SMALLINT UNSIGNED NULL AFTER mobile_padding_bottom_px, ADD COLUMN label_font_size VARCHAR(20) NULL AFTER mobile_padding_left_px, ADD COLUMN label_color CHAR(9) NULL AFTER label_font_size, ADD COLUMN label_transform VARCHAR(20) NULL AFTER label_color, ADD COLUMN title_font_size VARCHAR(20) NULL AFTER label_transform, ADD COLUMN title_color CHAR(9) NULL AFTER title_font_size, ADD COLUMN title_transform VARCHAR(20) NULL AFTER title_color, ADD COLUMN body_font_size VARCHAR(20) NULL AFTER title_transform, ADD COLUMN body_color CHAR(9) NULL AFTER body_font_size, ADD COLUMN body_transform VARCHAR(20) NULL AFTER body_color','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_title_transform=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='genre_themes' AND COLUMN_NAME='title_transform');
SET @sql=IF(@has_title_transform=0,'ALTER TABLE genre_themes ADD COLUMN title_transform VARCHAR(20) NOT NULL DEFAULT ''none'' AFTER title_decoration, ADD COLUMN heading_transform VARCHAR(20) NOT NULL DEFAULT ''none'' AFTER heading_decoration, ADD COLUMN body_transform VARCHAR(20) NOT NULL DEFAULT ''none'' AFTER body_decoration, ADD COLUMN meta_transform VARCHAR(20) NOT NULL DEFAULT ''none'' AFTER meta_decoration, ADD COLUMN button_transform VARCHAR(20) NOT NULL DEFAULT ''none'' AFTER button_decoration','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE site_pages SET label_text='For Writers' WHERE page_code='CREATE';

UPDATE site_page_sections s JOIN site_pages p ON p.site_page_id=s.site_page_id
SET s.eyebrow_text='Live Character-Driven Stories',s.title_text='One story. Different truths.',s.content_html='<p>Gather your people, step into a shared world, and discover what happens when every character knows something the others do not.</p>'
WHERE p.page_code='HOME' AND s.section_code='HERO';

UPDATE site_page_sections s JOIN site_pages p ON p.site_page_id=s.site_page_id
SET s.eyebrow_text='How Toinky Works',s.title_text='Host the story. Let every player live their side.',s.content_html='<p>Choose a world, invite your cast, and let Toinky deliver private motives, timed moments, and meaningful decisions while everyone plays together.</p>'
WHERE p.page_code='HOME' AND s.section_code='HOW';

UPDATE site_page_sections s JOIN site_pages p ON p.site_page_id=s.site_page_id
SET s.eyebrow_text='Different Perspectives',s.title_text='Nobody sees the whole story.',s.content_html='<p>Every player receives a character, private knowledge, and a personal reason to care. What looks obvious to one person may feel completely different to another.</p>'
WHERE p.page_code='HOME' AND s.section_code='PERSPECTIVES';

INSERT INTO site_page_sections(site_page_id,section_code,label_text,eyebrow_text,title_text,content_html,layout_type,columns_json,sort_order)
SELECT p.site_page_id,'THREE_VIEWPOINTS','One Moment, Three Viewpoints','One Moment, Three Viewpoints','The same event changes meaning with every point of view.','<p>A shared story becomes unforgettable when the cast compares what each person knew, feared, and chose.</p>','THREE_COLUMN','[{"title":"The Heir","content":"<p>A sealed letter could protect the crown—or expose a betrayal.</p>","image_media_id":null},{"title":"The Investigator","content":"<p>The same letter is evidence, and someone is trying to bury it.</p>","image_media_id":null},{"title":"The Confidant","content":"<p>The letter is personal. Revealing it may save the truth and ruin a friendship.</p>","image_media_id":null}]',35
FROM site_pages p WHERE p.page_code='HOME'
ON DUPLICATE KEY UPDATE label_text=VALUES(label_text),eyebrow_text=VALUES(eyebrow_text),title_text=VALUES(title_text),content_html=VALUES(content_html),layout_type=VALUES(layout_type),columns_json=VALUES(columns_json),sort_order=VALUES(sort_order);

UPDATE site_page_sections s JOIN site_pages p ON p.site_page_id=s.site_page_id
SET s.eyebrow_text='Story Worlds',s.title_text='Find a world your group will talk about afterward.',s.content_html='<p>Choose a story by atmosphere and cast size, then invite the people who will bring it to life.</p>',s.sort_order=40
WHERE p.page_code='HOME' AND s.section_code='WORLDS';

UPDATE site_page_sections s JOIN site_pages p ON p.site_page_id=s.site_page_id
SET s.eyebrow_text='Players, Hosts, And Writers',s.title_text='There is more than one way into the story.',s.content_html='<p>Play a role, bring a group together, or create a world other people can inhabit.</p>',s.sort_order=50
WHERE p.page_code='HOME' AND s.section_code='PATHS';

UPDATE site_page_sections s JOIN site_pages p ON p.site_page_id=s.site_page_id
SET s.eyebrow_text='Your Story Is Waiting',s.title_text='Bring your people. We will bring the secrets.',s.content_html='<p>Choose a story, invite your cast, and see how differently everyone remembers the same night.</p>',s.sort_order=60
WHERE p.page_code='HOME' AND s.section_code='FINALE';

UPDATE site_page_sections s JOIN site_pages p ON p.site_page_id=s.site_page_id
SET s.eyebrow_text='How It Works',s.title_text='From invitation to final reveal.',s.content_html='<p>Toinky gives the host a simple path into the story and gives every player a private experience inside the same shared game.</p>'
WHERE p.page_code='HOW_IT_WORKS' AND s.section_code='INTRO';

UPDATE site_page_sections s JOIN site_pages p ON p.site_page_id=s.site_page_id
SET s.eyebrow_text='The Experience',s.title_text='Four steps. One shared story.',s.content_html='<ol><li><strong>Choose a story.</strong> Pick the world and cast size that fit your group.</li><li><strong>Invite the cast.</strong> Players join from their own device and claim a character.</li><li><strong>Play your perspective.</strong> Toinky delivers private knowledge, timed events, choices, and consequences.</li><li><strong>Compare the truth.</strong> Finish together and discover what everyone else experienced.</li></ol>'
WHERE p.page_code='HOW_IT_WORKS' AND s.section_code='JOURNEY';

UPDATE site_page_sections s JOIN site_pages p ON p.site_page_id=s.site_page_id
SET s.eyebrow_text='Good To Know',s.title_text='Everything your group needs to begin.',s.content_html='<p><strong>Who hosts?</strong> Anyone with a Toinky account can choose a story and invite the group.</p><p><strong>What do players need?</strong> A phone or computer, an invitation, and the willingness to make choices in character.</p><p><strong>Does everyone see the same content?</strong> No. Shared moments connect the group, while private information makes every perspective distinct.</p><p><strong>Does the host need to run the story manually?</strong> No. Toinky manages the timing, character information, and story events.</p>'
WHERE p.page_code='HOW_IT_WORKS' AND s.section_code='FAQ';

UPDATE site_page_sections s JOIN site_pages p ON p.site_page_id=s.site_page_id
SET s.eyebrow_text='Story Worlds',s.title_text='Choose the world. Discover your truth.',s.content_html='<p>Every Toinky story places your group inside one shared situation, then gives each character private stakes, relationships, and decisions.</p>'
WHERE p.page_code='STORIES' AND s.section_code='HERO';

UPDATE site_page_sections s JOIN site_pages p ON p.site_page_id=s.site_page_id
SET s.active=0
WHERE p.page_code='STORIES' AND s.section_code='CATALOG';

INSERT INTO site_page_sections(site_page_id,section_code,label_text,eyebrow_text,title_text,content_html,layout_type,sort_order)
SELECT p.site_page_id,'CHOOSING','Choosing A Story','Choosing A Story','Start with the people around your table.','<p>Look for a cast size that fits your group and a world that creates the right kind of tension. The best choice is the story everyone is curious to enter—not the one they already know how to solve.</p>','TEXT_CENTER',30
FROM site_pages p WHERE p.page_code='STORIES'
ON DUPLICATE KEY UPDATE eyebrow_text=VALUES(eyebrow_text),title_text=VALUES(title_text),content_html=VALUES(content_html),layout_type=VALUES(layout_type),sort_order=VALUES(sort_order),active=1;

UPDATE site_page_sections s JOIN site_pages p ON p.site_page_id=s.site_page_id
SET s.eyebrow_text='For Writers',s.title_text='Write the truths that players will collide over.',s.content_html='<p>Toinky writers create playable worlds built from character perspective: private knowledge, competing motives, timed events, consequential choices, and endings the cast earns together.</p>'
WHERE p.page_code='CREATE' AND s.section_code='HERO';

UPDATE site_page_sections s JOIN site_pages p ON p.site_page_id=s.site_page_id
SET s.eyebrow_text='The Writing Process',s.title_text='From premise to playable world.',s.content_html='<p>Start with a shared pressure point. Build characters who want different things, decide what each person knows, and create moments where their choices meaningfully change what follows. The Story Writer guides structure, relationships, events, decisions, outcomes, and review.</p>'
WHERE p.page_code='CREATE' AND s.section_code='PROCESS';

UPDATE site_page_sections s JOIN site_pages p ON p.site_page_id=s.site_page_id
SET s.eyebrow_text='Become A Toinky Writer',s.title_text='Bring us a world people can inhabit.',s.content_html='<p>Writers can request access from a Toinky account. Approved creators use the guided Story Writer and submit completed stories for review before publication.</p><p><a href="?view=register">Create an account and request writer access →</a></p>'
WHERE p.page_code='CREATE' AND s.section_code='ACCESS';

UPDATE site_page_sections s JOIN site_pages p ON p.site_page_id=s.site_page_id
SET s.eyebrow_text='About Toinky',s.title_text='A shared story is more interesting when nobody knows everything.',s.content_html='<p>Toinky is a hosted, character-driven storytelling game about perspective. It brings a group into one world, gives every player a different truth, and lets human choices decide what the story becomes.</p>'
WHERE p.page_code='ABOUT' AND s.section_code='HERO';

UPDATE site_page_sections s JOIN site_pages p ON p.site_page_id=s.site_page_id
SET s.eyebrow_text='What We Believe',s.title_text='Designed around three principles.',s.content_html='<p>The platform handles structure and timing so the people playing can stay present with one another.</p>',s.layout_type='THREE_COLUMN',s.columns_json='[{"title":"Perspective Creates Tension","content":"<p>Characters become believable when each person has a partial, personal version of the truth.</p>","image_media_id":null},{"title":"Choice Belongs To People","content":"<p>Automation can keep a world moving, but the decisions that matter remain human.</p>","image_media_id":null},{"title":"The Reveal Is Social","content":"<p>The final conversation—when everyone compares what happened—is part of the experience.</p>","image_media_id":null}]'
WHERE p.page_code='ABOUT' AND s.section_code='PHILOSOPHY';

UPDATE site_page_sections s JOIN site_pages p ON p.site_page_id=s.site_page_id
SET s.eyebrow_text='Stay Connected',s.title_text='Follow the worlds taking shape.',s.content_html='<p>Use the links below to follow Toinky, ask about hosting, or start a conversation about writing and partnerships.</p>',s.layout_type='SOCIALS'
WHERE p.page_code='ABOUT' AND s.section_code='CONTACT';

INSERT IGNORE INTO schema_migrations(migration) VALUES('076_market_content_spacing_and_typography.sql');
