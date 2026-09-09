-- Remove temporary Home sections created during page-builder testing.
-- These are separate site_page_sections, not rows_json entries.
DELETE s
FROM site_page_sections s
JOIN site_pages p ON p.site_page_id=s.site_page_id
WHERE p.page_code='HOME'
  AND s.label_text='What Is Toinky';
