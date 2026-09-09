-- Clear seeded Home row content while preserving all parallax Hero fields.
-- The Home renderer uses the HERO section fields for its parallax experience.
UPDATE site_page_sections s
JOIN site_pages p ON p.site_page_id=s.site_page_id
SET s.rows_json=NULL
WHERE p.page_code='HOME'
  AND s.section_code='HERO';
