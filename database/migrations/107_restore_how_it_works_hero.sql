-- Restore the managed How It Works hero after default-content cleanup.
UPDATE site_page_sections s
JOIN site_pages p ON p.site_page_id=s.site_page_id
SET s.active=1,
    s.label_text='How It Works',
    s.title_text='How It Works',
    s.content_html='<p>One shared story. Different perspectives. Every choice changes what happens next.</p>',
    s.layout_type='TEXT_CENTER',
    s.is_default=1
WHERE p.page_code='HOW_IT_WORKS'
  AND s.section_code='HERO';

INSERT INTO site_page_sections
    (site_page_id,section_code,label_text,title_text,content_html,layout_type,sort_order,active,is_default,updated_by_user_id)
SELECT p.site_page_id,'HERO','Toinky','How It Works',
       '<p>One shared story. Different perspectives. Every choice changes what happens next.</p>',
       'TEXT_CENTER',10,1,1,NULL
FROM site_pages p
WHERE p.page_code='HOW_IT_WORKS'
  AND p.active=1
  AND NOT EXISTS (
      SELECT 1 FROM site_page_sections s
      WHERE s.site_page_id=p.site_page_id AND s.section_code='HERO'
  );
