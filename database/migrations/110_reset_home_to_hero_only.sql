-- Home is intentionally rebuilt from the parallax Hero only.
DELETE s
FROM site_page_sections s
JOIN site_pages p ON p.site_page_id=s.site_page_id
WHERE p.page_code='HOME'
  AND s.section_code <> 'HERO';
