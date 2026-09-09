UPDATE site_page_sections s JOIN site_pages p ON p.site_page_id=s.site_page_id
SET s.rows_json=JSON_ARRAY(JSON_OBJECT(
 'layout_type','TEXT_TOP','eyebrow_text','For Players, Hosts, And Writers','title_text','Choose how you enter the story.','content_html','<p>Play a role, host a shared world, or build stories people can inhabit.</p>',
 'columns',JSON_ARRAY(
  JSON_OBJECT('icon_text','PLAY','title','Become part of the story.','content','<p>Take a role, follow your instincts, and compare what really happened when the game ends.</p>','buttons',JSON_ARRAY(JSON_OBJECT('label','Create an account','url','?view=register','visible',true))),
  JSON_OBJECT('icon_text','HOST','title','Bring everyone into one world.','content','<p>Choose the story, invite the cast, and let Toinky manage timing, private events, and unclaimed roles.</p>','buttons',JSON_ARRAY(JSON_OBJECT('label','Host your first game','url','?view=register','visible',true))),
  JSON_OBJECT('icon_text','WRITE','title','Build stories people can inhabit.','content','<p>Create characters with conflicting truths, branching decisions, and consequences that feel earned.</p>','buttons',JSON_ARRAY(JSON_OBJECT('label','Learn about writing for Toinky','url','?view=create','visible',true)))
 )
))
WHERE p.page_code='HOME' AND s.section_code='PATHS' AND (s.rows_json IS NULL OR s.rows_json='');
UPDATE site_page_sections s JOIN site_pages p ON p.site_page_id=s.site_page_id
SET s.rows_json=JSON_ARRAY(JSON_OBJECT('layout_type','TEXT_TOP','eyebrow_text','Your Story Is Waiting','title_text','Who will you become?','content_html','<p>The same moment can mean something different to everyone inside it.</p>','buttons',JSON_ARRAY(JSON_OBJECT('label','Enter Toinky','url','?view=register','visible',true))))
WHERE p.page_code='HOME' AND s.section_code='FINALE' AND (s.rows_json IS NULL OR s.rows_json='');
