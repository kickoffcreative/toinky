UPDATE site_page_sections s
JOIN site_pages p ON p.site_page_id=s.site_page_id
SET s.rows_json=JSON_ARRAY(JSON_OBJECT(
  'layout_type','IMAGE_LEFT',
  'eyebrow_text','Different Perspectives',
  'title_text','You never know the whole story.',
  'content_html','<p>A prince protects a kingdom. A reporter follows a lie. A stranger knows what happens next. Toinky lets each player experience a personal storyline inside one living, shared event.</p>',
  'image_media_id',s.image_media_id,
  'bullets',JSON_ARRAY('Private information and character motives','Timed events for one player, several players, or everyone','Consequences that continue after a decision closes','Autonomous characters keep the world moving')
))
WHERE p.page_code='HOME' AND s.section_code='PERSPECTIVES' AND (s.rows_json IS NULL OR s.rows_json='');
